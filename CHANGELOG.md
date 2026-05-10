# Changelog

All notable changes to `abduns/laravel-qrcode` are documented here. The
format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
once it reaches 1.0.0. Pre-1.0 minor bumps may carry breaking changes.

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
