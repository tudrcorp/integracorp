<?php

declare(strict_types=1);

use App\Models\TravelAgency;
use App\Support\TravelAgencies\TravelAgencyExternalCatalog;
use App\Support\TravelAgencies\TravelAgencyExternalIdAssigner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
});

afterEach(function (): void {
    DB::rollBack();
});

it('el catalogo trae 166 agencias con identificadores unicos y los cuatro renombres', function (): void {
    $rows = TravelAgencyExternalCatalog::rows();
    $renames = array_values(array_filter($rows, fn (array $row): bool => $row['rename']));

    expect($rows)->toHaveCount(166)
        ->and(collect($rows)->pluck('id_agencia')->unique())->toHaveCount(166)
        ->and(collect($rows)->pluck('id_de_agente')->unique())->toHaveCount(166)
        ->and(collect($rows)->pluck('name'))->not->toContain('David Viajes & Diseños', 'Justo a Tiempo', 'Viaja X Venezuela', 'Viaje sin Escala')
        ->and($renames)->toHaveCount(4)
        ->and(collect($renames)->pluck('name')->all())->toEqualCanonicalizing([
            'Agencia de Viajes Blue Paradise,C.A',
            "Nina's Travel Worldwide C.A",
            'LAN TRAVEL',
            'Betravelshop',
        ]);
});

it('normaliza mayusculas espacios y puntuacion del nombre', function (): void {
    expect(TravelAgencyExternalCatalog::normalizeName('AGENCIA DE VIAJES SAMARA'))
        ->toBe(TravelAgencyExternalCatalog::normalizeName('Agencia de Viajes Samara '))
        ->and(TravelAgencyExternalCatalog::normalizeName('Jassuyus Tours, C.A'))
        ->toBe(TravelAgencyExternalCatalog::normalizeName('Jassuyus Tours,C.A'))
        ->and(TravelAgencyExternalCatalog::normalizeName("Nina's Travel Worldwide C.A"))
        ->toBe(TravelAgencyExternalCatalog::normalizeName('Ninas Travel Worldwide C.A'));
});

it('asigna los identificadores y renombra las cuatro agencias sin crear filas nuevas', function (): void {
    if (! Schema::hasColumn('travel_agencies', 'id_agencia')) {
        $this->markTestSkipped('La columna id_agencia todavía no existe.');
    }

    $before = TravelAgency::query()->count();
    $sample = TravelAgency::query()->where('name', 'A Donde Alirio Online')->first();
    $blueParadise = TravelAgency::query()
        ->whereIn('name', ['Agencia de viajes Blue Paradise', 'Agencia de Viajes Blue Paradise,C.A'])
        ->first();

    expect($sample)->not->toBeNull()
        ->and($blueParadise)->not->toBeNull();

    $sample->forceFill([
        'id_agencia' => null,
        'id_de_agente' => null,
    ])->save();

    $blueParadise->forceFill([
        'name' => 'Agencia de viajes Blue Paradise',
        'id_agencia' => null,
        'id_de_agente' => null,
    ])->save();

    $result = TravelAgencyExternalIdAssigner::apply();

    $sample->refresh();
    $blueParadise->refresh();

    expect($result['skipped'])->toBe([])
        ->and($result['updated'])->toBeGreaterThan(0)
        ->and($sample->id_agencia)->toBe(958)
        ->and($sample->id_de_agente)->toBe('A344062')
        ->and($blueParadise->name)->toBe('Agencia de Viajes Blue Paradise,C.A')
        ->and($blueParadise->id_agencia)->toBe(915)
        ->and($blueParadise->id_de_agente)->toBe('A344019')
        ->and(TravelAgency::query()->count())->toBe($before);

    $second = TravelAgencyExternalIdAssigner::apply();

    expect($second['updated'])->toBe(0)
        ->and($second['renamed'])->toBe(0);
});

it('la migracion solo agrega columnas y no carga identificadores', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_09_23_092900_add_external_ids_to_travel_agencies_table.php');

    expect($source)
        ->toContain('id_agencia')
        ->toContain('id_de_agente')
        ->not->toContain('TravelAgencyExternalIdAssigner');
});

it('el comando sin apply no modifica agencias', function (): void {
    if (! Schema::hasColumn('travel_agencies', 'id_agencia')) {
        $this->markTestSkipped('La columna id_agencia todavía no existe.');
    }

    $sample = TravelAgency::query()->where('name', 'A Donde Alirio Online')->first();

    expect($sample)->not->toBeNull();

    $sample->forceFill([
        'id_agencia' => null,
        'id_de_agente' => null,
    ])->save();

    $this->artisan('travel-agencies:assign-external-ids')
        ->assertSuccessful()
        ->expectsOutputToContain('Simulación: no se escribió nada.');

    expect($sample->refresh()->id_agencia)->toBeNull()
        ->and($sample->id_de_agente)->toBeNull();
});

it('no pisa un identificador que ya existe', function (): void {
    if (! Schema::hasColumn('travel_agencies', 'id_agencia')) {
        $this->markTestSkipped('La columna id_agencia todavía no existe.');
    }

    $sample = TravelAgency::query()->where('name', 'A Donde Alirio Online')->first();

    expect($sample)->not->toBeNull();

    $sample->forceFill([
        'id_agencia' => 1,
        'id_de_agente' => 'OTRO',
    ])->save();

    $result = TravelAgencyExternalIdAssigner::apply();

    $sample->refresh();

    expect($sample->id_agencia)->toBe(1)
        ->and($sample->id_de_agente)->toBe('OTRO')
        ->and($result['conflicts'])->not->toBeEmpty();
});
