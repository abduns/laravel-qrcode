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
    */
    'size' => 300,
    'margin' => 4,
    'foreground' => '#000000',
    'background' => '#ffffff',
];
