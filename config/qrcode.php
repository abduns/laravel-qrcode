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
    | Default rendering options
    |--------------------------------------------------------------------------
    |
    | These feed the basic SvgRenderer the factory builds when no custom
    | renderer is supplied. They are IGNORED once you pin a renderer via
    | QrCode::withRenderer(...) or pass one to ->svg($data, $renderer) /
    | response()->qrcode($data, $status, $renderer) — that renderer carries
    | its own size, margin, and colour configuration.
    |
    */
    'size' => 300,
    'margin' => 4,
    'foreground' => '#000000',
    'background' => '#ffffff',
];
