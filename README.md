# abduns/laravel-qrcode

Laravel 11/12 bridge for [`abduns/qrcode`](../qr-code).

## Install

```bash
composer require abduns/laravel-qrcode
php artisan vendor:publish --tag=qrcode-config
```

## Use

### Facade

```php
use Dunn\QrCode\Laravel\Facades\QrCode;

$svg = QrCode::svg('https://example.com');
$builder = QrCode::create('https://example.com');  // returns the core Builder
```

### Blade directive

```blade
{!! '@qrcode'('https://example.com') !!}
```

### Response macro

```php
Route::get('/qr/{data}', fn (string $data) => response()->qrcode($data));
```

## Config

`config/qrcode.php` exposes:

```php
[
    'ecc'        => EccLevel::Medium,
    'size'       => 300,
    'margin'     => 4,
    'foreground' => '#000000',
    'background' => '#ffffff',
]
```

## License

MIT
