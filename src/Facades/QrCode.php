<?php

declare(strict_types=1);

namespace Dunn\QrCode\Laravel\Facades;

use Dunn\QrCode\Builder;
use Illuminate\Support\Facades\Facade;

/**
 * Static-call shortcut for the bound {@see \Dunn\QrCode\Laravel\QrCodeFactory}.
 *
 * @method static Builder create(string $data)
 * @method static string svg(string $data)
 * @method static \Dunn\QrCode\Renderer\Renderer renderer()
 */
final class QrCode extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'qrcode';
    }
}
