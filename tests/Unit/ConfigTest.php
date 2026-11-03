<?php

declare(strict_types=1);

use Dunn\QrCode\EccLevel;
use Dunn\QrCode\Exception\InvalidConfigurationException;
use Dunn\QrCode\Laravel\Facades\QrCode;
use Dunn\QrCode\Laravel\QrCodeFactory;
use Dunn\QrCode\Renderer\Console\ConsoleRenderer;
use Dunn\QrCode\Renderer\Png\GdPngRenderer;
use Dunn\QrCode\Renderer\Svg\SvgRenderer;

it('applies size, margin, foreground and background to the SVG output', function (): void {
    config([
        'qrcode.size' => 120,
        'qrcode.margin' => 2,
        'qrcode.foreground' => '#123456',
        'qrcode.background' => '#abcdef',
    ]);

    $svg = QrCode::svg('HI');

    // 'HI' encodes as a version 1 symbol: 21 modules + 2 * margin.
    expect($svg)
        ->toContain('viewBox="0 0 25 25"')
        ->toContain('width="120" height="120"')
        ->toContain('<rect width="100%" height="100%" fill="#abcdef"/>')
        ->toContain('fill="#123456"');
});

it('applies size, margin and colours to the PNG output', function (): void {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('ext-gd not loaded.');
    }
    // 210px over 21 modules with no margin gives a whole number of pixels per module.
    config(['qrcode.size' => 210, 'qrcode.margin' => 0, 'qrcode.foreground' => '#ff0000', 'qrcode.background' => '#00ff00']);

    $png = QrCode::png('HI');
    $size = getimagesizefromstring($png);
    expect($size)->toBeArray();
    expect([$size[0], $size[1]])->toBe([210, 210]);

    $image = imagecreatefromstring($png);
    expect($image)->not->toBeFalse();
    // With margin 0 the top-left pixel is the dark corner of a finder pattern.
    $rgb = imagecolorsforindex($image, imagecolorat($image, 0, 0));
    expect([$rgb['red'], $rgb['green'], $rgb['blue']])->toBe([255, 0, 0]);
    // Module (1, 1) is the light ring inside that finder pattern.
    $rgb = imagecolorsforindex($image, imagecolorat($image, 15, 15));
    expect([$rgb['red'], $rgb['green'], $rgb['blue']])->toBe([0, 255, 0]);
});

it('applies the margin to console output', function (): void {
    config(['qrcode.margin' => 0]);
    $lines = explode("\n", rtrim(QrCode::console('HI'), "\n"));

    expect($lines)->toHaveCount(21);
});

it('reads config changes made after the factory was first resolved', function (): void {
    /** @var QrCodeFactory $factory */
    $factory = app('qrcode');
    expect($factory->svg('HI'))->toContain('fill="#000000"');

    config(['qrcode.foreground' => '#123456', 'qrcode.renderer' => 'console', 'qrcode.ecc' => EccLevel::High]);

    expect(app('qrcode'))->toBe($factory);
    expect($factory->svg('HI'))->toContain('fill="#123456"');
    expect($factory->renderer())->toBeInstanceOf(ConsoleRenderer::class);
    expect($factory->create('HI')->build()->eccLevel)->toBe(EccLevel::High);
    expect(QrCode::render('HI'))->toContain('██');
});

it('carries live config into factories returned by withRenderer()', function (): void {
    $pinned = QrCode::withRenderer(new SvgRenderer());

    config(['qrcode.ecc' => EccLevel::Low]);

    expect($pinned->create('HI')->build()->eccLevel)->toBe(EccLevel::Low);
});

it('accepts string ECC levels from env()', function (mixed $value, EccLevel $expected): void {
    config(['qrcode.ecc' => $value]);

    expect(QrCode::create('HI')->build()->eccLevel)->toBe($expected);
})->with([
    'backing value' => ['H', EccLevel::High],
    'lower-case backing value' => ['q', EccLevel::Quartile],
    'case name' => ['Low', EccLevel::Low],
    'lower-case case name' => ['high', EccLevel::High],
    'padded' => [' quartile ', EccLevel::Quartile],
    'enum' => [EccLevel::Quartile, EccLevel::Quartile],
]);

it('falls back to the core default ECC when ecc is unset or empty', function (mixed $value): void {
    config(['qrcode.ecc' => $value]);

    expect(QrCode::create('HI')->build()->eccLevel)->toBe(EccLevel::Medium);
})->with([null, '']);

it('rejects an unknown ECC level instead of silently ignoring it', function (mixed $value): void {
    config(['qrcode.ecc' => $value]);

    expect(fn () => QrCode::create('HI'))->toThrow(InvalidConfigurationException::class, 'qrcode.ecc');
})->with(['X', 'highest', 3, true]);

it('accepts renderer names in any case', function (): void {
    config(['qrcode.renderer' => ' Console ']);
    expect(QrCode::renderer())->toBeInstanceOf(ConsoleRenderer::class);

    config(['qrcode.renderer' => 'SVG']);
    expect(QrCode::renderer())->toBeInstanceOf(SvgRenderer::class);

    if (extension_loaded('gd')) {
        config(['qrcode.renderer' => 'PNG']);
        expect(QrCode::renderer())->toBeInstanceOf(GdPngRenderer::class);
    }
});

it('treats an unset or empty renderer as svg', function (mixed $value): void {
    config(['qrcode.renderer' => $value]);

    expect(QrCode::renderer())->toBeInstanceOf(SvgRenderer::class);
})->with([null, '']);

it('rejects an unknown renderer instead of silently falling back to SVG', function (mixed $value): void {
    config(['qrcode.renderer' => $value]);

    expect(fn () => QrCode::render('HI'))->toThrow(InvalidConfigurationException::class, 'qrcode.renderer');
})->with(['gd', 'jpeg', 1]);

it('reports invalid size, margin and colours as InvalidConfigurationException', function (string $key, mixed $value): void {
    config(["qrcode.{$key}" => $value]);

    expect(fn () => QrCode::svg('HI'))->toThrow(InvalidConfigurationException::class);
})->with([
    'zero size' => ['size', 0],
    'negative size' => ['size', '-5'],
    'non-numeric size' => ['size', 'big'],
    'negative margin' => ['margin', -1],
    'fractional margin' => ['margin', '1.5'],
    'colour name' => ['foreground', 'red'],
    'non-string colour' => ['background', 123],
]);

it('keeps the core exception as the previous exception', function (): void {
    config(['qrcode.size' => 0]);

    try {
        QrCode::svg('HI');
        $this->fail('Expected an InvalidConfigurationException.');
    } catch (InvalidConfigurationException $e) {
        expect($e->getPrevious())->toBeInstanceOf(\InvalidArgumentException::class);
    }
});

it('accepts numeric strings for size and margin and treats empty strings as unset', function (): void {
    config(['qrcode.size' => '120', 'qrcode.margin' => '']);

    expect(QrCode::svg('HI'))
        ->toContain('width="120" height="120"')
        ->toContain('viewBox="0 0 29 29"');
});

it('still accepts a plain config array in the constructor', function (): void {
    $factory = new QrCodeFactory(['ecc' => 'H', 'renderer' => 'console', 'margin' => 0]);

    expect($factory->create('HI')->build()->eccLevel)->toBe(EccLevel::High);
    expect($factory->render('HI'))->toContain('██');
});
