<?php

declare(strict_types=1);

use Dunn\QrCode\Laravel\Facades\QrCode;
use Dunn\QrCode\Laravel\QrCodeFactory;
use Dunn\QrCode\Renderer\Console\ConsoleRenderer;
use Dunn\QrCode\Renderer\Svg\SvgRenderer;
use Dunn\QrCode\Style\EyeStyle\CircleEyeOuter;
use Dunn\QrCode\Style\ModuleShape\DotModule;
use Illuminate\Support\Facades\Route;

it('factory::svg() accepts a custom renderer for one-off styled output', function (): void {
    /** @var QrCodeFactory $factory */
    $factory = app('qrcode');

    $styled = new SvgRenderer(
        moduleShape: new DotModule(),
        eyeOuter: new CircleEyeOuter(),
        dotColor: '#264653',
    );

    $svg = $factory->svg('HELLO WORLD', $styled);

    expect($svg)->toContain('shape-rendering="geometricPrecision"');
    expect($svg)->toContain('a.5 .5');         // DotModule arcs
    expect($svg)->toContain('a3.5 3.5');       // CircleEyeOuter arc
    expect($svg)->toContain('fill="#264653"'); // dotColor
});

it('factory::withRenderer() clones the factory with a pinned default', function (): void {
    /** @var QrCodeFactory $factory */
    $factory = app('qrcode');

    $styled = $factory->withRenderer(new SvgRenderer(moduleShape: new DotModule()));

    expect($styled)->not->toBe($factory);
    expect($styled->renderer())->toBeInstanceOf(SvgRenderer::class);

    // Now svg() without an override picks up the pinned renderer.
    expect($styled->svg('HELLO WORLD'))->toContain('a.5 .5');
});

it('facade exposes withRenderer() via __callStatic', function (): void {
    $styled = QrCode::withRenderer(new SvgRenderer(moduleShape: new DotModule()));

    expect($styled)->toBeInstanceOf(QrCodeFactory::class);
    expect($styled->svg('HI'))->toContain('a.5 .5');
});

it('response macro accepts a renderer override and reports its mime type', function (): void {
    $renderer = new ConsoleRenderer(margin: 1);

    Route::get('/qr-console/{data}', fn (string $data) => response()->qrcode($data, 200, $renderer));

    $response = $this->get('/qr-console/HELLO');

    $response->assertOk();
    // Laravel auto-appends ; charset=utf-8 to text/plain responses.
    expect($response->headers->get('Content-Type'))->toStartWith('text/plain');
    expect($response->getContent())->toContain('██');
});
