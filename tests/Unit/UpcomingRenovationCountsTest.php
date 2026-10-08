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
it('cuenta vencidas y tramos de 15, 30 y 45 días sin solaparse', function (string $model): void {
    $today = CarbonImmutable::today();
    $counts = UpcomingRenovationCounts::forQuery($model::query(), $today);

    expect(array_keys($counts))->toBe(['vencidas', 'd15', 'd30', 'd45']);

    foreach (UpcomingRenovationCounts::BUCKETS as $bucket) {
        expect($counts[$bucket])->toBe(UpcomingRenovationCounts::applyBucket($model::query(), $bucket, $today)->count());
    }

    $expected45 = $model::query()
        ->whereBetween('date_renewal', [$today->toDateString(), $today->addDays(45)->toDateString()])
        ->count();

    expect($counts['d15'] + $counts['d30'] + $counts['d45'])->toBe($expected45)
        ->and($counts['vencidas'])->toBe($model::query()->whereDate('date_renewal', '<', $today->toDateString())->count());
})->with([
    'individuales' => Renovation::class,
    'corporativas' => RenovationCorporate::class,
]);

it('respeta los filtros de la tabla: una consulta vacía da cero', function (): void {
    expect(UpcomingRenovationCounts::forQuery(Renovation::query()->whereRaw('1 = 0')))
        ->toBe(['vencidas' => 0, 'd15' => 0, 'd30' => 0, 'd45' => 0]);
});

it('los tramos son contiguos y no se pisan', function (): void {
    expect(UpcomingRenovationCounts::range('vencidas'))->toBe([null, -1])
        ->and(UpcomingRenovationCounts::range('d15'))->toBe([0, 15])
        ->and(UpcomingRenovationCounts::range('d30'))->toBe([16, 30])
        ->and(UpcomingRenovationCounts::range('d45'))->toBe([31, 45]);
});

it('rechaza un tramo desconocido', function (): void {
    UpcomingRenovationCounts::range('d90');
})->throws(InvalidArgumentException::class);

it('las dos tablas compartidas tienen el filtro «Renueva en» que usan las tarjetas', function (string $path): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/'.$path))
        ->toContain('Filter::make(UpcomingRenovationCounts::FILTER)')
        ->toContain('UpcomingRenovationCounts::applyBucket(');
})->with([
    'individuales' => 'Renovations/RenovationsTable.php',
    'corporativas' => 'RenovationCorporates/RenovationsCorporateTable.php',
]);

it('las tarjetas son botones que alternan el filtro sin contarse a sí mismas', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/Renovations/Concerns/ShowsUpcomingRenovationCounts.php');

    expect($source)
        ->toContain("'action' => \"toggleRenewalBucket('{\$bucket}')\"")
        ->toContain('public function toggleRenewalBucket(string $bucket): void')
        ->toContain('upcomingRenovationCountsIgnoringBucketFilter');
});

it('una tarjeta con acción se pinta como botón y la activa queda marcada', function (): void {
    $html = App\Support\Filament\SummaryCards::render([
        ['label' => 'Vencidas', 'value' => '3', 'color' => '#dc2626', 'action' => "toggleRenewalBucket('vencidas')", 'active' => true],
        ['label' => 'Sin acción', 'value' => '1', 'color' => '#3b82f6'],
    ])->toHtml();

    expect($html)
        ->toContain('<button type="button" wire:click="toggleRenewalBucket(&#039;vencidas&#039;)"')
        ->toContain('aria-pressed="true"')
        ->and(substr_count($html, '<button'))->toBe(1);
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
