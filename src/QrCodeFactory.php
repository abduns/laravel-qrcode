<?php

declare(strict_types=1);

namespace Dunn\QrCode\Laravel;

use Dunn\QrCode\Builder;
use Dunn\QrCode\EccLevel;
use Dunn\QrCode\Exception\InvalidConfigurationException;
use Dunn\QrCode\Payload\Email;
use Dunn\QrCode\Payload\Event;
use Dunn\QrCode\Payload\Geo;
use Dunn\QrCode\Payload\Phone;
use Dunn\QrCode\Payload\Sms;
use Dunn\QrCode\Payload\Text;
use Dunn\QrCode\Payload\Url;
use Dunn\QrCode\Payload\VCard;
use Dunn\QrCode\Payload\Wifi;
use Dunn\QrCode\Payload\WifiAuth;
use Dunn\QrCode\QrCode;
use Dunn\QrCode\Renderer\Console\ConsoleRenderer;
use Dunn\QrCode\Renderer\Png\GdPngRenderer;
use Dunn\QrCode\Renderer\Renderer;
use Dunn\QrCode\Renderer\Svg\SvgRenderer;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Convenience factory bound into the Laravel container as `qrcode`.
 *
 * Wraps the framework-agnostic core's static {@see QrCode::create()} (and the
 * v1.1 typed payload factories) so they can be resolved via the
 * {@see \Dunn\QrCode\Laravel\Facades\QrCode} facade and pre-apply sensible
 * defaults from `config/qrcode.php`.
 *
 * Pass a {@see Renderer} to {@see render()} / {@see svg()} (or
 * {@see withRenderer()}) to opt into the core package's styled rendering
 * (custom shapes / per-region colours / logo overlay).
 *
 * Configuration is read lazily on every call, so runtime changes to
 * `config('qrcode.*')` made after the factory was first resolved are honoured.
 */
final class QrCodeFactory
{
    private const RENDERER_KINDS = ['svg', 'png', 'console'];

    /**
     * @param array<string, mixed>|Repository $config either the `qrcode` config
     *     array (keys: ecc, renderer, size, margin, foreground, background) or
     *     the application config repository, which is then read lazily from
     *     the `qrcode.*` keys on every call
     */
    public function __construct(
        private array|Repository $config = [],
        private ?Renderer $defaultRenderer = null,
    ) {
    }

    /**
     * Begin building a QR code for $data, pre-applying the configured ECC.
     * Accepts a raw string or any {@see \Stringable} payload value object.
     *
     * @throws InvalidConfigurationException when config('qrcode.ecc') is not a valid level
     */
    public function create(string|\Stringable $data): Builder
    {
        $builder = QrCode::create($data);
        $ecc = $this->eccLevel();
        if ($ecc !== null) {
            $builder = $builder->errorCorrection($ecc);
        }

        return $builder;
    }

    public function url(string $url): Builder
    {
        return $this->create(new Url($url));
    }

    public function text(string $text): Builder
    {
        return $this->create(new Text($text));
    }

    public function phone(string $number): Builder
    {
        return $this->create(new Phone($number));
    }

    public function sms(string $number, ?string $body = null, bool $useSmsUri = false): Builder
    {
        return $this->create(new Sms($number, $body, $useSmsUri));
    }

    /**
     * @param list<string> $cc
     * @param list<string> $bcc
     */
    public function email(
        string $to,
        ?string $subject = null,
        ?string $body = null,
        array $cc = [],
        array $bcc = [],
    ): Builder {
        return $this->create(new Email($to, $subject, $body, $cc, $bcc));
    }

    public function geo(float $latitude, float $longitude, ?string $label = null): Builder
    {
        return $this->create(new Geo($latitude, $longitude, $label));
    }

    public function wifi(
        string $ssid,
        ?string $password = null,
        WifiAuth $auth = WifiAuth::WPA,
        bool $hidden = false,
    ): Builder {
        return $this->create(new Wifi($ssid, $password, $auth, $hidden));
    }

    public function vCard(VCard $vcard): Builder
    {
        return $this->create($vcard);
    }

    public function event(Event $event): Builder
    {
        return $this->create($event);
    }

    /**
     * Build the QR for $data and render it with the default renderer (or the
     * override). The default is picked from config('qrcode.renderer') —
     * `svg` / `png` / `console` — or the one pinned via withRenderer().
     *
     * This is the renderer-agnostic primary; svg()/png()/console() are sugar
     * pinned to a specific renderer family.
     */
    public function render(string|\Stringable $data, ?Renderer $renderer = null): string
    {
        return ($renderer ?? $this->renderer())->render($this->create($data)->build());
    }

    /**
     * Render to SVG markup. Used by the @qrcode Blade directive.
     *
     * Always produces SVG, whatever config('qrcode.renderer') says: a renderer
     * pinned via withRenderer() is used only when it produces SVG
     * (`image/svg+xml`); otherwise an SvgRenderer is built from config. An
     * explicitly passed $renderer is used as is, like png() and console().
     */
    public function svg(string|\Stringable $data, ?Renderer $renderer = null): string
    {
        return ($renderer ?? $this->svgRenderer())->render($this->create($data)->build());
    }

