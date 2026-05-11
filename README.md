# abduns/laravel-qrcode

Laravel 12/13 bridge for [`abduns/qrcode`](https://github.com/abduns/qrcode).

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

Mirroring the core's [payload helpers](https://github.com/abduns/qrcode#payload-helpers),
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

// Payload value objects are \Stringable, so they can be passed straight
// to svg() or response()->qrcode() without calling ->build() first.
$svg = QrCode::svg($card);
Route::get('/contact.svg', fn () => response()->qrcode($card));
```

### Builder chaining

Every typed factory returns the core `Builder`, so the full pipeline
(`errorCorrection`, `forceVersion`, `forceMode`, `build`) is available
without dropping back to the core `QrCode::create()`:

```php
use Dunn\QrCode\Encoder\Mode;
use Dunn\QrCode\EccLevel;
use Dunn\QrCode\Laravel\Facades\QrCode;
use Dunn\QrCode\Payload\WifiAuth;

$qr = QrCode::wifi('Office', 'hunter2', WifiAuth::WPA)
    ->errorCorrection(EccLevel::High)   // overrides config('qrcode.ecc')
    ->forceVersion(8)
    ->forceMode(Mode::Byte)
    ->build();
```

Pass the resulting `QrCode` to any renderer, or hand the payload value
object straight to `svg()` / `response()->qrcode()` to skip the explicit
`build()`.

### Blade directive

```blade
{!! '@qrcode'('https://example.com') !!}
```

### Response macro

```php
use Dunn\QrCode\Renderer\Png\GdPngRenderer;

// SVG (the default — Content-Type: image/svg+xml).
Route::get('/qr/{data}', fn (string $data) => response()->qrcode($data));

// PNG — pass a GdPngRenderer. Content-Type follows the renderer's
// mimeType() automatically, so the response comes back as image/png.
Route::get('/qr.png/{data}', fn (string $data) => response()->qrcode(
    $data,
    200,
    new GdPngRenderer(size: 300),
));
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

See the [core package's customization docs](https://github.com/abduns/qrcode#styling)
for the full catalogue of module shapes, eye styles, colours, and logo
options.

## Error handling

All exceptions thrown by the core extend `Dunn\QrCode\Exception\QrCodeException`
(which extends `RuntimeException`). The bridge does not catch them — they
bubble straight out of `QrCode::svg()`, `response()->qrcode()`, the
`@qrcode` directive, and any `Builder` chain.

- **`DataTooLongException`** — input cannot fit into a v40 symbol at the
  chosen ECC level. Drop the ECC, shorten the payload, or pick a denser
  mode via `forceMode(Mode::Numeric)`.
- **`InvalidConfigurationException`** — renderer misconfigured: `ext-gd`
  missing for `GdPngRenderer`, logo path doesn't exist, unsupported MIME,
  or `sizeRatio` exceeds the ECC budget.
- **`PayloadException`** — typed-payload value object rejected its input
  (empty SSID, latitude out of range, end-before-start event, …).

Inside Laravel you'll typically want to handle these in `app/Exceptions/Handler.php`
or with a route-scoped `try`/`catch`:

```php
use Dunn\QrCode\Exception\DataTooLongException;
use Dunn\QrCode\Exception\InvalidConfigurationException;
use Dunn\QrCode\Exception\PayloadException;
use Dunn\QrCode\Laravel\Facades\QrCode;

Route::get('/qr/{data}', function (string $data) {
    try {
        return response()->qrcode($data);
    } catch (PayloadException | DataTooLongException $e) {
        abort(422, $e->getMessage());
    } catch (InvalidConfigurationException $e) {
        report($e);
        abort(500);
    }
});
```

See the [core package's error-handling docs](https://github.com/abduns/qrcode#error-handling)
for the canonical reference.

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

- `ecc` is applied to every `Builder` the factory hands out (so it covers
  `create()`, all typed payload factories, `svg()`, the Blade directive,
  and the response macro).
- `size`, `margin`, `foreground`, `background` only feed the basic
  `SvgRenderer` the factory builds when no custom renderer is supplied.
  Once you pin a renderer via `QrCode::withRenderer(...)` or pass one to
  `svg()` / `response()->qrcode()`, those config keys are ignored — the
  renderer carries its own configuration.

## License

MIT
