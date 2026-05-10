<?php

declare(strict_types=1);

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
