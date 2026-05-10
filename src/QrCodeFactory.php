<?php

declare(strict_types=1);

namespace Dunn\QrCode\Laravel;

use Dunn\QrCode\Builder;
use Dunn\QrCode\EccLevel;
use Dunn\QrCode\QrCode;
use Dunn\QrCode\Renderer\Renderer;
use Dunn\QrCode\Renderer\Svg\SvgRenderer;

/**
 * Convenience factory bound into the Laravel container as `qrcode`.
 *
 * Wraps the framework-agnostic core's static {@see QrCode::create()} so it can
 * be resolved via the {@see \Dunn\QrCode\Laravel\Facades\QrCode} facade and
 * pre-applies sensible defaults from `config/qrcode.php`.
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
     */
    public function create(string $data): Builder
    {
        $builder = QrCode::create($data);
        $ecc = $this->config['ecc'] ?? null;
        if ($ecc instanceof EccLevel) {
            $builder = $builder->errorCorrection($ecc);
        }

        return $builder;
    }

    /**
     * Build the QR for $data and render it. Pass a Renderer to override the
     * default (e.g. a styled SvgRenderer with DotModule + CircleEyeOuter).
     */
    public function svg(string $data, ?Renderer $renderer = null): string
    {
        return ($renderer ?? $this->renderer())->render($this->create($data)->build());
    }

    /**
     * Return the default renderer — either the one supplied to the factory
     * constructor, or a basic SvgRenderer built from config('qrcode').
     */
    public function renderer(): Renderer
    {
        return $this->defaultRenderer ?? new SvgRenderer(
            size: (int) ($this->config['size'] ?? 300),
            margin: (int) ($this->config['margin'] ?? 4),
            foreground: (string) ($this->config['foreground'] ?? '#000000'),
            background: (string) ($this->config['background'] ?? '#ffffff'),
        );
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
}
