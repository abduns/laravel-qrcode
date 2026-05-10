<?php

declare(strict_types=1);

use Dunn\QrCode\Builder;
use Dunn\QrCode\Laravel\Facades\QrCode;

it('proxies create() to the bound factory', function (): void {
    $builder = QrCode::create('HELLO WORLD');
    expect($builder)->toBeInstanceOf(Builder::class);
});

it('proxies svg() to render an inline <svg>', function (): void {
    $svg = QrCode::svg('HELLO WORLD');
    expect($svg)->toStartWith('<svg ');
    expect($svg)->toContain('xmlns="http://www.w3.org/2000/svg"');
});
