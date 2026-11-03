# Changelog

All notable changes to `abduns/laravel-qrcode` are documented here. The
format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
once it reaches 1.0.0. Pre-1.0 minor bumps may carry breaking changes.

## [Unreleased]

Not tagged yet. This section also holds the work that earlier drafts of this
file listed as 1.3.0 and 1.3.1 (dated 2026-08-27): neither was ever tagged or
published, so both are folded in here. The latest release is 1.2.3.

### Changed (behaviour)
- **`svg()` and `@qrcode` always render SVG.** They used to follow
  `config('qrcode.renderer')` and any renderer pinned with `withRenderer()`,
  so `renderer => 'png'` made `@qrcode` write raw PNG bytes into the HTML and
  `withRenderer($console)->svg()` returned console text. A pinned renderer is
  now used by `svg()` only when it produces `image/svg+xml`; otherwise an
  `SvgRenderer` is built from config. A renderer passed explicitly as the
  second argument is still used as is. `render()`, `payload()`, `dataUri()`,
  `toArray()` and `response()->qrcode()` still follow the configured or
  pinned renderer. If you relied on `svg()` returning another format, call
  `render()` instead.
- **Config is read on every call.** The `qrcode` singleton used to snapshot
  `config('qrcode')` when first resolved, so later runtime changes (per-tenant
  styling in middleware, config set in tests after first use) were ignored.
  `QrCodeFactory` now accepts the config repository (a plain array still
  works) and `withRenderer()` carries it over.
- **Invalid config values throw `InvalidConfigurationException`.**
  - `qrcode.ecc` accepts an `EccLevel` case or a string: `'L'` / `'M'` /
    `'Q'` / `'H'` or `'low'` / `'medium'` / `'quartile'` / `'high'`, in any
    case, so `env('QRCODE_ECC', 'M')` works. String values used to be
    silently ignored (falling back to Medium); unknown values now throw.
  - `qrcode.renderer` is case-insensitive (`'PNG'` works). Unknown names such
    as `'gd'` used to fall back to SVG silently; they now throw.
  - `size` / `margin` must be integers (numeric strings are accepted, empty
    strings mean "use the default"); colours must be strings. Values the core
    rejects (`size` 0, negative `margin`, `'red'`) now surface as
    `InvalidConfigurationException` (with the core exception as `previous`)
    instead of a plain `\InvalidArgumentException`. Code that caught
    `\InvalidArgumentException` around rendering must catch
    `InvalidConfigurationException` (or `QrCodeException`) instead.
- **`response()->qrcode()` answers `If-None-Match`.** A request whose
  `If-None-Match` matches the ETag now gets `304 Not Modified` with an empty
  body (200 responses to GET/HEAD only; custom statuses are left alone).
  `toResponse()` gains an optional trailing `?Request $request` argument for
  the same behaviour outside the macro.
- **Content-Disposition filenames.**
  - The MIME-derived extension is appended unless the name already ends with
    it: `john.doe` → `john.doe.svg` (was sent without an extension),
    `ticket.` → `ticket.svg` (was `ticket..svg`), `ticket.png` from the SVG
    renderer → `ticket.png.svg`.
  - Leading/trailing dots, all control characters, quotes and path
    separators are stripped (`../../etc/passwd` → `etcpasswd.svg`).
  - Non-ASCII names are sent as an ASCII `filename` plus an RFC 6266
    `filename*=UTF-8''…` parameter instead of raw UTF-8 bytes.
- Text data URIs declare their charset: console output is now
  `data:text/plain;charset=utf-8;base64,…` (was `data:text/plain;base64,…`,
  which browsers decoded as US-ASCII). The payload's `mimeType` stays
  `text/plain`.
- The service provider no longer resolves the view and routing services on
  every boot: the directive is registered when `blade.compiler` is first
  resolved and the macro statically on `Illuminate\Routing\ResponseFactory`.
  The unused `provides()` method is removed (the provider was never
  deferred).

### Added
- `QrCodePayload` — JSON-serializable DTO with `mimeType`, `dataUri`, and
  `svg` (raw markup when the renderer produced SVG, otherwise `null`).
  Implements `Arrayable`, `JsonSerializable`, and `Stringable` (casts to
  the data URI). Returning it from a controller is a JSON response.
- `QrCodeFactory::payload(string|\Stringable $data, ?Renderer $renderer = null)`
  — primary JS-facing helper.
- `QrCodeFactory::dataUri(...)` — `data:{mime};base64,...` for `<img src>`.
- `QrCodeFactory::toArray(...)` — same shape as the payload, as a plain array.
- `QrCodeFactory::toResponse(...)` — HTTP response builder used by the
  `response()->qrcode()` macro. Always sets `ETag`. Optional `filename`,
  `maxAge` (seconds, `Cache-Control: public`), `download` (attachment vs
  inline) and `request` (conditional GET).
- Facade `@method` entries for `payload`, `dataUri`, `toArray`, `toResponse`.
- `response()->qrcode()` named extras: `filename`, `maxAge`, `download`.
  Existing positional calls (`$data`, `$status`, `$renderer`) are unchanged.
