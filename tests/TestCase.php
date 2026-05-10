<?php

declare(strict_types=1);

namespace Dunn\QrCode\Laravel\Tests;

use Dunn\QrCode\Laravel\Providers\QrCodeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [QrCodeServiceProvider::class];
    }
}
