<?php

declare(strict_types=1);

use App\Models\AffiliationCorporate;
use App\Support\AffiliationCorporates\CorporatePlanRowSync;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Conexión sqlite `:memory:` propia: este test escribe y nunca debe tocar la base real,
 * con o sin config cacheada.
 */
beforeEach(function (): void {
    config()->set('database.connections.plan_row_sync_testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    $this->previousConnection = config('database.default');
    config()->set('database.default', 'plan_row_sync_testing');
    DB::purge('plan_row_sync_testing');
    DB::setDefaultConnection('plan_row_sync_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:');

    Schema::create('affiliation_corporates', function (Blueprint $table): void {
        $table->id();
        $table->string('code')->nullable();
        $table->string('name_corporate')->nullable();
        $table->string('status')->nullable();
        $table->string('payment_frequency')->nullable();
        $table->decimal('fee_anual', 10, 2)->nullable();
        $table->timestamps();
        $table->timestamp('deleted_at')->nullable();
    });

    Schema::create('affiliate_corporates', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliation_corporate_id');
        $table->unsignedBigInteger('plan_id')->nullable();
        $table->unsignedBigInteger('coverage_id')->nullable();
        $table->string('age')->nullable();
        $table->decimal('fee', 10, 2)->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
        $table->timestamp('deleted_at')->nullable();
    });

    Schema::create('age_ranges', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('plan_id')->nullable();
        $table->string('range')->nullable();
        $table->integer('age_init')->nullable();
        $table->integer('age_end')->nullable();
        $table->timestamps();
    });

    Schema::create('afilliation_corporate_plans', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliation_corporate_id');
        $table->string('code_affiliation')->nullable();
        $table->unsignedBigInteger('plan_id');
        $table->unsignedBigInteger('coverage_id')->nullable();
        $table->unsignedBigInteger('age_range_id')->nullable();
        $table->decimal('fee', 8, 2);
        $table->string('fee_source', 20)->nullable();
        $table->integer('total_persons')->default(0);
        $table->string('payment_frequency')->nullable();
        $table->decimal('subtotal_anual', 10, 2)->default(0);
        $table->decimal('subtotal_quarterly', 10, 2)->default(0);
        $table->decimal('subtotal_biannual', 10, 2)->default(0);
        $table->decimal('subtotal_monthly', 10, 2)->nullable();
        $table->string('status')->nullable();
        $table->string('created_by')->nullable();
        $table->timestamps();
    });

    DB::table('age_ranges')->insert([
        ['id' => 2, 'plan_id' => 2, 'range' => '0 a 45', 'age_init' => 0, 'age_end' => 45],
        ['id' => 3, 'plan_id' => 2, 'range' => '46 a 65', 'age_init' => 46, 'age_end' => 65],
    ]);

    /** Réplica de ABP LECHERIA tras la renovación: afiliados a 189, fila del plan con 180 × 5. */
    DB::table('affiliation_corporates')->insert([
        'id' => 10, 'code' => 'TDEC-COR-00010', 'name_corporate' => 'ABP LECHERIA', 'status' => 'ACTIVA',
        'payment_frequency' => 'SEMESTRAL', 'fee_anual' => 945,
    ]);

    DB::table('afilliation_corporate_plans')->insert([
        'id' => 82, 'affiliation_corporate_id' => 10, 'code_affiliation' => 'TDEC-COR-00010', 'plan_id' => 2,
        'coverage_id' => 2, 'age_range_id' => 2, 'fee' => 189, 'fee_source' => null, 'total_persons' => 5,
        'payment_frequency' => 'SEMESTRAL', 'subtotal_anual' => 900, 'subtotal_quarterly' => 225,
        'subtotal_biannual' => 450, 'subtotal_monthly' => 78.75, 'status' => 'ACTIVA',
    ]);

    foreach ([86 => '33', 87 => '8', 88 => '41', 89 => '13', 90 => '9'] as $id => $age) {
        DB::table('affiliate_corporates')->insert([
            'id' => $id, 'affiliation_corporate_id' => 10, 'plan_id' => 2, 'coverage_id' => 2,
            'age' => $age, 'fee' => 189, 'status' => 'ACTIVO',
        ]);
    }
});

