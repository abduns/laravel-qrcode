<?php

declare(strict_types=1);

use Dunn\QrCode\Builder;
use Dunn\QrCode\EccLevel;
use Dunn\QrCode\Laravel\Facades\QrCode as QrCodeFacade;
use Dunn\QrCode\Laravel\QrCodeFactory;
use Dunn\QrCode\Payload\Event;
use Dunn\QrCode\Payload\VCard;
use Dunn\QrCode\Payload\WifiAuth;
use Dunn\QrCode\QrCode;

beforeEach(function (): void {
    config(['qrcode.ecc' => EccLevel::Quartile]);
});

function laravelQrcodeFactory(): QrCodeFactory
{
    /** @var QrCodeFactory $factory */
    $factory = app('qrcode');

    return $factory;
}

/**
 * Build the core equivalent at the ECC level the tests configure, so a
 * factory that drops or reorders an argument produces a different symbol.
 */
function laravelQrcodeCore(Builder $builder): QrCode
{
    return $builder->errorCorrection(EccLevel::Quartile)->build();
}

it('url() returns a Builder pre-applying the configured ECC', function (): void {
    $builder = laravelQrcodeFactory()->url('https://example.com');

    expect($builder)->toBeInstanceOf(Builder::class);
    expect($builder->build()->eccLevel)->toBe(EccLevel::Quartile);
    expect($builder->build())->toEqual(laravelQrcodeCore(QrCode::url('https://example.com')));
});

it('text() matches the core payload', function (): void {
    $qr = laravelQrcodeFactory()->text('hello')->build();

    expect($qr->eccLevel)->toBe(EccLevel::Quartile);
    expect($qr)->toEqual(laravelQrcodeCore(QrCode::text('hello')));
});

it('phone() matches the core payload', function (): void {
    expect(laravelQrcodeFactory()->phone('+14155550123')->build())
        ->toEqual(laravelQrcodeCore(QrCode::phone('+14155550123')))
        ->not->toEqual(laravelQrcodeCore(QrCode::phone('+14155550124')));
});

it('sms() passes the number, body and URI flag through', function (): void {
    $qr = laravelQrcodeFactory()->sms('+14155550123', 'hi', true)->build();

    expect($qr)->toEqual(laravelQrcodeCore(QrCode::sms('+14155550123', 'hi', true)));
    expect($qr)->not->toEqual(laravelQrcodeCore(QrCode::sms('+14155550123', 'hi')));
    expect($qr)->not->toEqual(laravelQrcodeCore(QrCode::sms('+14155550123', null, true)));
});

it('email() passes subject, body, cc and bcc through', function (): void {
    $qr = laravelQrcodeFactory()->email('a@b.com', 'hi', 'body', ['c@b.com'], ['d@b.com'])->build();

    expect($qr)->toEqual(laravelQrcodeCore(QrCode::email('a@b.com', 'hi', 'body', ['c@b.com'], ['d@b.com'])));
    expect($qr)->not->toEqual(laravelQrcodeCore(QrCode::email('a@b.com', null, 'body', ['c@b.com'], ['d@b.com'])));
    expect($qr)->not->toEqual(laravelQrcodeCore(QrCode::email('a@b.com', 'hi', null, ['c@b.com'], ['d@b.com'])));
    expect($qr)->not->toEqual(laravelQrcodeCore(QrCode::email('a@b.com', 'hi', 'body', [], ['d@b.com'])));
    expect($qr)->not->toEqual(laravelQrcodeCore(QrCode::email('a@b.com', 'hi', 'body', ['c@b.com'])));
});

it('geo() passes the coordinates and label through', function (): void {
    $qr = laravelQrcodeFactory()->geo(37.7749, -122.4194, label: 'SF')->build();

    expect($qr)->toEqual(laravelQrcodeCore(QrCode::geo(37.7749, -122.4194, 'SF')));
    expect($qr)->not->toEqual(laravelQrcodeCore(QrCode::geo(37.775, -122.4194, 'SF')));
    expect($qr)->not->toEqual(laravelQrcodeCore(QrCode::geo(37.7749, 122.4194, 'SF')));
    expect($qr)->not->toEqual(laravelQrcodeCore(QrCode::geo(37.7749, -122.4194)));
});

it('wifi() passes the password, auth type and hidden flag through', function (): void {
    $qr = laravelQrcodeFactory()->wifi('MyNet', 'secret', WifiAuth::WEP, true)->build();

    expect($qr)->toEqual(laravelQrcodeCore(QrCode::wifi('MyNet', 'secret', WifiAuth::WEP, true)));
    expect($qr)->not->toEqual(laravelQrcodeCore(QrCode::wifi('MyNet', null, WifiAuth::WEP, true)));
    expect($qr)->not->toEqual(laravelQrcodeCore(QrCode::wifi('MyNet', 'secret', WifiAuth::WPA, true)));
    expect($qr)->not->toEqual(laravelQrcodeCore(QrCode::wifi('MyNet', 'secret', WifiAuth::WEP)));
});

it('wifi() defaults to WPA and not hidden', function (): void {
    expect(laravelQrcodeFactory()->wifi('MyNet', 'secret')->build())
        ->toEqual(laravelQrcodeCore(QrCode::wifi('MyNet', 'secret', WifiAuth::WPA, false)));
});

it('vCard() matches the core payload', function (): void {
    $card = VCard::make('John Doe')->withOrg('Acme');

    expect(laravelQrcodeFactory()->vCard($card)->build())->toEqual(laravelQrcodeCore(QrCode::vCard($card)));
});

it('event() encodes the event payload', function (): void {
    $event = Event::make('Launch')
        ->withUid('launch@example.com')
        ->from(new \DateTimeImmutable('2026-06-01 18:00', new \DateTimeZone('UTC')))
        ->to(new \DateTimeImmutable('2026-06-01 22:00', new \DateTimeZone('UTC')));

    $builder = laravelQrcodeFactory()->event($event);

    // DTSTAMP is the generation time, so compare the encoded text without it.
    $withoutStamp = static fn (string $ics): string => (string) preg_replace('/^DTSTAMP:.*$/m', 'DTSTAMP:', $ics);
    $data = (new \ReflectionProperty(Builder::class, 'data'))->getValue($builder);

    expect($data)->toBeString();
    expect($withoutStamp((string) $data))->toBe($withoutStamp((string) $event));
    expect($builder->build()->eccLevel)->toBe(EccLevel::Quartile);
});

it('create() accepts a Stringable payload directly', function (): void {
    $card = VCard::make('Jane');

    expect(laravelQrcodeFactory()->create($card)->build())->toEqual(laravelQrcodeCore(QrCode::create((string) $card)));
});

it('svg() accepts a Stringable payload', function (): void {
    $card = VCard::make('Jane');
    $svg = laravelQrcodeFactory()->svg($card);
    expect($svg)->toStartWith('<svg ')->toEndWith('</svg>');
    expect($svg)->toBe(laravelQrcodeFactory()->svg((string) $card));
});

it('exposes the new factories through the Facade', function (): void {
    expect(QrCodeFacade::url('https://example.com')->build())
        ->toEqual(laravelQrcodeCore(QrCode::url('https://example.com')));
});
