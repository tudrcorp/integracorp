<?php

declare(strict_types=1);

use App\Models\ServiceProvider;
use App\Models\Supplier;
use App\Support\Affiliations\AffiliationServiceProvidersField;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

it('normaliza la lista a mayúsculas, sin vacíos ni repetidos', function (): void {
    expect(AffiliationServiceProvidersField::normalizeList(['atenmedi', ' ILS ', 'ATENMEDI', '', null, ['tdec']]))
        ->toBe(['ATENMEDI', 'ILS', 'TDEC'])
        ->and(AffiliationServiceProvidersField::normalizeList('["ATENMEDI", "ILS"]'))->toBe(['ATENMEDI', 'ILS'])
        ->and(AffiliationServiceProvidersField::normalizeList(null))->toBe([])
        ->and(AffiliationServiceProvidersField::normalizeList(15))->toBe([]);
});

it('lista ILS y TDEC siempre, más los alias de proveedores con gestión activa', function (): void {
    $withGestion = Supplier::query()->create([
        'name' => 'PROVEEDOR PRUEBA CON GESTION',
        'gestion_integracorp' => true,
        'integracorp_alias' => 'ZZPRUEBA ACTIVO',
    ]);
    Supplier::query()->create([
        'name' => 'PROVEEDOR PRUEBA SIN GESTION',
        'gestion_integracorp' => false,
        'integracorp_alias' => 'ZZPRUEBA INACTIVO',
    ]);

    $options = AffiliationServiceProvidersField::options();

    expect($options)
        ->toHaveKey('ILS', 'ILS')
        ->toHaveKey('TDEC', 'TDEC')
        ->toHaveKey('ZZPRUEBA ACTIVO', 'ZZPRUEBA ACTIVO')
        ->not->toHaveKey('ZZPRUEBA INACTIVO')
        ->and(array_keys($options))->toBe(array_values(array_unique(array_keys($options))))
        ->and($withGestion->exists)->toBeTrue();
});

it('conserva los valores ya guardados aunque no estén en el catálogo', function (): void {
    expect(AffiliationServiceProvidersField::options(['proveedor historico']))
        ->toHaveKey('PROVEEDOR HISTORICO');
});

it('crea el proveedor en el catálogo en mayúsculas y lo reutiliza si ya existe', function (): void {
    $before = ServiceProvider::query()->count();

    expect(AffiliationServiceProvidersField::createCatalogProvider('  zzprueba   nuevo '))->toBe('ZZPRUEBA NUEVO')
        ->and(AffiliationServiceProvidersField::createCatalogProvider('ZZPRUEBA NUEVO'))->toBe('ZZPRUEBA NUEVO')
        ->and(AffiliationServiceProvidersField::createCatalogProvider('zzprueba nuevo'))->toBe('ZZPRUEBA NUEVO')
        ->and(ServiceProvider::query()->count())->toBe($before + 1)
        ->and(AffiliationServiceProvidersField::options())->toHaveKey('ZZPRUEBA NUEVO');
});

it('rechaza crear un proveedor sin nombre', function (mixed $name): void {
    AffiliationServiceProvidersField::createCatalogProvider($name);
})->with([
    'vacío' => [''],
    'espacios' => ['   '],
    'nulo' => [null],
])->throws(InvalidArgumentException::class);

it('usa el campo compartido en los formularios individual y corporativo', function (): void {
    foreach ([
        'app/Filament/Business/Resources/Affiliations/Schemas/AffiliationForm.php',
        'app/Filament/Business/Resources/AffiliationCorporates/Schemas/AffiliationCorporateForm.php',
    ] as $path) {
        expect(file_get_contents(dirname(__DIR__, 2).'/'.$path))
            ->toContain('AffiliationServiceProvidersField::make()')
            ->not->toContain('ServiceProvider::all()');
    }
});
