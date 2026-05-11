<?php

declare(strict_types=1);

namespace Dunn\QrCode\Laravel;

use Dunn\QrCode\Builder;
use Dunn\QrCode\EccLevel;
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

/**
 * Convenience factory bound into the Laravel container as `qrcode`.
 *
 * Wraps the framework-agnostic core's static {@see QrCode::create()} (and the
 * v1.1 typed payload factories) so they can be resolved via the
 * {@see \Dunn\QrCode\Laravel\Facades\QrCode} facade and pre-apply sensible
 * defaults from `config/qrcode.php`.
 *
 * Pass a {@see Renderer} to {@see svg()} (or {@see withRenderer()}) to opt
 * into the core package's styled rendering (custom shapes / per-region
 * colours / logo overlay).
 */
final class QrCodeFactory
{
    /**
     * @param array{
     *     ecc?: EccLevel,
     *     renderer?: 'svg'|'png'|'console',
     *     size?: int,
     *     margin?: int,
     *     foreground?: string,
     *     background?: string,
     * } $config
     */
    public function __construct(
        private array $config = [],
        private ?Renderer $defaultRenderer = null,
    ) {
    }

    /**
     * Begin building a QR code for $data, pre-applying the configured ECC.
     * Accepts a raw string or any {@see \Stringable} payload value object.
     */
    public function create(string|\Stringable $data): Builder
    {
        $builder = QrCode::create($data);
        $ecc = $this->config['ecc'] ?? null;
        if ($ecc instanceof EccLevel) {
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
     * Render with the default renderer (or the override). Behaves identically
     * to render() — kept as the historical name and called by the @qrcode
     * Blade directive. If you've set config('qrcode.renderer') = 'png' the
     * directive will emit PNG bytes inline, which is rarely useful — keep the
     * default as 'svg' for HTML embedding.
     */
    public function svg(string|\Stringable $data, ?Renderer $renderer = null): string
    {
        return $this->render($data, $renderer);
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
     * or a fresh instance built from config('qrcode') honouring the
     * `renderer` key (`svg` | `png` | `console`).
     */
    public function renderer(): Renderer
    {
        return $this->defaultRenderer ?? $this->buildRenderer($this->config['renderer'] ?? 'svg');
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

    private function buildRenderer(string $kind): Renderer
    {
        return match ($kind) {
            'png' => new GdPngRenderer(
                size: (int) ($this->config['size'] ?? 300),
                margin: (int) ($this->config['margin'] ?? 4),
                foreground: (string) ($this->config['foreground'] ?? '#000000'),
                background: (string) ($this->config['background'] ?? '#ffffff'),
            ),
            'console' => new ConsoleRenderer(
                margin: (int) ($this->config['margin'] ?? 1),
            ),
            default => new SvgRenderer(
                size: (int) ($this->config['size'] ?? 300),
                margin: (int) ($this->config['margin'] ?? 4),
                foreground: (string) ($this->config['foreground'] ?? '#000000'),
                background: (string) ($this->config['background'] ?? '#ffffff'),
            ),
        };
    }
}
