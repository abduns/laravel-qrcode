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

Requires PHP 8.2+ and Laravel 12 or 13 (Laravel 13 needs PHP 8.3+). PNG output needs `ext-gd`. CI runs Pest on PHP 8.2–8.5 × Laravel 12 and PHP 8.3–8.5 × Laravel 13, with both lowest and latest dependencies, plus PHPStan and php-cs-fixer.

---

## Quick Start

```php
use Dunn\QrCode\Laravel\Facades\QrCode;

$svg     = QrCode::svg('https://example.com');     // SVG markup (always SVG)
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

### 2. Image URL (`<img src="/qr?data=...">`)

Works with React, Vue, Blade, mobile WebViews — anything that can load an image.

Pass the payload as a query string, not a path segment. An encoded URL contains `%2F`, which Laravel decodes to `/` before matching `{data}`, so `/qr/{data}` returns 404 for URLs (some web servers reject encoded slashes before Laravel even sees them).

```php
use Illuminate\Http\Request;

Route::get('/qr', function (Request $request) {
    $data = $request->validate(['data' => ['required', 'string', 'max:1024']])['data'];

    return response()->qrcode($data, maxAge: 86400);
})->middleware('throttle:60,1');
```

```jsx
<img src={`/qr?data=${encodeURIComponent(url)}`} alt="QR code" />
```

```vue
<img :src="`/qr?data=${encodeURIComponent(url)}`" alt="QR code" />
```

A route like this is a public, unauthenticated rendering endpoint: rate-limit it (as above), cap the payload length, or use [signed URLs](https://laravel.com/docs/urls#signed-urls) if only your app should generate codes.

`Content-Type` follows the renderer (`image/svg+xml` by default, or `image/png` if you pin PNG). Every response includes an `ETag`, and a request whose `If-None-Match` matches it gets `304 Not Modified` with an empty body. SVG renderers that use gradients emit a random id prefix, so their ETag changes on every request and never revalidates.

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

The directive always emits inline SVG, whatever `config('qrcode.renderer')` says. It calls `QrCode::svg()`, so a second argument passes a renderer:

```blade
{{-- $renderer = new SvgRenderer(moduleShape: new DotModule()) --}}
@qrcode($ticket->url, $renderer)
```

---

## Response macro

```php
// Image response (SVG by default)
Route::get('/qr', fn (Request $request) => response()->qrcode((string) $request->query('data', 'hello')));

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

Named arguments: `filename`, `maxAge` (seconds), `download`.

- The filename gets `.svg` / `.png` / `.txt` from the renderer MIME type unless it already ends with that extension (`john.doe` → `john.doe.svg`, `ticket.` → `ticket.svg`).
- Path separators, quotes, control characters and leading/trailing dots are stripped; an empty result becomes `qrcode`.
- Non-ASCII names are sent as an ASCII `filename` plus an RFC 6266 `filename*=UTF-8''…` parameter.
- A matching `If-None-Match` returns `304 Not Modified` (200 responses to GET/HEAD only). `QrCode::toResponse()` does the same when you pass `request: $request`.

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
    'ecc'        => EccLevel::Medium, // or 'L' | 'M' | 'Q' | 'H' | 'low' | 'medium' | 'quartile' | 'high'
    'renderer'   => 'svg',            // 'svg' | 'png' | 'console' (any case)
    'size'       => 300,
    'margin'     => 4,
    'foreground' => '#000000',
    'background' => '#ffffff',
]
```

`renderer` drives `render()`, `payload()`, `dataUri()`, `toArray()`, and `response()->qrcode()`. It does **not** change `@qrcode` or `svg()` (always SVG) or `png()` / `console()` (always their own type). `size` / `margin` / colours are ignored once you pass a custom renderer.

String values work for `ecc`, so `'ecc' => env('QRCODE_ECC', 'M')` is fine. Config is read on every call, so changes made at runtime (for example per-tenant colours set in middleware) apply even after the facade was first used. Unknown `ecc` or `renderer` values, non-integer `size` / `margin`, and invalid colours throw `InvalidConfigurationException` instead of being silently ignored.

---

## Error handling

The bridge does not swallow core exceptions. Catch around `payload()`, `response()->qrcode()`, and the Blade directive:

- `Dunn\QrCode\Exception\DataTooLongException` — payload exceeds the chosen ECC/version
- `Dunn\QrCode\Exception\InvalidConfigurationException` — invalid `config('qrcode.*')` values (unknown `ecc` / `renderer`, bad `size` / `margin` / colours) and other renderer errors such as missing `ext-gd`
- `Dunn\QrCode\Exception\PayloadException` — invalid vCard / Wi-Fi / URL / … value

All three extend `Dunn\QrCode\Exception\QrCodeException`. Renderers you construct yourself (`new SvgRenderer(size: 0)`, `Color::hex('red')`) still throw the core's plain `\InvalidArgumentException` at construction time, in your code.

---

## Testing

```bash
composer test
```

---

## Compatibility

| Platform | Supported |
|---|---|
| PHP 8.2 / 8.3 / 8.4 / 8.5 | ✅ CI |
| Laravel 12 | ✅ CI (PHP 8.2–8.5, Testbench 10) |
| Laravel 13 | ✅ CI (PHP 8.3–8.5, Testbench 11; Laravel 13 needs PHP 8.3+) |
| Inertia + React / Vue | ✅ (`payload()` / `dataUri()`) |
| JSON API / SPA | ✅ (`return QrCode::payload(...)`) |
| Blade | ✅ (`@qrcode`) |

---

## License

MIT
