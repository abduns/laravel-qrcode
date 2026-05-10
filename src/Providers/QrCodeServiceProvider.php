<?php

declare(strict_types=1);

namespace Dunn\QrCode\Laravel\Providers;

use Dunn\QrCode\Laravel\QrCodeFactory;
use Dunn\QrCode\Renderer\Renderer;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the QR code factory as a singleton, publishes the config, and
 * wires up the `@qrcode(...)` Blade directive plus `response()->qrcode(...)`
 * macro.
 */
final class QrCodeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/qrcode.php', 'qrcode');

        $this->app->singleton('qrcode', function (Application $app): QrCodeFactory {
            /** @var \Illuminate\Contracts\Config\Repository $repo */
            $repo = $app->make('config');

            /** @var array{ecc?: \Dunn\QrCode\EccLevel, size?: int, margin?: int, foreground?: string, background?: string} $config */
            $config = $repo->get('qrcode', []);

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
        // Renders an inline <svg> string using the factory's default renderer.
        Blade::directive('qrcode', static fn (string $expression): string => "<?php echo app('qrcode')->svg({$expression}); ?>");

        // Response macro: return response()->qrcode('https://example.com');
        // Pass a Renderer to opt into styled rendering:
        //   response()->qrcode($url, 200, new SvgRenderer(moduleShape: new DotModule()))
        ResponseFacade::macro('qrcode', function (string $data, int $status = 200, ?Renderer $renderer = null): Response {
            /** @var QrCodeFactory $factory */
            $factory = app('qrcode');
            $renderer ??= $factory->renderer();
            $body = $renderer->render($factory->create($data)->build());

            return new Response($body, $status, ['Content-Type' => $renderer->mimeType()]);
        });
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return ['qrcode', QrCodeFactory::class];
    }
}
