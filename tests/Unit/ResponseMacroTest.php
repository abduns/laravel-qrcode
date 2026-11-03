<?php

declare(strict_types=1);

use Dunn\QrCode\Payload\VCard;
use Dunn\QrCode\Renderer\Console\ConsoleRenderer;
use Dunn\QrCode\Renderer\Png\GdPngRenderer;
use Illuminate\Http\Request;
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

it('derives the download extension from the renderer MIME type', function (): void {
    Route::get('/qr-dl-txt', fn () => response()->qrcode('HELLO', 200, new ConsoleRenderer(), download: true));
    expect($this->get('/qr-dl-txt')->headers->get('Content-Disposition'))->toBe('attachment; filename="qrcode.txt"');

    if (extension_loaded('gd')) {
        Route::get('/qr-dl-png', fn () => response()->qrcode('HELLO', 200, new GdPngRenderer(), download: true));
        expect($this->get('/qr-dl-png')->headers->get('Content-Disposition'))->toBe('attachment; filename="qrcode.png"');
    }
});

it('sanitizes filenames for Content-Disposition', function (string $filename, string $expected): void {
    $response = app('qrcode')->toResponse('HELLO', filename: $filename);

    expect($response->headers->get('Content-Disposition'))->toBe('inline; filename="'.$expected.'"');
})->with([
    'path traversal' => ['../../etc/passwd', 'etcpasswd.svg'],
    'backslash path' => ['..\\..\\ticket.svg', 'ticket.svg'],
    'dot-dot' => ['..', 'qrcode.svg'],
    'dot' => ['.', 'qrcode.svg'],
    'empty' => ['', 'qrcode.svg'],
    'extension only' => ['.svg', 'qrcode.svg'],
    'dotted name' => ['john.doe', 'john.doe.svg'],
    'domain name' => ['example.com', 'example.com.svg'],
    'trailing dot' => ['ticket.', 'ticket.svg'],
    'upper-case extension' => ['ticket.SVG', 'ticket.SVG'],
    'mismatched extension' => ['ticket.png', 'ticket.png.svg'],
    'tab and NUL' => ["tic\tket\0.svg", 'ticket.svg'],
]);

it('adds an RFC 6266 filename* parameter for non-ASCII filenames', function (): void {
    $response = app('qrcode')->toResponse('HELLO', filename: 'bilét', download: true);

    expect($response->headers->get('Content-Disposition'))
        ->toBe("attachment; filename=\"bilet.svg\"; filename*=UTF-8''bil%C3%A9t.svg");
});

it('falls back to an ASCII filename when nothing ASCII is left', function (): void {
    $response = app('qrcode')->toResponse('HELLO', filename: '票.svg');

    expect($response->headers->get('Content-Disposition'))
        ->toStartWith('inline; filename="')
        ->toEndWith("; filename*=UTF-8''%E7%A5%A8.svg");
    expect($response->headers->get('Content-Disposition'))->toMatch('/^inline; filename="[\x20-\x7E]+\.svg";/');
});

it('answers a matching If-None-Match with 304 Not Modified', function (): void {
    Route::get('/qr-304', fn () => response()->qrcode('HELLO'));

    $etag = $this->get('/qr-304')->headers->get('ETag');
    expect($etag)->toBeString();

    $response = $this->get('/qr-304', ['If-None-Match' => (string) $etag]);

    expect($response->getStatusCode())->toBe(304);
    expect($response->getContent())->toBe('');
});

it('answers a stale If-None-Match with the full body', function (): void {
    Route::get('/qr-stale', fn () => response()->qrcode('HELLO'));

    $response = $this->get('/qr-stale', ['If-None-Match' => '"stale"']);

    $response->assertOk();
    expect($response->getContent())->toStartWith('<svg ');
});

it('does not turn a custom status into 304', function (): void {
    Route::get('/qr-418', fn () => response()->qrcode('HELLO', 418));

    $etag = (string) $this->get('/qr-418')->headers->get('ETag');
    $response = $this->get('/qr-418', ['If-None-Match' => $etag]);

    expect($response->getStatusCode())->toBe(418);
    expect($response->getContent())->toStartWith('<svg ');
});

it('toResponse() only checks If-None-Match when given a request', function (): void {
    $etag = (string) app('qrcode')->toResponse('HELLO')->headers->get('ETag');
    $request = Request::create('/qr', 'GET', server: ['HTTP_IF_NONE_MATCH' => $etag]);

    expect(app('qrcode')->toResponse('HELLO')->getStatusCode())->toBe(200);
    expect(app('qrcode')->toResponse('HELLO', request: $request)->getStatusCode())->toBe(304);
});
