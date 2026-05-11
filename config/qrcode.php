<?php

declare(strict_types=1);

use Dunn\QrCode\EccLevel;

return [
    /*
    |--------------------------------------------------------------------------
    | Default error correction level
    |--------------------------------------------------------------------------
    |
    | One of EccLevel::Low, Medium, Quartile, High. Trades data capacity for
    | damage tolerance: ~7%, ~15%, ~25%, ~30% recoverable respectively.
    |
    */
    'ecc' => EccLevel::Medium,

    /*
    |--------------------------------------------------------------------------
    | Default renderer
    |--------------------------------------------------------------------------
    |
    | Which renderer the factory builds when no override is passed and no
    | renderer is pinned via QrCode::withRenderer(). One of:
    |
    |   'svg'     — Dunn\QrCode\Renderer\Svg\SvgRenderer (default; zero deps)
    |   'png'     — Dunn\QrCode\Renderer\Png\GdPngRenderer (requires ext-gd)
    |   'console' — Dunn\QrCode\Renderer\Console\ConsoleRenderer (debug)
    |
    | Keep this as 'svg' if you use the @qrcode Blade directive — the
    | directive emits the renderer's output inline, so PNG bytes would land
    | as binary in your HTML. QrCode::png() / QrCode::console() ignore this
    | setting and always force their renderer type.
    |
    */
    'renderer' => 'svg',

    /*
    |--------------------------------------------------------------------------
    | Default rendering options
    |--------------------------------------------------------------------------
    |
    | These feed the renderer the factory builds when no custom one is
    | supplied. Which keys apply depends on the renderer:
    |
    |   svg / png — size, margin, foreground, background
    |   console   — margin only (in cells, not pixels)
    |
    | All four keys are IGNORED once you pin a renderer via
    | QrCode::withRenderer(...) or pass one to ->render() / ->svg() /
    | response()->qrcode(...) — that renderer carries its own configuration.
    |
    */
    'size' => 300,
    'margin' => 4,
    'foreground' => '#000000',
    'background' => '#ffffff',
];
