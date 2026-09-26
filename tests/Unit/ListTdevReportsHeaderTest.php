<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\TdevReports\Pages\ListTdevReports;
use Illuminate\Support\HtmlString;

it('el encabezado del reporte TDEV muestra el logo de Tu Doctor En Viajes', function (): void {
    $heading = (new ListTdevReports)->getHeading();

    expect($heading)->toBeInstanceOf(HtmlString::class)
        ->and((string) $heading)
        ->toContain('src="/image/logo-tdev.png"')
        ->toContain('alt="Tu Doctor En Viajes"')
        ->toContain('Reporte de TDEV')
        ->toContain('Importe el CSV oficial de TDEV')
        ->toContain('dark:text-white');
});

it('no repite la explicación como subtítulo aparte', function (): void {
    expect((new ListTdevReports)->getSubheading())->toBeNull();
});

it('conserva el título de la pestaña del navegador', function (): void {
    $title = (new ReflectionClass(ListTdevReports::class))->getStaticPropertyValue('title');

    expect($title)->toBe('Reporte de TDEV')
        ->and(is_file(public_path_for_tdev_header_test('image/logo-tdev.png')))->toBeTrue();
});

function public_path_for_tdev_header_test(string $path): string
{
    return dirname(__DIR__, 2).'/public/'.$path;
}
