<?php

declare(strict_types=1);

use Dunn\QrCode\Laravel\Facades\QrCode;
use Dunn\QrCode\Laravel\QrCodeFactory;
use Dunn\QrCode\Laravel\QrCodePayload;
use Dunn\QrCode\Renderer\Console\ConsoleRenderer;
use Dunn\QrCode\Renderer\Png\GdPngRenderer;
use Illuminate\Support\Facades\Route;

function laravelQrcodeFactoryForJs(): QrCodeFactory
{
    /** @var QrCodeFactory $factory */
    $factory = app('qrcode');

    return $factory;
}

it('dataUri() returns a base64 SVG data URI by default', function (): void {
    $uri = laravelQrcodeFactoryForJs()->dataUri('HELLO WORLD');

    expect($uri)->toStartWith('data:image/svg+xml;base64,');

    $encoded = substr($uri, strlen('data:image/svg+xml;base64,'));
    $svg = base64_decode($encoded, true);

    expect($svg)->toBeString()
        ->toStartWith('<svg ')
        ->toContain('</svg>');
});

it('payload() exposes mimeType, dataUri, and svg for JSON clients', function (): void {
    $payload = laravelQrcodeFactoryForJs()->payload('HELLO WORLD');

    expect($payload)->toBeInstanceOf(QrCodePayload::class);
    expect($payload->mimeType)->toBe('image/svg+xml');
    expect($payload->dataUri)->toStartWith('data:image/svg+xml;base64,');
    expect($payload->svg)->toStartWith('<svg ')->toContain('</svg>');
});

it('toArray() matches the JSON-serializable payload shape', function (): void {
    $payload = laravelQrcodeFactoryForJs()->payload('HELLO WORLD');
    $array = laravelQrcodeFactoryForJs()->toArray('HELLO WORLD');

    expect($array)->toBe($payload->toArray());
    expect($array)->toHaveKeys(['mimeType', 'dataUri', 'svg']);

    $decoded = json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    expect($decoded)->toBe($array);
    expect($decoded['mimeType'])->toBe('image/svg+xml');
    expect($decoded['dataUri'])->toStartWith('data:image/svg+xml;base64,');
    expect($decoded['svg'])->toStartWith('<svg ');
});

it('payload string-casts to the data URI', function (): void {
    $payload = laravelQrcodeFactoryForJs()->payload('HELLO');

    expect((string) $payload)->toBe($payload->dataUri);
});

it('payload() sets svg to null for PNG output', function (): void {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('ext-gd not loaded.');
    }

    $payload = laravelQrcodeFactoryForJs()->payload('HELLO', new GdPngRenderer());

    expect($payload->mimeType)->toBe('image/png');
    expect($payload->dataUri)->toStartWith('data:image/png;base64,');
    expect($payload->svg)->toBeNull();

    $encoded = substr($payload->dataUri, strlen('data:image/png;base64,'));
    $bytes = base64_decode($encoded, true);
    expect($bytes)->toBeString();
    expect(substr($bytes, 0, 8))->toBe("\x89PNG\r\n\x1a\n");
});

it('dataUri() honours a renderer override', function (): void {
    $uri = laravelQrcodeFactoryForJs()->dataUri('HELLO', new ConsoleRenderer(margin: 1));

    expect($uri)->toStartWith('data:text/plain;base64,');

    $encoded = substr($uri, strlen('data:text/plain;base64,'));
    $text = base64_decode($encoded, true);
    expect($text)->toBeString()->toContain('██');
});

it('exposes payload(), dataUri(), and toArray() through the Facade', function (): void {
    $payload = QrCode::payload('HELLO');

    expect($payload)->toBeInstanceOf(QrCodePayload::class);
    expect(QrCode::dataUri('HELLO'))->toBe($payload->dataUri);
    expect(QrCode::toArray('HELLO'))->toBe($payload->toArray());
});

it('returning payload() from a route yields JSON for SPA clients', function (): void {
    Route::get('/api/qr', fn () => QrCode::payload('HELLO WORLD'));

    $response = $this->getJson('/api/qr');

    $response->assertOk();
    $response->assertJsonStructure(['mimeType', 'dataUri', 'svg']);
    expect($response->json('mimeType'))->toBe('image/svg+xml');
    expect($response->json('dataUri'))->toStartWith('data:image/svg+xml;base64,');
    expect($response->json('svg'))->toStartWith('<svg ');
});
