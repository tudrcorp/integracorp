<?php

declare(strict_types=1);

use App\Models\Renovation;
use App\Models\RenovationCorporate;
use App\Support\Renovations\UpcomingRenovationCounts;
use Carbon\CarbonImmutable;

uses(Tests\TestCase::class);

/**
 * Solo lectura: cuenta sobre la base sin escribir nada.
 */
it('cuenta las renovaciones de los próximos 15, 30 y 45 días, acumuladas', function (string $model): void {
    $counts = UpcomingRenovationCounts::forQuery($model::query());

    expect(array_keys($counts))->toBe([15, 30, 45])
        ->and($counts[30])->toBeGreaterThanOrEqual($counts[15])
        ->and($counts[45])->toBeGreaterThanOrEqual($counts[30]);

    $expected45 = $model::query()
        ->whereBetween('date_renewal', [CarbonImmutable::today()->toDateString(), CarbonImmutable::today()->addDays(45)->toDateString()])
        ->count();

    expect($counts[45])->toBe($expected45);
})->with([
    'individuales' => Renovation::class,
    'corporativas' => RenovationCorporate::class,
]);

it('respeta los filtros de la tabla: una consulta vacía da cero', function (): void {
    expect(UpcomingRenovationCounts::forQuery(Renovation::query()->whereRaw('1 = 0')))
        ->toBe([15 => 0, 30 => 0, 45 => 0]);
});

it('las cuatro páginas de renovaciones muestran las tarjetas', function (string $path): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/'.$path))
        ->toContain('use ShowsUpcomingRenovationCounts;');
})->with([
    'administración individuales' => 'Administration/Resources/Renovations/Pages/ListRenovations.php',
    'administración corporativas' => 'Administration/Resources/RenovationCorporates/Pages/ListRenovationCorporates.php',
    'negocios individuales' => 'Business/Resources/Renovations/Pages/ListRenovations.php',
    'negocios corporativas' => 'Business/Resources/RenovationCorporates/Pages/ListRenovationCorporates.php',
]);
