# laravel-qrcode

Laravel 12/13 bridge for the abduns/qrcode QR Code generator: ServiceProvider, Facade, Blade directive, and response()->qrcode() macro.

[![Tests](https://github.com/abduns/laravel-qrcode/actions/workflows/tests.yml/badge.svg)](https://github.com/abduns/laravel-qrcode/actions)
[![Coverage](https://img.shields.io/endpoint?url=https://raw.githubusercontent.com/abduns/laravel-qrcode/main/coverage.json)](https://github.com/abduns/laravel-qrcode)
[![Version](https://img.shields.io/packagist/v/abduns/laravel-qrcode.svg)](https://packagist.org/packages/abduns/laravel-qrcode)
[![Downloads](https://img.shields.io/packagist/dt/abduns/laravel-qrcode.svg)](https://packagist.org/packages/abduns/laravel-qrcode)
[![License](https://img.shields.io/packagist/l/abduns/laravel-qrcode.svg)](LICENSE.md)

---

## Features

- Modern PHP support
- Lightweight and fast
- Typed API
- Laravel Facade & Blade Directives
- Standards-oriented
- Response Macros

---

## Installation

```bash
composer require abduns/laravel-qrcode
php artisan vendor:publish --tag=qrcode-config
```

---

## Quick Start

```php
use Dunn\QrCode\Laravel\Facades\QrCode;

$svg = QrCode::svg('https://example.com');
```

---

## Why This Package?

- Existing solutions are outdated
- Missing modern PHP features
- Poor developer experience
- No standards compliance
- Too framework-coupled

This package focuses on simplicity, interoperability, and modern developer ergonomics for generating QR Codes directly in Laravel.

---

## Usage

### Basic Usage

```php
use Dunn\QrCode\Laravel\Facades\QrCode;

$svg     = QrCode::svg('https://example.com');     // SVG markup (the default)
$png     = QrCode::png('https://example.com');     // raw PNG bytes (ext-gd)
$ascii   = QrCode::console('https://example.com'); // unicode block string
$builder = QrCode::create('https://example.com');  // raw core Builder
```

### Advanced Usage

```php
use Dunn\QrCode\Laravel\Facades\QrCode;
use Dunn\QrCode\Payload\VCard;

// vCard — compose the contact, then hand to the facade
$card = VCard::make('John Doe')
    ->withOrg('Acme')
    ->addPhone('+14155550123', VCard::TYPE_WORK)
    ->addEmail('john@acme.com');

$svg = QrCode::svg($card);
```

### Blade Directive

```blade
{!! '@qrcode'('https://example.com') !!}
```

### Response Macro

```php
use Dunn\QrCode\Renderer\Png\GdPngRenderer;

Route::get('/qr/{data}', fn (string $data) => response()->qrcode($data));
```

### Configuration

`config/qrcode.php` exposes:

```php
[
    'ecc'        => EccLevel::Medium,
    'renderer'   => 'svg',         // 'svg' | 'png' | 'console'
    'size'       => 300,
    'margin'     => 4,
    'foreground' => '#000000',
    'background' => '#ffffff',
]
```

---

## Standards / Specifications

References:

- ISO/IEC 18004

---

## Supported Features

| Feature | Support |
|---|---|
| Facade | ✅ |
| Blade Directives | ✅ |
| Response Macros | ✅ |

---

## Compatibility

| Platform | Supported |
|---|---|
| PHP 8.2+ | ✅ |
| Laravel 12.0+ | ✅ |

---

## Design Goals

- Developer experience first
- Predictable APIs
- Minimal dependencies
- Strong typing
- Extensibility
- Interoperability

---

## Architecture

- Facades
- Service Providers
- Response Macros
- Blade Directives

---

## Performance

| Operation | Time |
|---|---|
| Render component | < 2ms |

---

## Testing

```bash
composer test
```

---

## Roadmap

- [ ] Support Livewire components

---

## Contributing

Contributions, issues, and discussions are welcome.

---

## Security

If you discover security issues, please report them responsibly.

---

## License

MIT
