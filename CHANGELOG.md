# Changelog

All notable changes to `abduns/laravel-qrcode` are documented here. The
format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
once it reaches 1.0.0. Pre-1.0 minor bumps may carry breaking changes.

## [1.2.0] — 2026-05-11

Additive release — no breaking changes. Closes the convenience-surface gaps
called out in the 1.1.1 audit: the bridge now first-classes PNG and console
output, exposes a renderer-agnostic primary method, and lets you pick the
default renderer from config without writing PHP.

### Added
- `QrCodeFactory::render(string|\Stringable $data, ?Renderer $renderer = null)` —
  renderer-agnostic primary. Uses the override → pinned default →
  config-driven default chain. `svg()` is preserved as a BC alias.
- `QrCodeFactory::png(string|\Stringable $data, ?Renderer $renderer = null)` —
  forces a `GdPngRenderer` built from config when no override is supplied.
  Ignores `config('qrcode.renderer')` and any pinned default, so the result
  is always PNG bytes. Requires `ext-gd` at render time.
- `QrCodeFactory::console(string|\Stringable $data, ?Renderer $renderer = null)` —
  forces a `ConsoleRenderer`. Useful for `php artisan tinker` and CLI
  debugging.
- All four methods (`render`, `svg`, `png`, `console`) exposed on the
  `Facades\QrCode` `@method` list.
- `config('qrcode.renderer')` key — `'svg'` (default), `'png'`, or
  `'console'`. Picks the renderer the factory builds when no override is
  supplied and nothing is pinned via `withRenderer()`. Drives
  `response()->qrcode()` and `QrCodeFactory::render()` — but NOT the
  `@qrcode` Blade directive (still SVG-only by intent) and NOT `svg()` /
  `png()` / `console()` (renderer-pinned sugar).

### Tests
- `tests/Unit/RenderMethodsTest.php` (12 tests) — covers `render` / `png` /
  `console` semantics, config-driven `renderer()` selection, pinned-default
  precedence, and the `response()->qrcode()` Content-Type / body change
  when `config('qrcode.renderer') = 'png'`.
- `tests/Unit/ExceptionBubblingTest.php` (4 tests) — verifies
  `DataTooLongException` bubbles out of `factory->svg()`,
  `response()->qrcode()`, and the `@qrcode` Blade directive without being
  swallowed by the bridge. Also covers `PayloadException` propagation
  through a Stringable that throws on cast.
- `tests/Unit/StyledRenderingTest.php` (5 tests) — smoke tests that the
  bridge passes `LinearGradient`, `RadialGradient`, `RoundedModule`,
  `RoundedEyeOuter` / `RoundedEyeInner`, and `Logo` through to the SVG
  output unchanged. Regression guard if the bridge ever grows
  post-processing.
- Suite total: 27 → 48 tests, 51 → 83 assertions.

### Documentation
- README's Facade section now shows `svg()` / `png()` / `console()` /
  `render()` side-by-side with a one-line explanation of when to use each.
- README's Styled output section gained a gradient + rounded preset
  example and a tinker / `ConsoleRenderer` snippet.
- README's Config section documents the new `renderer` key with the
  Blade-directive caveat (PNG bytes inline in HTML are not useful).
- README's Response macro section mentions the `config('qrcode.renderer')`
  shortcut for PNG-everywhere apps.

### Notes
- Backward compatible. `QrCodeFactory::svg($data, ?Renderer)` keeps its
  renderer-agnostic behaviour exactly as in 1.1.x — the only behavioural
  change is that the default renderer is now config-driven (defaulting to
  `'svg'`, so existing apps see no change).

## [1.1.1] — 2026-05-11

Patch release. No new API surface; widens an existing macro's accepted type
and tightens the README.

### Changed
- `response()->qrcode()` now accepts `string|\Stringable`, so payload value
  objects (`VCard`, `Event`, `Wifi`, …) can be passed directly — matching
  `QrCodeFactory::create()` and `QrCodeFactory::svg()`.

### Added
- README: "Error handling" section enumerating `DataTooLongException`,
  `InvalidConfigurationException`, and `PayloadException` so apps know what
  to catch around `response()->qrcode()` and the Blade directive.
- README: PNG response example showing `response()->qrcode($data, 200,
  new GdPngRenderer())` — the `Content-Type` header follows the renderer's
  `mimeType()` automatically.
- README: `Builder` chaining example (`QrCode::wifi(...)->errorCorrection(...)
  ->build()`) — the typed factories return a `Builder` so the full core
  pipeline is available.
- `config/qrcode.php`: inline note clarifying that `size` / `margin` /
  `foreground` / `background` only apply to the default `SvgRenderer` and
  are ignored once a custom renderer is pinned via `withRenderer()` or
  passed to `svg()`.

