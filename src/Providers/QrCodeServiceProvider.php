<?php

declare(strict_types=1);

namespace Dunn\QrCode\Laravel\Providers;

use Dunn\QrCode\Laravel\QrCodeFactory;
use Dunn\QrCode\Renderer\Renderer;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Response;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;

/**
 * Registers the QR code factory as a singleton, publishes the config, and
 * wires up the `@qrcode(...)` Blade directive plus `response()->qrcode(...)`
 * macro.
 *
 * The provider is intentionally not deferred: boot() registers a Blade
 * directive and a response macro. Neither registration resolves the view or
 * routing services, so queue workers and artisan commands pay nothing for it.
 */
final class QrCodeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/qrcode.php', 'qrcode');

        // The factory keeps a reference to the config repository and reads
        // `qrcode.*` lazily, so config changes made after the first
        // resolution are honoured.
        $this->app->singleton('qrcode', static function (Application $app): QrCodeFactory {
            /** @var Repository $config */
            $config = $app->make('config');

            return new QrCodeFactory($config);
        });

        $this->app->alias('qrcode', QrCodeFactory::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/qrcode.php' => $this->app->configPath('qrcode.php'),
            ], 'qrcode-config');
        }

        // Blade directive: @qrcode('https://example.com')
        // Always renders inline SVG markup (see QrCodeFactory::svg()).
        // Registered once the Blade compiler is first resolved (immediately if
        // it already is), so booting the app does not build the view layer.
        $this->callAfterResolving('blade.compiler', static function (BladeCompiler $blade): void {
            $blade->directive('qrcode', static fn (string $expression): string => "<?php echo app('qrcode')->svg({$expression}); ?>");
        });

        // Response macro: return response()->qrcode('https://example.com');
        // Named extras (filename / maxAge / download) are for <img src> caching
        // and file downloads. Pass a Renderer as the 3rd argument to style:
        //   response()->qrcode($url, 200, new SvgRenderer(moduleShape: new DotModule()))
        //   response()->qrcode($url, filename: 'ticket.svg', maxAge: 86400)
        // A matching If-None-Match on the current request yields a 304.
        // Registered statically on the concrete factory, which needs no
        // container resolution.
        ResponseFactory::macro('qrcode', function (
            string|\Stringable $data,
            int $status = 200,
            ?Renderer $renderer = null,
            ?string $filename = null,
            ?int $maxAge = null,
            bool $download = false,
        ): Response {
            /** @var QrCodeFactory $factory */
            $factory = app('qrcode');

            return $factory->toResponse($data, $status, $renderer, $filename, $maxAge, $download, request());
        });
    }
}
