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
    | Strings work too, so the level can come from the environment:
    | 'L' / 'M' / 'Q' / 'H' or 'low' / 'medium' / 'quartile' / 'high' (any
    | case), e.g. env('QRCODE_ECC', 'M'). An unknown value throws
    | Dunn\QrCode\Exception\InvalidConfigurationException.
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
    | Names are case-insensitive; an unknown name throws
    | Dunn\QrCode\Exception\InvalidConfigurationException.
    |
    | This drives render(), payload(), dataUri(), toArray() and
    | response()->qrcode(). It does NOT change QrCode::svg() or the @qrcode
    | Blade directive (always SVG), nor QrCode::png() / QrCode::console()
    | (always their own renderer type).
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
    | size and margin are integers (numeric strings are accepted); colours
    | are hex strings such as '#000000'. Invalid values throw
    | Dunn\QrCode\Exception\InvalidConfigurationException when rendering.
    |
    | All four keys are IGNORED once you pin a renderer via
    | QrCode::withRenderer(...) or pass one to ->render() / ->svg() /
    | response()->qrcode(...) — that renderer carries its own configuration.
    | (svg() still builds an SvgRenderer from these keys when the pinned
    | renderer does not produce SVG.)
    |
    */
    'size' => 300,
    'margin' => 4,
    'foreground' => '#000000',
    'background' => '#ffffff',
];