### Fixed
- README: corrected the prose claiming `response()->qrcode()` already
  accepted `\Stringable` payloads (it didn't until this release).
- README: cross-package links now point at
  https://github.com/abduns/qrcode instead of a local monorepo path so they
  resolve on Packagist and GitHub.
- composer.json description updated to reflect Laravel 12/13 (Laravel 11
  was dropped in 1.0.0).

## [1.1.0] — 2026-05-11

Tracks `abduns/qrcode` v1.1.0 (semantic payload helpers).

### Added
- Nine new payload factories on `QrCodeFactory` and `Facades\QrCode`, each
  returning the existing `Builder` pre-configured with the ECC from
  `config/qrcode.php`:
  - `QrCode::url(string $url)`
  - `QrCode::text(string $text)`
  - `QrCode::phone(string $number)`
  - `QrCode::sms(string $number, ?string $body = null, bool $useSmsUri = false)`
  - `QrCode::email(string $to, ?string $subject = null, ?string $body = null, list<string> $cc = [], list<string> $bcc = [])`
  - `QrCode::geo(float $latitude, float $longitude, ?string $label = null)`
  - `QrCode::wifi(string $ssid, ?string $password = null, WifiAuth $auth = WifiAuth::WPA, bool $hidden = false)`
  - `QrCode::vCard(VCard $vcard)`
  - `QrCode::event(Event $event)`
- `QrCodeFactory::create()` and `QrCodeFactory::svg()` now accept
  `string|\Stringable`, so any core payload value object can be passed
  directly.

### Changed
- No breaking changes. Existing `QrCode::create($string)` / `QrCode::svg(...)` /
  `response()->qrcode(...)` calls behave identically.

### Usage

```php
use Dunn\QrCode\Laravel\Facades\QrCode;
use Dunn\QrCode\Payload\VCard;
use Dunn\QrCode\Payload\WifiAuth;

QrCode::url('https://abduns.dev')->build();
QrCode::wifi('Office', 'hunter2', WifiAuth::WPA)->build();
QrCode::svg(VCard::make('Jane')->addEmail('jane@acme.com'));
```

## [1.0.0] — 2026-05-11

First stable release. Tracks `abduns/qrcode` v1.0.0.

### Changed (BREAKING)
- Dropped Laravel 11 support. Now requires Laravel 12 or 13.
- `orchestra/testbench` constraint bumped to `^10.0|^11.0`.
- `abduns/qrcode` constraint pinned to `^1.0` (was `*@dev`).
- Composer `repositories` block switched from a local `path` to a
  GitHub `vcs` entry pointing at https://github.com/abduns/qrcode.

### Added
- No new API surface in this release — the v0.2 factory passthrough
  (`QrCode::svg($data, $renderer)`, `QrCode::withRenderer($renderer)`,
  `response()->qrcode($data, $status, $renderer)`) already supports
  every styled renderer added in `abduns/qrcode` v1.0.0 (RoundedModule,
  RoundedEye*, LinearGradient, RadialGradient, Logo).

## [0.3.0] — 2026-05-11

Tracks `abduns/qrcode` v0.3.0 (`RoundedModule`, `LinearGradient` /
`RadialGradient`, `GdPngRenderer` parity).

### Added
- No new API surface in this bridge package — the v0.2 factory passthrough
  (`QrCode::svg($data, $renderer)` / `QrCode::withRenderer($renderer)` /
  `response()->qrcode($data, $status, $renderer)`) already accepts any
  `Renderer` instance, including the new v0.3 styled renderers.

### Changed
- Composer constraint bumped to `abduns/qrcode: *@dev` (local) — public
  releases will require `^0.3`.
- README's "Styled output" section linked to the updated core
  customization docs.

### Usage with v0.3 features

```php
use Dunn\QrCode\Laravel\Facades\QrCode;
use Dunn\QrCode\Renderer\Svg\SvgRenderer;
use Dunn\QrCode\Style\ModuleShape\RoundedModule;
use Dunn\QrCode\Style\Gradient\{LinearGradient, GradientStop};
use Dunn\QrCode\Style\Color;

$svg = QrCode::svg('https://abduns.dev', new SvgRenderer(
    moduleShape: new RoundedModule(),
    dotColor: new LinearGradient([
        new GradientStop(0.0, Color::hex('#264653')),
        new GradientStop(1.0, Color::hex('#2a9d8f')),
    ]),
));
```

## [0.2.0] — 2026-05-11

Tracks `abduns/qrcode` v0.2.0 (per-region eye styles, per-region colours,
logo overlays).

### Added
- `QrCodeFactory::svg($data, ?Renderer $renderer = null)` — pass a styled
  renderer (`SvgRenderer` with `DotModule`, `CircleEyeOuter`, `Logo`, etc.)
  for one-off renders.
- `QrCodeFactory::withRenderer(Renderer $renderer): self` — returns a new
  factory with the renderer pinned as the default.
- `response()->qrcode($data, $status = 200, ?Renderer $renderer = null)`
  now accepts a renderer override. The `Content-Type` header follows the
  renderer's `mimeType()`, so PNG/console responses get the right header
  automatically.

### Changed
- No breaking API changes. The unsigned `svg($data)` / `renderer()` calls
  behave exactly as before.

## [0.1.0] — 2026-05-11

Initial release.

- `QrCodeServiceProvider` registers a `QrCodeFactory` singleton at
  `'qrcode'`, publishes `config/qrcode.php` under tag `qrcode-config`.
- `Facades\QrCode` for static-style access (`create`, `svg`, `renderer`).
- `@qrcode('...')` Blade directive renders inline SVG.
- `response()->qrcode($data, $status = 200)` macro returns an
  `image/svg+xml` response.
- Tested via Pest 3 + Orchestra Testbench against Laravel 11 and 12.