    /**
     * Render to PNG. Forces a GdPngRenderer built from config when no
     * override is supplied — ignores config('qrcode.renderer') and any
     * pinned default, so the result is always PNG bytes.
     *
     * Requires the `ext-gd` extension at render time.
     */
    public function png(string|\Stringable $data, ?Renderer $renderer = null): string
    {
        return ($renderer ?? $this->buildRenderer('png'))->render($this->create($data)->build());
    }

    /**
     * Render to a monospace console string. Forces a ConsoleRenderer when no
     * override is supplied. Useful for tinker/CLI debugging.
     */
    public function console(string|\Stringable $data, ?Renderer $renderer = null): string
    {
        return ($renderer ?? $this->buildRenderer('console'))->render($this->create($data)->build());
    }

    /**
     * Return the default renderer — either the one pinned via withRenderer(),
     * or a fresh instance built from the current config('qrcode') honouring
     * the `renderer` key (`svg` | `png` | `console`).
     *
     * @throws InvalidConfigurationException when the renderer name or its options are invalid
     */
    public function renderer(): Renderer
    {
        return $this->defaultRenderer ?? $this->buildRenderer($this->rendererKind());
    }

    /**
     * Return a new factory with the given renderer pinned as the default.
     * Useful for app-wide styled output:
     *
     *     $styled = QrCode::withRenderer(new SvgRenderer(moduleShape: new DotModule()));
     *     $styled->svg('https://example.com');
     */
    public function withRenderer(Renderer $renderer): self
    {
        return new self($this->config, $renderer);
    }

    /**
     * JSON-friendly payload for Inertia, React, Vue, and API clients.
     *
     *     return QrCode::payload('https://example.com');
     *     // {"mimeType":"image/svg+xml","dataUri":"data:image/svg+xml;base64,...","svg":"<svg ...>"}
     */
    public function payload(string|\Stringable $data, ?Renderer $renderer = null): QrCodePayload
    {
        $renderer ??= $this->renderer();
        $body = $renderer->render($this->create($data)->build());
        $mime = $renderer->mimeType();

        return new QrCodePayload(
            mimeType: $mime,
            dataUri: QrCodePayload::encodeDataUri($mime, $body),
            svg: self::isSvgMime($mime) ? $body : null,
        );
    }

    /**
     * Data URI for <img src>. Same renderer chain as {@see render()}.
     *
     *     <img src={qr.dataUri} />
     *     <img :src="qr.dataUri" />
     */
    public function dataUri(string|\Stringable $data, ?Renderer $renderer = null): string
    {
        return $this->payload($data, $renderer)->dataUri;
    }

    /**
     * @return array{mimeType: string, dataUri: string, svg: string|null}
     */
    public function toArray(string|\Stringable $data, ?Renderer $renderer = null): array
    {
        return $this->payload($data, $renderer)->toArray();
    }

    /**
     * HTTP response for <img src="/qr?data=..."> or a file download.
     *
     * Always sets an ETag (quoted SHA-256 of the body). Pass $filename and/or
     * $download for Content-Disposition, $maxAge (seconds) for Cache-Control.
     * Pass the current $request to answer a matching `If-None-Match` with
     * `304 Not Modified` (only for a 200 response to a GET/HEAD request); the
     * response()->qrcode() macro does this automatically.
     */
    public function toResponse(
        string|\Stringable $data,
        int $status = 200,
        ?Renderer $renderer = null,
        ?string $filename = null,
        ?int $maxAge = null,
        bool $download = false,
        ?Request $request = null,
    ): Response {
        $renderer ??= $this->renderer();
        $body = $renderer->render($this->create($data)->build());
        $mime = $renderer->mimeType();

        $headers = ['Content-Type' => $mime];

        $disposition = $this->contentDisposition($filename, $mime, $download);
        if ($disposition !== null) {
            $headers['Content-Disposition'] = $disposition;
        }

        $response = new Response($body, $status, $headers);
        $response->setEtag(hash('sha256', $body));

        if ($maxAge !== null) {
            $response->setPublic();
            $response->setMaxAge($maxAge);
        }

        if ($request !== null && $status === 200) {
            $response->isNotModified($request);
        }

        return $response;
    }

    /**
     * Build the Content-Disposition header value (RFC 6266): an ASCII
     * `filename` plus a UTF-8 `filename*` when the name is not plain ASCII.
     */
    private function contentDisposition(?string $filename, string $mime, bool $download): ?string
    {
        if ($filename === null && ! $download) {
            return null;
        }

        $extension = $this->extensionForMime($mime);
        $name = $this->sanitizeFilename((string) $filename, $extension);
        $fallback = $this->sanitizeFilename(
            (string) preg_replace('/[^\x20-\x7E]|%/', '', Str::ascii($name)),
            $extension,
        );

        $header = ($download ? 'attachment' : 'inline').'; filename="'.$fallback.'"';
        if ($fallback !== $name) {
            $header .= "; filename*=UTF-8''".rawurlencode($name);
        }

        return $header;
    }

