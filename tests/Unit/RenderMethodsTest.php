<?php

declare(strict_types=1);

use Dunn\QrCode\Laravel\Facades\QrCode;
use Dunn\QrCode\Laravel\QrCodeFactory;
use Dunn\QrCode\Renderer\Console\ConsoleRenderer;
use Dunn\QrCode\Renderer\Png\GdPngRenderer;
use Dunn\QrCode\Renderer\Svg\SvgRenderer;

function laravelQrcodeFactoryForRender(): QrCodeFactory
{
    /** @var QrCodeFactory $factory */
    $factory = app('qrcode');

    return $factory;
}

it('render() uses the default SvgRenderer when nothing is overridden', function (): void {
    $out = laravelQrcodeFactoryForRender()->render('HELLO WORLD');

    expect($out)->toStartWith('<svg ')->toContain('</svg>');
});

it('render() honours an override renderer regardless of its type', function (): void {
    $out = laravelQrcodeFactoryForRender()->render('HELLO WORLD', new ConsoleRenderer(margin: 1));

    expect($out)->toContain('██');
});

it('png() returns raw PNG bytes (starts with the PNG magic number)', function (): void {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('ext-gd not loaded.');
    }

    $bytes = laravelQrcodeFactoryForRender()->png('HELLO WORLD');

    expect(substr($bytes, 0, 8))->toBe("\x89PNG\r\n\x1a\n");
});

it('png() ignores config(qrcode.renderer) and always forces PNG', function (): void {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('ext-gd not loaded.');
    }
    config(['qrcode.renderer' => 'console']);

    $bytes = laravelQrcodeFactoryForRender()->png('HELLO');

    expect(substr($bytes, 0, 8))->toBe("\x89PNG\r\n\x1a\n");
});

it('console() returns the unicode block representation', function (): void {
    $out = laravelQrcodeFactoryForRender()->console('HELLO WORLD');

    expect($out)->toContain('██');
});

it('console() ignores config(qrcode.renderer) and always forces console output', function (): void {
    config(['qrcode.renderer' => 'svg']);

    $out = laravelQrcodeFactoryForRender()->console('HELLO');

    expect($out)->toContain('██')->not->toContain('<svg');
});

it('renderer() returns a GdPngRenderer when config(qrcode.renderer) = png', function (): void {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('ext-gd not loaded.');
    }
    config(['qrcode.renderer' => 'png']);

    expect(app('qrcode')->renderer())->toBeInstanceOf(GdPngRenderer::class);
});

it('renderer() returns a ConsoleRenderer when config(qrcode.renderer) = console', function (): void {
    config(['qrcode.renderer' => 'console']);

    expect(app('qrcode')->renderer())->toBeInstanceOf(ConsoleRenderer::class);
});

it('renderer() returns an SvgRenderer by default', function (): void {
    expect(app('qrcode')->renderer())->toBeInstanceOf(SvgRenderer::class);
});

it('renderer() respects a pinned renderer over config(qrcode.renderer)', function (): void {
    config(['qrcode.renderer' => 'console']);

    $pinned = new SvgRenderer();
    expect(app('qrcode')->withRenderer($pinned)->renderer())->toBe($pinned);
});

it('config(qrcode.renderer) = png makes response()->qrcode() return image/png', function (): void {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('ext-gd not loaded.');
    }
    config(['qrcode.renderer' => 'png']);

    \Illuminate\Support\Facades\Route::get('/qr-config-png', fn () => response()->qrcode('HELLO'));

    $response = $this->get('/qr-config-png');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('image/png');
    expect(substr((string) $response->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
});

it('exposes render(), png(), and console() through the Facade', function (): void {
    expect(QrCode::render('HELLO'))->toStartWith('<svg ');
    expect(QrCode::console('HELLO'))->toContain('██');

    if (extension_loaded('gd')) {
        expect(substr(QrCode::png('HELLO'), 0, 8))->toBe("\x89PNG\r\n\x1a\n");
    }
});