- `composer.json` suggests `ext-gd` (needed by `png()` and the `'png'`
  renderer), and gains `homepage`, `support` and more keywords.
- `.gitattributes` keeps tests, CI and tool configs out of the dist archive.

### Dependencies
- Requires `illuminate/contracts`, `illuminate/http`, `illuminate/routing`
  and `illuminate/view` (`^12.0|^13.0`) next to `illuminate/support`: the
  bridge uses the HTTP response/request classes, the routing response
  factory and the Blade compiler directly.
- `abduns/qrcode` is required as `^1.1`. 1.2.3 was published with
  `^1.1 || dev-main`; the `dev-main` alternative and the custom `vcs`
  repository entry are gone, so the core installs from Packagist.
- Dev constraints allow both test stacks: Pest `^3.0|^4.0`,
  `pest-plugin-laravel` `^3.0|^4.0`, Testbench `^10.0|^11.0`;
  php-cs-fixer `^3.88`.
- Author e-mail is now `werk@abduns.com`.

### Fixed
- README: the image-URL example used `Route::get('/qr/{data}')`, which returns
  404 for any URL-encoded URL because `%2F` decodes to `/` before route
  matching. It now uses a query string (`/qr?data=...`) and warns that the
  endpoint is public and should be rate-limited or signed.
- README and config docs now agree with the code about which methods follow
  `config('qrcode.renderer')`, the accepted config values, and the exceptions
  to catch.

### Tests
- New: `SvgAlwaysSvgTest`, `ConfigTest` (size / margin / colours applied to
  SVG, PNG and console output; runtime config changes; ECC / renderer
  normalisation and validation), `ServiceProviderTest` (publish tag,
  `vendor:publish`, auto-discovery entries, lazy registration).
- `PayloadFactoryTest` compares every typed factory with the core
  equivalent and with variants that drop an argument, instead of asserting
  `build()` is not null.
- `ResponseMacroTest` covers `.png` / `.txt` download names, filename
  sanitisation, `filename*`, and conditional requests (304, stale ETag,
  custom status).
- `JsFriendlyOutputTest` — data URI encoding (SVG / PNG / console, with
  charset), payload JSON shape, string cast, facade proxies, and
  `return QrCode::payload()` as a JSON route.

### Documentation
- README rewritten around the three JS consumption patterns (JSON payload,
  image URL, data URI) with Inertia React, Inertia Vue, and SPA fetch
  examples.
- README CI badge points at `.github/workflows/ci.yml`.

### CI
- One shared workflow: PHP 8.2–8.5 × Laravel 12 and PHP 8.3–8.5 × Laravel 13,
  each with `--prefer-lowest` and `--prefer-stable`, plus a separate
  static-analysis job (`composer validate`, PHPStan at PHP 8.2 level,
  php-cs-fixer). Read-only token permissions and a concurrency group.
- `gd` + `mbstring` extensions so PNG tests run on the runner.
- php-cs-fixer uses the `@PHP8x2Migration` sets (the `@PHP82Migration` names
  are deprecated) and also lints `config/`.

## [1.2.3] — 2026-05-17

### Changed
- `composer test` runs `@php ./vendor/bin/pest`.

### Notes
- Like every release since 1.1.1, this one requires
  `abduns/qrcode: ^1.1 || dev-main`. The `dev-main` alternative is dropped in
  [Unreleased].

### Documentation
- Removed the custom coverage badge from the README.

## [1.2.2] — 2026-05-17

Re-tag of the 1.2.1 commit; no changes.

## [1.2.1] — 2026-05-17

### Documentation
- README rewritten in a shorter, more conventional layout.

## [1.2.0] — 2026-05-11

Additive release — no breaking changes. Closes the convenience-surface gaps
called out in the 1.1.1 audit: the bridge now first-classes PNG and console
output, exposes a renderer-agnostic primary method, and lets you pick the
default renderer from config without writing PHP.

### Added
- `QrCodeFactory::render(string|\Stringable $data, ?Renderer $renderer = null)` —
  renderer-agnostic primary. Uses the override → pinned default →
  config-driven default chain. `svg()` is preserved as a BC alias.
  (Despite the note below, 1.2.x `svg()` and `@qrcode` did follow
  `config('qrcode.renderer')`; this is fixed in [Unreleased].)
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

[Unreleased]: https://github.com/abduns/laravel-qrcode/compare/v1.2.3...HEAD
[1.2.3]: https://github.com/abduns/laravel-qrcode/compare/v1.2.2...v1.2.3
[1.2.2]: https://github.com/abduns/laravel-qrcode/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/abduns/laravel-qrcode/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/abduns/laravel-qrcode/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/abduns/laravel-qrcode/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/abduns/laravel-qrcode/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/abduns/laravel-qrcode/compare/v0.3.0...v1.0.0
[0.3.0]: https://github.com/abduns/laravel-qrcode/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/abduns/laravel-qrcode/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/abduns/laravel-qrcode/releases/tag/v0.1.0