    /**
     * Strip path separators, quotes and control characters, drop leading and
     * trailing dots/whitespace, and make sure the name ends with $extension.
     */
    private function sanitizeFilename(string $filename, string $extension): string
    {
        $name = rtrim((string) preg_replace('/[\x00-\x1F\x7F"\'\\\\\/]/', '', $filename), " \t.");

        $suffix = pathinfo($name, PATHINFO_EXTENSION);
        if (strtolower($suffix) === $extension) {
            $base = substr($name, 0, -strlen($suffix) - 1);
        } else {
            $base = $name;
            $suffix = $extension;
        }

        $base = trim($base, " \t.");

        return ($base === '' ? 'qrcode' : $base).'.'.$suffix;
    }

    private function extensionForMime(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/png') => 'png',
            str_starts_with($mime, 'text/plain') => 'txt',
            default => 'svg',
        };
    }

    private static function isSvgMime(string $mime): bool
    {
        return str_starts_with(strtolower($mime), 'image/svg');
    }

    /**
     * The pinned renderer when it produces SVG, otherwise an SvgRenderer
     * built from config.
     */
    private function svgRenderer(): Renderer
    {
        if ($this->defaultRenderer !== null && self::isSvgMime($this->defaultRenderer->mimeType())) {
            return $this->defaultRenderer;
        }

        return $this->buildRenderer('svg');
    }

    private function buildRenderer(string $kind): Renderer
    {
        try {
            return match ($kind) {
                'png' => new GdPngRenderer(
                    size: $this->intOption('size', 300),
                    margin: $this->intOption('margin', 4),
                    foreground: $this->stringOption('foreground', '#000000'),
                    background: $this->stringOption('background', '#ffffff'),
                ),
                'console' => new ConsoleRenderer(
                    margin: $this->intOption('margin', 1),
                ),
                'svg' => new SvgRenderer(
                    size: $this->intOption('size', 300),
                    margin: $this->intOption('margin', 4),
                    foreground: $this->stringOption('foreground', '#000000'),
                    background: $this->stringOption('background', '#ffffff'),
                ),
                default => throw self::invalidOption('renderer', $kind, '"svg", "png" or "console"'),
            };
        } catch (\InvalidArgumentException $e) {
            // The core renderers and Color reject bad size/margin/colour values
            // with a plain \InvalidArgumentException; surface them as the
            // documented configuration error instead.
            throw new InvalidConfigurationException('Invalid qrcode rendering config: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Resolve config('qrcode.renderer'), accepting any case and surrounding
     * whitespace. Unset or empty means `svg`.
     */
    private function rendererKind(): string
    {
        $kind = $this->option('renderer');
        if ($kind === null || $kind === '') {
            return 'svg';
        }

        if (is_string($kind)) {
            $normalised = strtolower(trim($kind));
            if (in_array($normalised, self::RENDERER_KINDS, true)) {
                return $normalised;
            }
        }

        throw self::invalidOption('renderer', $kind, '"svg", "png" or "console"');
    }

    /**
     * Resolve config('qrcode.ecc'). Accepts an EccLevel, its backing value
     * ('L', 'M', 'Q', 'H') or its case name ('low', 'Medium', ...), in any
     * case, so env() strings work. Unset or empty means the core default.
     */
    private function eccLevel(): ?EccLevel
    {
        $ecc = $this->option('ecc');
        if ($ecc === null || $ecc === '') {
            return null;
        }

        if ($ecc instanceof EccLevel) {
            return $ecc;
        }

        if (is_string($ecc)) {
            $normalised = strtoupper(trim($ecc));
            foreach (EccLevel::cases() as $level) {
                if ($level->value === $normalised || strtoupper($level->name) === $normalised) {
                    return $level;
                }
            }
        }

        throw self::invalidOption('ecc', $ecc, 'an EccLevel case or one of "L", "M", "Q", "H", "low", "medium", "quartile", "high"');
    }

    private function intOption(string $key, int $default): int
    {
        $value = $this->option($key);
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\s*[+-]?\d+\s*$/', $value) === 1) {
            return (int) $value;
        }

        throw self::invalidOption($key, $value, 'an integer');
    }

    private function stringOption(string $key, string $default): string
    {
        $value = $this->option($key);
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_string($value)) {
            return $value;
        }

        throw self::invalidOption($key, $value, 'a hex colour string such as "#000000"');
    }

    private function option(string $key): mixed
    {
        if ($this->config instanceof Repository) {
            return $this->config->get('qrcode.'.$key);
        }

        return $this->config[$key] ?? null;
    }

    private static function invalidOption(string $key, mixed $value, string $expected): InvalidConfigurationException
    {
        $given = match (true) {
            is_string($value) => '"'.$value.'"',
            is_scalar($value) => var_export($value, true),
            is_object($value) => $value::class,
            default => get_debug_type($value),
        };

        return new InvalidConfigurationException(sprintf('Invalid qrcode.%s config value %s; expected %s.', $key, $given, $expected));
    }
}
