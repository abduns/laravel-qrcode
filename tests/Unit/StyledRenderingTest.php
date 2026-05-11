<?php

declare(strict_types=1);

use Dunn\QrCode\Laravel\Facades\QrCode;
use Dunn\QrCode\Renderer\Svg\SvgRenderer;
use Dunn\QrCode\Style\Color;
use Dunn\QrCode\Style\EyeStyle\RoundedEyeInner;
use Dunn\QrCode\Style\EyeStyle\RoundedEyeOuter;
use Dunn\QrCode\Style\Gradient\GradientStop;
use Dunn\QrCode\Style\Gradient\LinearGradient;
use Dunn\QrCode\Style\Gradient\RadialGradient;
use Dunn\QrCode\Style\Logo;
use Dunn\QrCode\Style\ModuleShape\RoundedModule;

it('passes LinearGradient through to the SVG output via the bridge', function (): void {
    $styled = new SvgRenderer(
        dotColor: new LinearGradient([
            new GradientStop(0.0, Color::hex('#264653')),
            new GradientStop(1.0, Color::hex('#2a9d8f')),
        ]),
    );

    $svg = QrCode::svg('https://example.com', $styled);

    expect($svg)
        ->toContain('<linearGradient ')
        ->toContain('fill="url(#');
});

it('passes RadialGradient through to the SVG output via the bridge', function (): void {
    $styled = new SvgRenderer(
        markerInnerColor: new RadialGradient([
            new GradientStop(0.0, Color::hex('#f4a261')),
            new GradientStop(1.0, Color::hex('#e76f51')),
        ]),
    );

    $svg = QrCode::svg('https://example.com', $styled);

    expect($svg)
        ->toContain('<radialGradient ')
        ->toContain('fill="url(#');
});

it('passes RoundedModule + RoundedEye styles through to the SVG output', function (): void {
    $styled = new SvgRenderer(
        moduleShape: new RoundedModule(),
        eyeOuter: new RoundedEyeOuter(),
        eyeInner: new RoundedEyeInner(),
    );

    $svg = QrCode::svg('https://example.com', $styled);

    // RoundedModule emits a0.5 0.5 arcs for isolated/corner modules.
    expect($svg)
        ->toContain('a0.5 0.5')
        ->toContain('shape-rendering="geometricPrecision"');
});

it('embeds a Logo as a data-URI <image> element via the bridge', function (): void {
    // 1x1 transparent PNG.
    $tinyPng = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='
    );
    $styled = new SvgRenderer(
        logo: new Logo($tinyPng, 'image/png', sizeRatio: 0.1),
    );

    $svg = QrCode::svg('https://example.com', $styled);

    expect($svg)
        ->toContain('<image ')
        ->toContain('href="data:image/png;base64,');
});

it('withRenderer() with a styled renderer pins the styling app-wide', function (): void {
    $styled = (new \Dunn\QrCode\Laravel\QrCodeFactory())->withRenderer(
        new SvgRenderer(
            moduleShape: new RoundedModule(),
            dotColor: new LinearGradient([
                new GradientStop(0.0, Color::hex('#264653')),
                new GradientStop(1.0, Color::hex('#2a9d8f')),
            ]),
        ),
    );

    $svg = $styled->svg('https://example.com');

    expect($svg)
        ->toContain('<linearGradient ')
        ->toContain('a0.5 0.5');
});
