# abduns/laravel-qrcode

Laravel 12/13 bridge for [`abduns/qrcode`](../qr-code).

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

### Typed payloads

Mirroring the core's [payload helpers](../qr-code/README.md#payload-helpers),
the facade exposes nine semantic factories. Each returns a `Builder`
pre-configured with the ECC from `config/qrcode.php`, so you can chain the
usual `errorCorrection()` / `forceVersion()` overrides or call `build()`
directly. Pair with `svg()` or `response()->qrcode()` to render.

```php
use Dunn\QrCode\Laravel\Facades\QrCode;
use Dunn\QrCode\Payload\Event;
use Dunn\QrCode\Payload\VCard;
use Dunn\QrCode\Payload\WifiAuth;

// Link / text / phone / sms / email / geo
QrCode::url('https://example.com')->build();
QrCode::text('hello')->build();
QrCode::phone('+14155550123')->build();
QrCode::sms('+14155550123', body: 'hi')->build();
QrCode::email('a@b.com', subject: 'hello', body: 'hi')->build();
QrCode::geo(37.7749, -122.4194, label: 'SF')->build();

// WiFi join
QrCode::wifi('MyNet', password: 'secret', auth: WifiAuth::WPA)->build();

// vCard — compose the contact, then hand to the facade
$card = VCard::make('John Doe')
    ->withOrg('Acme')
    ->addPhone('+14155550123', VCard::TYPE_WORK)
    ->addEmail('john@acme.com');

QrCode::vCard($card)->build();

// Calendar event
$event = Event::make('Launch party')
    ->from(new DateTimeImmutable('2026-06-01 18:00', new DateTimeZone('UTC')))
    ->to(new DateTimeImmutable('2026-06-01 22:00', new DateTimeZone('UTC')));

QrCode::event($event)->build();

// All of the above also work in svg() / response()->qrcode() directly,
// because payload value objects are \Stringable:
$svg = QrCode::svg($card);
Route::get('/contact.svg', fn () => response()->qrcode($card));
```

### Blade directive

```blade
{!! '@qrcode'('https://example.com') !!}
```

### Response macro

```php
Route::get('/qr/{data}', fn (string $data) => response()->qrcode($data));
```

### Styled output

Build a custom renderer from the core package and pass it through:

```php
use Dunn\QrCode\Laravel\Facades\QrCode;
use Dunn\QrCode\Renderer\Svg\SvgRenderer;
use Dunn\QrCode\Style\Color;
use Dunn\QrCode\Style\ModuleShape\DotModule;
use Dunn\QrCode\Style\EyeStyle\CircleEyeOuter;
use Dunn\QrCode\Style\EyeStyle\CircleEyeInner;
use Dunn\QrCode\Style\Logo;

$styled = new SvgRenderer(
    moduleShape: new DotModule(),
    eyeOuter: new CircleEyeOuter(),
    eyeInner: new CircleEyeInner(),
    dotColor: Color::hex('#264653'),
    markerOuterColor: Color::hex('#2a9d8f'),
    markerInnerColor: Color::hex('#e76f51'),
    logo: Logo::fromFile(public_path('logo.png'), sizeRatio: 0.18),
);

// One-off: pass the renderer to svg().
$svg = QrCode::svg('https://abduns.dev', $styled);

// App-wide: pin the renderer onto a factory clone.
$factory = QrCode::withRenderer($styled);
$factory->svg('any payload');

// Response macro variant — Content-Type follows the renderer's mimeType().
Route::get('/qr/{data}', fn (string $data) => response()->qrcode($data, 200, $styled));
```

See the [core package's customization docs](../qr-code/README.md#customization)
for the full catalogue of module shapes, eye styles, colours, and logo
options.

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

These defaults feed the basic `SvgRenderer` the factory builds when no
custom renderer is supplied.

## License

MIT
