<?php

declare(strict_types=1);

use Dunn\QrCode\Builder;
use Dunn\QrCode\EccLevel;
use Dunn\QrCode\Laravel\QrCodeFactory;

it('binds the factory as a singleton at "qrcode"', function (): void {
    $a = app('qrcode');
    $b = app('qrcode');

    expect($a)->toBeInstanceOf(QrCodeFactory::class);
    expect($a)->toBe($b);
});

it('also resolves via the QrCodeFactory class alias', function (): void {
    expect(app(QrCodeFactory::class))->toBeInstanceOf(QrCodeFactory::class);
});

it('returns a Builder pre-configured with the default ECC from config', function (): void {
    config(['qrcode.ecc' => EccLevel::Quartile]);
    /** @var QrCodeFactory $factory */
    $factory = app('qrcode');

    $builder = $factory->create('HELLO WORLD');
    $qr = $builder->build();

    expect($builder)->toBeInstanceOf(Builder::class);
    expect($qr->eccLevel)->toBe(EccLevel::Quartile);
});

it('renders an SVG via the default renderer', function (): void {
    /** @var QrCodeFactory $factory */
    $factory = app('qrcode');

    $svg = $factory->svg('HELLO WORLD');

    expect($svg)->toStartWith('<svg ');
    expect($svg)->toEndWith('</svg>');
});
