<?php

declare(strict_types=1);

use Dunn\QrCode\Laravel\Facades\QrCode;
use Dunn\QrCode\Laravel\Providers\QrCodeServiceProvider;
use Dunn\QrCode\Laravel\QrCodeFactory;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

it('publishes the config file under the qrcode-config tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(QrCodeServiceProvider::class, 'qrcode-config');

    expect($paths)->toHaveCount(1);
    $source = (string) array_key_first($paths);

    expect(realpath($source))->toBe(realpath(__DIR__.'/../../config/qrcode.php'));
    expect($paths[$source])->toBe(config_path('qrcode.php'));
});

it('publishes the config file through vendor:publish', function (): void {
    $target = config_path('qrcode.php');
    $existed = file_exists($target);
    $original = $existed ? (string) file_get_contents($target) : null;

    try {
        $this->artisan('vendor:publish', ['--tag' => 'qrcode-config', '--force' => true])->assertSuccessful();

        expect($target)->toBeFile();
        expect(file_get_contents($target))->toBe(file_get_contents(__DIR__.'/../../config/qrcode.php'));
    } finally {
        if ($existed) {
            file_put_contents($target, (string) $original);
        } elseif (file_exists($target)) {
            unlink($target);
        }
    }
});

it('merges the package config defaults', function (): void {
    expect(config('qrcode.renderer'))->toBe('svg');
    expect(config('qrcode.size'))->toBe(300);
});

it('declares the provider and facade alias for package auto-discovery', function (): void {
    /** @var array{extra: array{laravel: array{providers: list<string>, aliases: array<string, string>}}} $composer */
    $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['extra']['laravel']['providers'])->toBe([QrCodeServiceProvider::class]);
    expect($composer['extra']['laravel']['aliases'])->toBe(['QrCode' => QrCode::class]);
    expect(is_subclass_of(QrCode::class, Facade::class))->toBeTrue();
});

it('resolves the facade root to the bound factory', function (): void {
    expect(QrCode::getFacadeRoot())->toBeInstanceOf(QrCodeFactory::class)->toBe(app('qrcode'));
});

it('does not resolve the view or routing services while booting', function (): void {
    expect(app()->resolved('blade.compiler'))->toBeFalse();
    expect(app()->resolved('view'))->toBeFalse();
    expect(app()->resolved(ResponseFactory::class))->toBeFalse();
    expect(app()->resolved('redirect'))->toBeFalse();

    // ...and the directive and macro are still available once they are.
    expect(app('blade.compiler')->getCustomDirectives())->toHaveKey('qrcode');
    expect(app(ResponseFactory::class)->hasMacro('qrcode'))->toBeTrue();
});
