<?php

declare(strict_types=1);

use Dunn\QrCode\Payload\VCard;
use Illuminate\Support\Facades\Route;

it('registers a response()->qrcode() macro', function (): void {
    Route::get('/qr/{data}', fn (string $data) => response()->qrcode($data));

    $response = $this->get('/qr/HELLO%20WORLD');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/svg+xml');
    expect($response->getContent())->toStartWith('<svg ');
    expect($response->getContent())->toContain('</svg>');
});

it('honours a custom status code passed to the macro', function (): void {
    Route::get('/qr/teapot', fn () => response()->qrcode('TEAPOT', 418));

    $response = $this->get('/qr/teapot');

    expect($response->getStatusCode())->toBe(418);
});

it('accepts a Stringable payload', function (): void {
    Route::get('/vcard', fn () => response()->qrcode(VCard::make('Jane')));

    $response = $this->get('/vcard');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/svg+xml');
    expect($response->getContent())
        ->toStartWith('<svg ')
        ->toContain('</svg>');
});

it('sets an ETag on every qrcode response', function (): void {
    Route::get('/qr-etag', fn () => response()->qrcode('HELLO'));

    $response = $this->get('/qr-etag');

    $response->assertOk();
    $etag = $response->headers->get('ETag');
    expect($etag)->toBeString()->not->toBe('');
    expect($etag)->toBe('"'.hash('sha256', (string) $response->getContent()).'"');
});

it('sets inline Content-Disposition when filename is given', function (): void {
    Route::get('/qr-file', fn () => response()->qrcode('HELLO', filename: 'ticket.svg'));

    $response = $this->get('/qr-file');

    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toBe('inline; filename="ticket.svg"');
});

it('appends an extension when filename has none', function (): void {
    Route::get('/qr-file-ext', fn () => response()->qrcode('HELLO', filename: 'ticket'));

    $response = $this->get('/qr-file-ext');

    expect($response->headers->get('Content-Disposition'))->toBe('inline; filename="ticket.svg"');
});

it('uses attachment disposition when download is true', function (): void {
    Route::get('/qr-dl', fn () => response()->qrcode('HELLO', download: true));

    $response = $this->get('/qr-dl');

    expect($response->headers->get('Content-Disposition'))->toBe('attachment; filename="qrcode.svg"');
});

it('sets public Cache-Control when maxAge is given', function (): void {
    Route::get('/qr-cache', fn () => response()->qrcode('HELLO', maxAge: 3600));

    $response = $this->get('/qr-cache');

    $cache = (string) $response->headers->get('Cache-Control');
    expect($cache)->toContain('public')->toContain('max-age=3600');
});

it('does not set Content-Disposition or Cache-Control by default', function (): void {
    Route::get('/qr-plain', fn () => response()->qrcode('HELLO'));

    $response = $this->get('/qr-plain');

    expect($response->headers->get('Content-Disposition'))->toBeNull();
    $cache = (string) $response->headers->get('Cache-Control');
    expect($cache)->not->toContain('max-age=');
});

it('strips quotes and CR/LF from the download filename', function (): void {
    Route::get('/qr-safe', fn () => response()->qrcode('HELLO', filename: "tic\"ket\r\n.svg"));

    $response = $this->get('/qr-safe');

    expect($response->headers->get('Content-Disposition'))->toBe('inline; filename="ticket.svg"');
});

it('factory toResponse() matches the response macro', function (): void {
    $viaFactory = app('qrcode')->toResponse('HELLO', filename: 'ticket.svg', maxAge: 60);
    $viaMacro = response()->qrcode('HELLO', filename: 'ticket.svg', maxAge: 60);

    expect($viaMacro->getContent())->toBe($viaFactory->getContent());
    expect($viaMacro->headers->get('Content-Disposition'))->toBe($viaFactory->headers->get('Content-Disposition'));
    expect($viaMacro->headers->get('ETag'))->toBe($viaFactory->headers->get('ETag'));
});
