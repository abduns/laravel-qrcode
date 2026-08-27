# laravel-qrcode

Laravel 12/13 bridge for the [abduns/qrcode](https://github.com/abduns/qrcode) generator. Server-side QR codes that drop into Blade, Inertia, React, Vue, or any JSON API.

[![CI](https://github.com/abduns/laravel-qrcode/actions/workflows/ci.yml/badge.svg)](https://github.com/abduns/laravel-qrcode/actions/workflows/ci.yml)
[![Version](https://img.shields.io/packagist/v/abduns/laravel-qrcode.svg)](https://packagist.org/packages/abduns/laravel-qrcode)
[![Downloads](https://img.shields.io/packagist/dt/abduns/laravel-qrcode.svg)](https://packagist.org/packages/abduns/laravel-qrcode)
[![License](https://img.shields.io/packagist/l/abduns/laravel-qrcode.svg)](LICENSE.md)

---

## Installation

```bash
composer require abduns/laravel-qrcode
php artisan vendor:publish --tag=qrcode-config
```

Requires PHP 8.2+ and Laravel 12 or 13. PNG output needs `ext-gd`. CI runs Pest, PHPStan, and php-cs-fixer on PHP 8.2–8.4 against Laravel 12.

---

## Quick Start

```php
use Dunn\QrCode\Laravel\Facades\QrCode;

$svg     = QrCode::svg('https://example.com');     // SVG markup
$png     = QrCode::png('https://example.com');     // raw PNG bytes (ext-gd)
$uri     = QrCode::dataUri('https://example.com'); // data:image/svg+xml;base64,...
$payload = QrCode::payload('https://example.com'); // JSON-ready { mimeType, dataUri, svg }
```

For JavaScript frontends, start with `payload()` or `dataUri()`. Do not put `png()` bytes into JSON.

---

## Usage with React, Vue, and Inertia

QR codes are generated in PHP. The browser only displays the result. Three patterns cover every Laravel + JS stack.

### 1. JSON payload (Inertia, SPA, API)

```php
use Dunn\QrCode\Laravel\Facades\QrCode;

// API controller — returns JSON automatically
return QrCode::payload('https://example.com');

// Inertia
return Inertia::render('Ticket/Show', [
    'qr' => QrCode::payload($ticket->url),
]);
```

JSON body:

```json
{
  "mimeType": "image/svg+xml",
  "dataUri": "data:image/svg+xml;base64,PHN2Zy...",
  "svg": "<svg xmlns=\"http://www.w3.org/2000/svg\" ...></svg>"
}
```

**React (Inertia)** — `<img src>` is the safe default:

```jsx
export default function Ticket({ qr }) {
  return <img src={qr.dataUri} alt="QR code" width={300} height={300} />;
}
```

Inline SVG (only if you want to style the markup):

```jsx
<div dangerouslySetInnerHTML={{ __html: qr.svg ?? '' }} />
```

**Vue (Inertia)**:

```vue
<template>
  <img :src="qr.dataUri" alt="QR code" width="300" height="300" />
</template>

<script setup>
defineProps({
  qr: { type: Object, required: true },
})
</script>
```

Inline SVG:

```vue
<div v-html="qr.svg" />
```

**SPA fetch**:

```js
const qr = await (await fetch('/api/qr')).json()
document.querySelector('#qr').src = qr.dataUri
```

### 2. Image URL (`<img src="/qr/...">`)

Works with React, Vue, Blade, mobile WebViews — anything that can load an image.

```php
Route::get('/qr/{data}', function (string $data) {
    return response()->qrcode($data, maxAge: 86400);
});
```

```jsx
<img src={`/qr/${encodeURIComponent(url)}`} alt="QR code" />
```

```vue
<img :src="`/qr/${encodeURIComponent(url)}`" alt="QR code" />
```

`Content-Type` follows the renderer (`image/svg+xml` by default, or `image/png` if you pin PNG). Every response includes an `ETag`.

### 3. Data URI only

When you already have a string prop and do not want the full payload:

```php
return Inertia::render('Ticket/Show', [
    'qrSrc' => QrCode::dataUri($ticket->url),
]);
```

```jsx
<img src={qrSrc} alt="QR code" />
```

PNG data URI (requires `ext-gd`):

```php
use Dunn\QrCode\Renderer\Png\GdPngRenderer;

QrCode::dataUri('https://example.com', new GdPngRenderer());
// data:image/png;base64,iVBOR...
```

---

## Blade

```blade
@qrcode('https://example.com')
```

The directive emits inline SVG. Keep `config('qrcode.renderer')` as `svg` if you use it — PNG bytes inline in HTML are not useful.

---

## Response macro

```php
// Image response (SVG by default)
Route::get('/qr/{data}', fn (string $data) => response()->qrcode($data));

// Cached for CDNs / <img src>
response()->qrcode($url, maxAge: 86400);

// Suggest a filename (inline — still works as <img src>)
response()->qrcode($url, filename: 'ticket.svg');

// Force a download
response()->qrcode($url, download: true, filename: 'ticket');

// PNG, styled, or a custom status
use Dunn\QrCode\Renderer\Png\GdPngRenderer;

response()->qrcode($url, 200, new GdPngRenderer(), filename: 'ticket.png');
```

Named arguments: `filename`, `maxAge` (seconds), `download`. A filename without an extension gets `.svg` / `.png` / `.txt` from the renderer MIME type.

---

## Payloads (vCard, Wi-Fi, …)

```php
use Dunn\QrCode\Laravel\Facades\QrCode;
use Dunn\QrCode\Payload\VCard;
use Dunn\QrCode\Payload\WifiAuth;

QrCode::url('https://example.com')->build();
QrCode::wifi('Office', 'hunter2', WifiAuth::WPA)->build();

$card = VCard::make('Jane Doe')
    ->withOrg('Acme')
    ->addEmail('jane@acme.com');

return QrCode::payload($card); // same JSON shape, any Stringable payload
```

Typed factories: `url`, `text`, `phone`, `sms`, `email`, `geo`, `wifi`, `vCard`, `event`. Each returns a core `Builder` with the configured ECC applied.

---

## Styled output

```php
use Dunn\QrCode\Laravel\Facades\QrCode;
use Dunn\QrCode\Renderer\Svg\SvgRenderer;
use Dunn\QrCode\Style\ModuleShape\RoundedModule;
use Dunn\QrCode\Style\Gradient\LinearGradient;
use Dunn\QrCode\Style\Gradient\GradientStop;
use Dunn\QrCode\Style\Color;

$renderer = new SvgRenderer(
    moduleShape: new RoundedModule(),
    dotColor: new LinearGradient([
        new GradientStop(0.0, Color::hex('#264653')),
        new GradientStop(1.0, Color::hex('#2a9d8f')),
    ]),
);

QrCode::payload('https://example.com', $renderer);
QrCode::withRenderer($renderer)->dataUri('https://example.com');
```

---

## Configuration

`config/qrcode.php`:

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

`renderer` drives `render()`, `payload()`, `dataUri()`, `toArray()`, and `response()->qrcode()`. It does **not** change `@qrcode` (SVG-only by intent) or `svg()` / `png()` / `console()` (those force a family). `size` / `margin` / colours are ignored once you pass a custom renderer.

---

## Error handling

The bridge does not swallow core exceptions. Catch around `payload()`, `response()->qrcode()`, and the Blade directive:

- `Dunn\QrCode\Exception\DataTooLongException` — payload exceeds the chosen ECC/version
- `Dunn\QrCode\Exception\InvalidConfigurationException` — renderer/config errors
- `Dunn\QrCode\Exception\PayloadException` — invalid vCard / Wi-Fi / URL / … value

---

## Testing

```bash
composer test
```

---

## Compatibility

| Platform | Supported |
|---|---|
| PHP 8.2 / 8.3 / 8.4 | ✅ CI |
| Laravel 12 | ✅ CI |
| Laravel 13 | ✅ runtime (`illuminate/support` APIs used here are 12/13-compatible) |
| Inertia + React / Vue | ✅ (`payload()` / `dataUri()`) |
| JSON API / SPA | ✅ (`return QrCode::payload(...)`) |
| Blade | ✅ (`@qrcode`) |

---

## License

MIT
