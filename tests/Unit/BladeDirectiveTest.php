<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

it('compiles the @qrcode directive to a php block calling the factory', function (): void {
    $compiled = Blade::compileString("@qrcode('HELLO WORLD')");

    expect($compiled)->toContain("<?php echo app('qrcode')->svg(");
    expect($compiled)->toContain("'HELLO WORLD'");
});

it('renders an inline SVG via the directive at runtime', function (): void {
    $compiled = Blade::compileString("@qrcode('HELLO WORLD')");

    // Evaluate the compiled php using output buffering.
    ob_start();
    eval('?>'.$compiled);
    $output = ob_get_clean();

    expect($output)->toStartWith('<svg ');
    expect($output)->toContain('</svg>');
});
