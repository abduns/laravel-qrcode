<?php

declare(strict_types=1);

namespace Dunn\QrCode\Laravel;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Stringable;

/**
 * JSON-serializable QR output for Inertia, React, Vue, and API clients.
 *
 * Return it from a controller for a JSON body, or pass it as an Inertia
 * prop. Frontends should prefer {@see $dataUri} as an <img src>; {@see $svg}
 * is the raw markup when the renderer produced SVG (null for PNG).
 *
 * @implements Arrayable<string, string|null>
 */
final readonly class QrCodePayload implements Arrayable, JsonSerializable, Stringable
{
    public function __construct(
        public string $mimeType,
        public string $dataUri,
        public ?string $svg = null,
    ) {
    }

    /**
     * @return array{mimeType: string, dataUri: string, svg: string|null}
     */
    public function toArray(): array
    {
        return [
            'mimeType' => $this->mimeType,
            'dataUri' => $this->dataUri,
            'svg' => $this->svg,
        ];
    }

    /**
     * @return array{mimeType: string, dataUri: string, svg: string|null}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * The data URI, so Blade `src="{{ $qr }}"` and string casts work.
     */
    public function __toString(): string
    {
        return $this->dataUri;
    }

    /**
     * Build a base64 data URI. Text MIME types without an explicit charset get
     * `;charset=utf-8`, since RFC 2397 would otherwise default them to
     * US-ASCII and garble the console renderer's block glyphs.
     */
    public static function encodeDataUri(string $mimeType, string $body): string
    {
        if (str_starts_with(strtolower($mimeType), 'text/') && ! str_contains(strtolower($mimeType), 'charset=')) {
            $mimeType .= ';charset=utf-8';
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($body);
    }
}
