<?php

declare(strict_types=1);

use Dunn\QrCode\Laravel\Facades\QrCode;
use Dunn\QrCode\Laravel\QrCodeFactory;
use Dunn\QrCode\Renderer\Console\ConsoleRenderer;
use Dunn\QrCode\Renderer\Png\GdPngRenderer;
use Dunn\QrCode\Renderer\Svg\SvgRenderer;
use Dunn\QrCode\Style\ModuleShape\DotModule;
use Illuminate\Support\Facades\Blade;

it('svg() renders SVG even when config(qrcode.renderer) is png or console', function (string $kind): void {
    config(['qrcode.renderer' => $kind]);

    expect(QrCode::svg('HELLO'))->toStartWith('<svg ')->toEndWith('</svg>');
})->with(['png', 'console']);

it('@qrcode renders SVG even when config(qrcode.renderer) is png or console', function (string $kind): void {
    config(['qrcode.renderer' => $kind]);

    expect(Blade::render("@qrcode('HELLO')"))->toStartWith('<svg ')->toContain('</svg>');
})->with(['png', 'console']);

it('render() still follows config(qrcode.renderer)', function (): void {
    config(['qrcode.renderer' => 'console']);

    expect(QrCode::render('HELLO'))->toContain('██')->not->toContain('<svg');
});

it('svg() ignores a pinned non-SVG renderer', function (): void {
    /** @var QrCodeFactory $factory */
    $factory = app('qrcode');

    expect($factory->withRenderer(new ConsoleRenderer())->svg('HELLO'))->toStartWith('<svg ');

    if (extension_loaded('gd')) {
        expect($factory->withRenderer(new GdPngRenderer())->svg('HELLO'))->toStartWith('<svg ');
    }
});

it('svg() keeps using a pinned SVG renderer', function (): void {
    config(['qrcode.renderer' => 'console']);

    expect(QrCode::withRenderer(new SvgRenderer(moduleShape: new DotModule()))->svg('HELLO'))
        ->toStartWith('<svg ')
        ->toContain('a.5 .5');
});

it('svg() uses an explicitly passed renderer as is', function (): void {
    expect(QrCode::svg('HELLO', new ConsoleRenderer()))->toContain('██')->not->toContain('<svg');
});

it('@qrcode accepts a renderer as its second argument', function (): void {
    $output = Blade::render('@qrcode($data, $renderer)', [
        'data' => 'HELLO',
        'renderer' => new SvgRenderer(moduleShape: new DotModule()),
    ]);

    expect($output)->toStartWith('<svg ')->toContain('a.5 .5');
});
