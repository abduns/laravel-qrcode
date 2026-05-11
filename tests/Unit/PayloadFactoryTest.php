<?php

declare(strict_types=1);

use Dunn\QrCode\Builder;
use Dunn\QrCode\EccLevel;
use Dunn\QrCode\Laravel\QrCodeFactory;
use Dunn\QrCode\Payload\Event;
use Dunn\QrCode\Payload\VCard;
use Dunn\QrCode\Payload\WifiAuth;

beforeEach(function (): void {
    config(['qrcode.ecc' => EccLevel::Quartile]);
});

function laravelQrcodeFactory(): QrCodeFactory
{
    /** @var QrCodeFactory $factory */
    $factory = app('qrcode');

    return $factory;
}

it('url() returns a Builder pre-applying the configured ECC', function (): void {
    $builder = laravelQrcodeFactory()->url('https://example.com');

    expect($builder)->toBeInstanceOf(Builder::class);
    expect($builder->build()->eccLevel)->toBe(EccLevel::Quartile);
});

it('text() builds end-to-end', function (): void {
    expect(laravelQrcodeFactory()->text('hello')->build()->eccLevel)
        ->toBe(EccLevel::Quartile);
});

it('phone() builds end-to-end', function (): void {
    expect(laravelQrcodeFactory()->phone('+14155550123')->build())->not->toBeNull();
});

it('sms() builds end-to-end', function (): void {
    expect(laravelQrcodeFactory()->sms('+14155550123', 'hi')->build())->not->toBeNull();
});

it('email() builds end-to-end', function (): void {
    $qr = laravelQrcodeFactory()->email('a@b.com', subject: 'hi', cc: ['c@b.com'])->build();
    expect($qr)->not->toBeNull();
});

it('geo() builds end-to-end', function (): void {
    expect(laravelQrcodeFactory()->geo(37.7749, -122.4194, label: 'SF')->build())->not->toBeNull();
});

it('wifi() builds end-to-end with WPA defaults', function (): void {
    $qr = laravelQrcodeFactory()->wifi('MyNet', 'secret', WifiAuth::WPA)->build();
    expect($qr)->not->toBeNull();
});

it('vCard() builds end-to-end', function (): void {
    $card = VCard::make('John Doe')->withOrg('Acme');
    expect(laravelQrcodeFactory()->vCard($card)->build())->not->toBeNull();
});

it('event() builds end-to-end', function (): void {
    $event = Event::make('Launch')
        ->from(new \DateTimeImmutable('2026-06-01 18:00', new \DateTimeZone('UTC')))
        ->to(new \DateTimeImmutable('2026-06-01 22:00', new \DateTimeZone('UTC')));

    expect(laravelQrcodeFactory()->event($event)->build())->not->toBeNull();
});

it('create() now accepts a Stringable payload directly', function (): void {
    $card = VCard::make('Jane');
    expect(laravelQrcodeFactory()->create($card)->build())->not->toBeNull();
});

it('svg() accepts a Stringable payload', function (): void {
    $card = VCard::make('Jane');
    $svg = laravelQrcodeFactory()->svg($card);
    expect($svg)->toStartWith('<svg ')->toEndWith('</svg>');
});

it('exposes the new factories through the Facade', function (): void {
    $svg = \Dunn\QrCode\Laravel\Facades\QrCode::url('https://example.com')->build();
    expect($svg)->not->toBeNull();
});
