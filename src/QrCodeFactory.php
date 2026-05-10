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
    public function __construct(private array $config = [])
    {
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
     * Build the QR for $data and render it via the default SVG renderer.
     */
    public function svg(string $data): string
    {
        return $this->renderer()->render($this->create($data)->build());
    }

    /**
     * Construct the default SvgRenderer pre-configured from the config array.
     */
    public function renderer(): Renderer
    {
        return new SvgRenderer(
            size: (int) ($this->config['size'] ?? 300),
            margin: (int) ($this->config['margin'] ?? 4),
            foreground: (string) ($this->config['foreground'] ?? '#000000'),
            background: (string) ($this->config['background'] ?? '#ffffff'),
        );
    }
}
