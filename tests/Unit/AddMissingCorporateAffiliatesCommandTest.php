<?php

declare(strict_types=1);

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\User;
use App\Support\AffiliationCorporates\CorporatePopulationMissingImporter as Importer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

/**
 * Crea afiliados de verdad: todo va en una transacción que siempre se revierte.
 */
beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

function padronCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'padron').'.csv';
    $handle = fopen($path, 'w');
    fputcsv($handle, ['NOMBRE_RIESGO', '1ER_APELLIDO_RIESGO', '2DO_APELLIDO_RIESGO', 'PARENTESCO', 'TIP_DOCUMENTO', 'CODIGO_DOCUMENTO']);
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    fclose($handle);

    return $path;
}

function repsolAffiliation(): AffiliationCorporate
{
    $affiliation = AffiliationCorporate::query()->where('code', 'TDEC-COR-00054')->first();

    if ($affiliation === null) {
        test()->markTestSkipped('No existe TDEC-COR-00054 en esta base.');
    }

    return $affiliation;
}

it('normaliza documentos sin perder el sufijo ni los ceros de la cédula de menor', function (): void {
    expect(Importer::documentKey('V-010.444.385'))->toBe('10444385')
        ->and(Importer::documentKey('00000184'))->toBe('184')
        ->and(Importer::storedDocument('023490882-25'))->toBe('023490882-25')
        ->and(Importer::storedDocument(' 15.320.349 '))->toBe('15320349');
});

it('lee el padrón como texto y exige las columnas', function (): void {
    $rows = Importer::readRows(padronCsv([['ana', 'zamora', '', 'CO', 'CIV', '014043761']]));

    expect($rows[0])->toMatchArray(['first_name' => 'ANA', 'last_name' => 'ZAMORA', 'document' => '014043761', 'relationship_code' => 'CO']);

    $bad = tempnam(sys_get_temp_dir(), 'padron').'.csv';
    file_put_contents($bad, "NOMBRE,CEDULA\nANA,1\n");

    expect(fn () => Importer::readRows($bad))->toThrow(RuntimeException::class, 'Faltan columnas');
});

it('no admite colectivos con varias filas de plan', function (): void {
    $multi = AffiliationCorporate::query()->whereHas('affiliationCorporatePlans', null, '>', 1)->first();

    if ($multi === null) {
        $this->markTestSkipped('No hay colectivos con varias filas de plan.');
    }

    expect(fn () => Importer::planRow($multi))->toThrow(RuntimeException::class, 'filas de plan');
});

it('decide por fila: existe por documento, posible duplicado por nombre, repetida o crear', function (): void {
    $affiliation = repsolAffiliation();
    $existing = AffiliateCorporate::query()->where('affiliation_corporate_id', $affiliation->id)->where('nro_identificacion', '!=', '')->whereRaw('LENGTH(nro_identificacion) >= 6')->firstOrFail();

    $rows = Importer::readRows(padronCsv([
        ['X', 'Y', '', 'TI', 'CIV', $existing->nro_identificacion],
        [$existing->first_name, $existing->last_name, '', 'HI', 'CIM', '099999999-01'],
        ['PERSONA', 'NUEVA', 'PRUEBA', 'TI', 'CIV', '98765431'],
        ['PERSONA', 'NUEVA', 'PRUEBA', 'TI', 'CIV', '98765431'],
        ['', '', '', 'TI', 'CIV', ''],
    ]));

    $actions = array_column(Importer::plan($affiliation, $rows), 'action');

    expect($actions)->toBe([
        Importer::ACTION_EXISTS,
        Importer::ACTION_NAME_MATCH,
        Importer::ACTION_CREATE,
        Importer::ACTION_REPEATED,
        Importer::ACTION_INVALID,
    ]);

    // Revisado a mano, el posible duplicado puede autorizarse.
    $forced = Importer::plan($affiliation, $rows, [$rows[1]['row']]);

    expect($forced[1]['action'])->toBe(Importer::ACTION_CREATE);
});

it('crea solo a los que faltan, sin fecha ni edad, con plan y tarifa del colectivo, y cuadra los totales', function (): void {
    $affiliation = repsolAffiliation();
    $planRow = Importer::planRow($affiliation);
    $before = Importer::totals($affiliation, $planRow);
    $user = User::query()->where('email', 'like', '%@tudrencasa.com')->where('is_superAdmin', 1)->firstOrFail();

    $rows = Importer::readRows(padronCsv([
        ['PERSONA', 'NUEVA', 'UNO', 'TI', 'CIV', '98765431'],
        ['HIJA', 'NUEVA', 'DOS', 'HI', 'CIM', '098765431-25'],
    ]));

    $result = Importer::execute($affiliation, Importer::plan($affiliation, $rows), $user, 'Prueba automatizada');

    expect($result['created'])->toHaveCount(2)
        ->and($result['after']['poblation'])->toBe($before['poblation'] + 2)
        ->and($result['after']['fee_anual'])->toBe(round($before['fee_anual'] + 2 * (float) $planRow->fee, 2))
        ->and($result['after']['plan_total_persons'])->toBe($before['plan_total_persons'] + 2);

    $child = AffiliateCorporate::query()->find($result['created'][1]);

    expect($child->nro_identificacion)->toBe('098765431-25')
        ->and($child->birth_date)->toBeNull()
        ->and($child->age)->toBeNull()
        ->and($child->relationship)->toBeNull()
        ->and($child->status)->toBe('ACTIVO')
        ->and((int) $child->plan_id)->toBe((int) $planRow->plan_id)
        ->and((float) $child->fee)->toBe((float) $planRow->fee)
        ->and((int) $child->created_by)->toBe($user->id);

    expect(DB::table('logs')->where('action', 'AUDIT_BUSINESS_CORPORATE_AFFILIATES_BULK_ADDED')->exists())->toBeTrue();

    // Idempotente: una segunda corrida con el mismo padrón no crea a nadie.
    $again = array_column(Importer::plan($affiliation->fresh(), $rows), 'action');

    expect($again)->toBe([Importer::ACTION_EXISTS, Importer::ACTION_EXISTS]);
});

it('la vista previa del comando no escribe en la base y --execute exige motivo y usuario', function (): void {
    Storage::fake('local');
    repsolAffiliation();
    $path = padronCsv([['PERSONA', 'NUEVA', 'UNO', 'TI', 'CIV', '98765431']]);
    $count = AffiliateCorporate::query()->count();

    $this->artisan('affiliations-corporate:add-missing', ['code' => 'TDEC-COR-00054', 'path' => $path])
        ->expectsOutputToContain('Vista previa: no se modificó nada')
        ->assertSuccessful();

    $this->artisan('affiliations-corporate:add-missing', ['code' => 'TDEC-COR-00054', 'path' => $path, '--execute' => true])
        ->expectsOutputToContain('son obligatorios --motivo y --usuario')
        ->assertFailed();

    $this->artisan('affiliations-corporate:add-missing', ['code' => 'TDEC-COR-00054', 'path' => $path, '--execute' => true, '--motivo' => 'x', '--usuario' => (string) User::query()->where('is_superAdmin', 1)->value('id')])
        ->expectsConfirmation('¿Crear 1 afiliados en TDEC-COR-00054 a nombre de '.User::query()->where('is_superAdmin', 1)->value('name').'?', 'no')
        ->assertFailed();

    expect(AffiliateCorporate::query()->count())->toBe($count);

    $this->artisan('affiliations-corporate:add-missing', ['code' => 'NO-EXISTE', 'path' => $path])
        ->expectsOutputToContain('No existe la afiliación')
        ->assertFailed();
});