afterEach(function (): void {
    DB::purge('plan_row_sync_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
});

function planRow(int $id = 82): object
{
    return DB::table('afilliation_corporate_plans')->find($id);
}

function abpLecheria(): AffiliationCorporate
{
    return AffiliationCorporate::query()->findOrFail(10);
}

it('repara ABP LECHERIA: la línea del plan pasa a 472,50 semestral, igual que el total', function (): void {
    $report = CorporatePlanRowSync::sync(abpLecheria(), actor: 'Test');

    $row = planRow();

    expect((float) $row->fee)->toBe(189.0)
        ->and((int) $row->total_persons)->toBe(5)
        ->and((float) $row->subtotal_anual)->toBe(945.0)
        ->and((float) $row->subtotal_biannual)->toBe(472.5)
        ->and((float) $row->subtotal_quarterly)->toBe(236.25)
        ->and((float) $row->subtotal_monthly)->toBe(78.75)
        ->and($report['updated'])->toBe(1)
        ->and($report['created'])->toBe(0)
        ->and($report['unmatched'])->toBe([]);
});

it('en simulación no escribe nada pero informa el cambio', function (): void {
    $report = CorporatePlanRowSync::sync(abpLecheria(), persist: false);

    expect((float) planRow()->subtotal_biannual)->toBe(450.0)
        ->and($report['updated'])->toBe(1)
        ->and($report['rows'][0]['before']['annual'])->toBe(900.0)
        ->and($report['rows'][0]['after']['annual'])->toBe(945.0);
});

it('es idempotente: una segunda corrida no cambia nada', function (): void {
    CorporatePlanRowSync::sync(abpLecheria());

    $second = CorporatePlanRowSync::sync(abpLecheria());

    expect($second['updated'])->toBe(0)->and($second['created'])->toBe(0);
});

it('usa la tarifa del plan recalculada en la renovación, sin sumar upgrades', function (): void {
    /** Un afiliado con upgrade paga 229 (189 + 40), pero la fila del plan lleva 189. */
    DB::table('affiliate_corporates')->where('id', 86)->update(['fee' => 229]);

    $repriced = array_fill_keys([86, 87, 88, 89, 90], ['plan_fee' => 189.0, 'age_range_id' => 2]);

    CorporatePlanRowSync::sync(abpLecheria(), $repriced);

    expect((float) planRow()->fee)->toBe(189.0)
        ->and((float) planRow()->subtotal_anual)->toBe(945.0)
        ->and(planRow()->fee_source)->toBeNull();
});

it('sube la tarifa de la fila cuando la renovación cobra más y la marca como estándar', function (): void {
    DB::table('afilliation_corporate_plans')->where('id', 82)->update(['fee' => 180, 'fee_source' => 'NEGOCIADA']);

    $repriced = array_fill_keys([86, 87, 88, 89, 90], ['plan_fee' => 189.0, 'age_range_id' => 2]);

    CorporatePlanRowSync::sync(abpLecheria(), $repriced);

    expect((float) planRow()->fee)->toBe(189.0)
        ->and(planRow()->fee_source)->toBe('ESTANDAR')
        ->and((float) planRow()->subtotal_biannual)->toBe(472.5);
});

it('crea la fila del nuevo rango de edad y deja en cero la que se vacía, sin borrarla', function (): void {
    /** Todos cumplen 46+ en la renovación: pasan al rango 3 con otra tarifa. */
    $repriced = array_fill_keys([86, 87, 88, 89, 90], ['plan_fee' => 250.0, 'age_range_id' => 3]);

    $report = CorporatePlanRowSync::sync(abpLecheria(), $repriced, 'Test');

    $old = planRow();
    $new = DB::table('afilliation_corporate_plans')->where('age_range_id', 3)->first();

    expect($old)->not->toBeNull()
        ->and((int) $old->total_persons)->toBe(0)
        ->and((float) $old->subtotal_anual)->toBe(0.0)
        ->and($new)->not->toBeNull()
        ->and((int) $new->total_persons)->toBe(5)
        ->and((float) $new->fee)->toBe(250.0)
        ->and((float) $new->subtotal_biannual)->toBe(625.0)
        ->and($new->payment_frequency)->toBe('SEMESTRAL')
        ->and($new->fee_source)->toBe('ESTANDAR')
        ->and($new->created_by)->toBe('Test')
        ->and($report['created'])->toBe(1)
        ->and($report['emptied'])->toBe(1);
});

it('no cuenta afiliados inactivos ni excluidos', function (): void {
    DB::table('affiliate_corporates')->where('id', 90)->update(['status' => 'EXCLUIDO']);

    CorporatePlanRowSync::sync(abpLecheria());

    expect((int) planRow()->total_persons)->toBe(4)
        ->and((float) planRow()->subtotal_anual)->toBe(756.0);
});

it('reporta sin tocar al afiliado que no cae en ninguna fila', function (): void {
    DB::table('affiliate_corporates')->where('id', 88)->update(['age' => '70']);

    $report = CorporatePlanRowSync::sync(abpLecheria());

    expect($report['unmatched'])->toBe([88])
        ->and((int) planRow()->total_persons)->toBe(4);
});

it('la aceptación de renovación corporativa sincroniza las filas del plan en su transacción', function (): void {
    $source = file_get_contents(__DIR__.'/../../app/Services/AcceptAffiliationCorporateRenovationsService.php');

    expect($source)
        ->toContain('CorporatePlanRowSync::sync($affiliation, $repriced, $acceptedBy)')
        ->toContain('$repriced[(int) $affiliate->id] = self::repricedLine($amounts);');

    $syncPosition = strpos($source, 'CorporatePlanRowSync::sync(');
    $totalsPosition = strpos($source, '$this->recalculateAffiliationTotals($affiliation);');
    $collectionsPosition = strpos($source, 'createPendingCollectionsForCorporateRenewal(');

    expect($syncPosition)->toBeGreaterThan($totalsPosition)
        ->and($syncPosition)->toBeLessThan($collectionsPosition);
});
