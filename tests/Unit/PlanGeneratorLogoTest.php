<?php

declare(strict_types=1);

use App\Models\PlanGenerator;
use App\Services\PlanGeneratorPdfService;
use App\Support\PlanGenerators\PlanGeneratorLogo;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

/**
 * Logo propio de la página de cálculos del generador de planes. Disco público
 * falso y caché en memoria: no escribe en la base ni en el disco real.
 */
beforeEach(function (): void {
    config(['cache.default' => 'array']);
    Storage::fake('public');
});

function planGeneratorLogoPng(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 23, 97, 47));
    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();
    imagedestroy($image);

    return $png;
}

function planGeneratorLogoDecode(string $dataUri): GdImage
{
    expect($dataUri)->toStartWith('data:image/png;base64,');

    return imagecreatefromstring((string) base64_decode(substr($dataUri, strlen('data:image/png;base64,')), true));
}

it('sin logo cargado no devuelve nada y el PDF usa el de siempre', function (): void {
    expect(PlanGeneratorLogo::dataUri(null))->toBeNull()
        ->and(PlanGeneratorLogo::dataUri(''))->toBeNull()
        ->and(PlanGeneratorLogo::fingerprint(null))->toBe('');
});

it('acomoda un logo cuadrado en la misma caja del logo de siempre, a la izquierda y con fondo transparente', function (): void {
    Storage::disk('public')->put('plan-generator-logos/cuadrado.png', planGeneratorLogoPng(400, 400));

    $image = planGeneratorLogoDecode((string) PlanGeneratorLogo::dataUri('plan-generator-logos/cuadrado.png'));

    $leftPixel = imagecolorsforindex($image, imagecolorat($image, 10, 200));
    $rightPixel = imagecolorsforindex($image, imagecolorat($image, 1100, 200));

    expect(imagesx($image))->toBe(1200)
        ->and(imagesy($image))->toBe(400)
        ->and($leftPixel['alpha'])->toBe(0)
        ->and([$leftPixel['red'], $leftPixel['green'], $leftPixel['blue']])->toBe([23, 97, 47])
        ->and($rightPixel['alpha'])->toBe(127);
});

it('acepta JPG y un logo muy ancho no se sale de la caja', function (): void {
    $image = imagecreatetruecolor(3000, 300);
    ob_start();
    imagejpeg($image);
    Storage::disk('public')->put('plan-generator-logos/ancho.jpg', (string) ob_get_clean());
    imagedestroy($image);

    $result = planGeneratorLogoDecode((string) PlanGeneratorLogo::dataUri('plan-generator-logos/ancho.jpg'));

    expect(imagesx($result))->toBe(1200)
        ->and(imagesy($result))->toBe(400)
        ->and(imagecolorsforindex($result, imagecolorat($result, 600, 5))['alpha'])->toBe(127)
        ->and(imagecolorsforindex($result, imagecolorat($result, 600, 200))['alpha'])->toBe(0);
});

it('vuelve al logo de siempre si el archivo se borró, está dañado o la ruta no es de logos', function (string $path, ?string $content): void {
    if ($content !== null) {
        Storage::disk('public')->put($path, $content);
    }

    expect(PlanGeneratorLogo::dataUri($path))->toBeNull();
})->with([
    'archivo borrado' => ['plan-generator-logos/ya-no-existe.png', null],
    'no es una imagen' => ['plan-generator-logos/falso.png', 'esto no es un png'],
    'fuera del directorio' => ['administracion/logoNewPdfTDEC.png', null],
    'ruta con ..' => ['plan-generator-logos/../administracion/x.png', null],
]);

it('cambiar el logo cambia la versión en caché del PDF', function (): void {
    Storage::disk('public')->put('plan-generator-logos/nuevo.png', planGeneratorLogoPng(200, 100));

    $sinLogo = new PlanGenerator(['name' => 'Plan I', 'brand_color' => '#1d4ed8']);
    $conLogo = new PlanGenerator(['name' => 'Plan I', 'brand_color' => '#1d4ed8', 'logo_path' => 'plan-generator-logos/nuevo.png']);

    expect(PlanGeneratorPdfService::pdfCacheVersion($sinLogo))
        ->not->toBe(PlanGeneratorPdfService::pdfCacheVersion($conLogo));
});

it('el formulario, el PDF y las derivadas usan el logo propio', function (): void {
    $root = dirname(__DIR__, 2);
    $service = (string) file_get_contents($root.'/app/Services/PlanGeneratorPdfService.php');
    $form = (string) file_get_contents($root.'/app/Filament/Business/Resources/PlanGenerators/Schemas/PlanGeneratorForm.php');
    $cloner = (string) file_get_contents($root.'/app/Support/PlanGenerators/PlanGeneratorTemplateCloner.php');

    $body = (string) file_get_contents($root.'/resources/views/documents/partials/plan-generator-plan-body.blade.php');

    expect($service)->toContain('$customLogoDataUri = PlanGeneratorLogo::dataUri($planGenerator->logo_path);')
        ->toContain("'logoDataUri' => \$customLogoDataUri ?? self::cachedLogoDataUri()")
        ->toContain("'customLogoWidth' => \$customLogoDataUri !== null ? PlanGeneratorLogo::PDF_WIDTH_PT : null")
        ->and($body)->toContain('@if (! empty($customLogoWidth)) style="width: {{ (int) $customLogoWidth }}px;"@endif')
        ->and(PlanGeneratorLogo::PDF_WIDTH_PT)->toBe(120)
        ->and($service)
        ->toContain('PlanGeneratorLogo::fingerprint($planGenerator->logo_path)')
        ->and($form)->toContain("FileUpload::make('logo_path')")
        ->toContain('->directory(PlanGeneratorLogo::DIRECTORY)')
        ->and($cloner)->toContain("\$attributes['logo_path'] = \$template->logo_path;")
        ->and((new PlanGenerator)->isFillable('logo_path'))->toBeTrue();
});

it('el pie de la página de cálculos dice «Plan generado por Integracorp» en todos los casos', function (): void {
    $body = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/partials/plan-generator-plan-body.blade.php');

    expect(substr_count($body, 'Plan generado por Integracorp'))->toBe(3)
        ->and($body)->not->toContain('Integracorp · Tu Dr en Casa · Plan generado');
});
