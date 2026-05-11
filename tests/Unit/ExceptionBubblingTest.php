<?php

declare(strict_types=1);

use Dunn\QrCode\Exception\DataTooLongException;
use Dunn\QrCode\Exception\PayloadException;
use Dunn\QrCode\Laravel\Facades\QrCode;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

it('factory->svg() lets DataTooLongException bubble out of the bridge', function (): void {
    $tooLong = str_repeat('A', 5000);

    expect(fn () => QrCode::svg($tooLong))->toThrow(DataTooLongException::class);
});

it('response()->qrcode() lets DataTooLongException bubble out of the macro', function (): void {
    $this->withoutExceptionHandling();
    Route::get('/qr-overflow', fn () => response()->qrcode(str_repeat('A', 5000)));

    expect(fn () => $this->get('/qr-overflow'))->toThrow(DataTooLongException::class);
});

it('@qrcode Blade directive lets DataTooLongException bubble out at render time', function (): void {
    $compiled = Blade::compileString('@qrcode($payload)');

    ob_start();
    try {
        $payload = str_repeat('A', 5000);
        expect(function () use ($compiled, $payload): void {
            eval('?>'.$compiled);
        })->toThrow(DataTooLongException::class);
    } finally {
        ob_end_clean();
    }
});

it('a Stringable that throws PayloadException on cast propagates through factory->svg()', function (): void {
    $bad = new class () implements \Stringable {
        public function __toString(): string
        {
            throw PayloadException::emptyValue('synthetic');
        }
    };

    expect(fn () => QrCode::svg($bad))->toThrow(PayloadException::class);
});
