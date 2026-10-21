<?php

declare(strict_types=1);

use App\Enums\SystemNotificationKey;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\CorporatePopulationOtpChallenge;
use App\Models\CorporatePopulationUpdate;
use App\Models\CorporatePopulationUpdateItem as Item;
use App\Models\SystemNotificationRecipientSetting;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\AffiliationCorporates\Population\CorporatePopulationOtp;
use App\Support\AffiliationCorporates\Population\PopulationColumnMapping;
use App\Support\AffiliationCorporates\Population\PopulationFileReader;
use App\Support\AffiliationCorporates\Population\PopulationPerson;
use App\Support\AffiliationCorporates\Population\PopulationUpdateAnalyzer;
use App\Support\AffiliationCorporates\Population\PopulationUpdateApplier;
use App\Support\AffiliationCorporates\Population\PopulationUpdateRollback;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

/**
 * Escriben en la base y en disco: transacción revertida, disco y correo simulados.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake('local');
    Mail::fake();
    Queue::fake();
});

afterEach(function (): void {
    DB::rollBack();
});

function popActor(): User
{
    return User::factory()->make(['id' => 987900, 'name' => 'Analista Población Pest']);
}

/**
 * Afiliación corporativa sintética clonada de una real, con un solo plan 0–99 años.
 *
 * @return array{owner: AffiliationCorporate, row: AfilliationCorporatePlan, a: AffiliateCorporate, b: AffiliateCorporate, c: AffiliateCorporate, d: AffiliateCorporate}
 */
function popFixture(): array
{
    $sourceRow = AfilliationCorporatePlan::query()->whereHas('ageRange', fn ($q) => $q->where('age_init', '<=', 1)->where('age_end', '>=', 90))->firstOrFail();
    $sourceOwner = AffiliationCorporate::query()->findOrFail($sourceRow->affiliation_corporate_id);

    $owner = $sourceOwner->replicate();
    $owner->forceFill(['code' => 'PEST-COR-'.Str::upper(Str::random(6)), 'name_corporate' => 'EMPRESA PEST', 'poblation' => 3, 'fee_anual' => 300, 'total_amount' => 75, 'payment_frequency' => 'TRIMESTRAL'])->save();

    $row = $sourceRow->replicate();
    $row->forceFill(['affiliation_corporate_id' => $owner->id, 'fee' => 100, 'total_persons' => 3])->save();

    $make = function (string $first, string $last, string $doc, ?string $birth, string $status, ?int $age) use ($owner, $row): AffiliateCorporate {
        $affiliate = new AffiliateCorporate;
        $affiliate->forceFill([
            'affiliation_corporate_id' => $owner->id, 'affiliation_type' => 'ESTANDARD', 'first_name' => $first, 'last_name' => $last,
            'nro_identificacion' => $doc, 'birth_date' => $birth, 'age' => $age, 'condition_medical' => 'SANO',
            'plan_id' => $row->plan_id, 'coverage_id' => $row->coverage_id, 'fee' => 100, 'subtotal_anual' => 100,
            'payment_frequency' => 'TRIMESTRAL', 'status' => $status, 'created_by' => 1,
        ])->save();

        return $affiliate;
    };

    return [
        'owner' => $owner,
        'row' => $row,
        'a' => $make('JUAN', 'PEREZ ROJAS', '11111111', '1/1/1980', 'ACTIVO', 46),
        'b' => $make('MARIA', 'LOPEZ', '22222222', '2/2/1985', 'ACTIVO', 41),
        'c' => $make('PEDRO', 'GOMEZ', '33333333', '3/3/1975', 'INACTIVO', 51),
        'd' => $make('ANA', 'PEREZ ROJAS', '12', '5/5/2015', 'ACTIVO', 11),
    ];
}

/**
 * CSV del cliente: JUAN coincide, PEDRO se reactiva, ANA (cédula del
 * representante con sufijo) coincide por nombre y fecha, CARLOS es nuevo,
 * JUAN repetido, MARIA no viene (baja).
 */
function popCsv(): string
{
    return implode("\n", [
        'CODIGO_DOCUMENTO;NOMBRE_RIESGO;1ER_APELLIDO_RIESGO;2DO_APELLIDO_RIESGO;NACIMIENTO;SEXO;PARENTESCO',
        '11111111;JUAN;PEREZ;ROJAS;01/01/1980;M;TI',
        '33333333;PEDRO;GOMEZ;;03/03/1975;M;TI',
        '011111111-15;ANA;PEREZ;ROJAS;05/05/2015;F;HI',
        '44444444;CARLOS;RUIZ;;02/02/1990;M;CO',
        '11111111;JUAN;PEREZ;ROJAS;01/01/1980;M;TI',
    ]);
}

function popUpdate(AffiliationCorporate $owner, ?string $content = null): CorporatePopulationUpdate
{
    $path = 'corporate-population/'.$owner->id.'/pest-'.Str::random(6).'.csv';
    Storage::disk('local')->put($path, $content ?? popCsv());
    $headers = PopulationFileReader::headers(Storage::disk('local')->path($path), 'csv');

    return CorporatePopulationUpdate::query()->create([
        'public_id' => (string) Str::uuid(),
        'affiliation_corporate_id' => $owner->id,
        'status' => CorporatePopulationUpdate::STATUS_ANALYZING,
        'mode' => 'FULL',
        'file_disk' => 'local',
        'file_path' => $path,
        'file_name' => 'poblacion.csv',
        'file_sha1' => sha1('x'),
        'file_format' => 'csv',
        'mapping' => PopulationColumnMapping::validate(PopulationColumnMapping::guess($headers), $headers),
        'analyzed_by' => 987900,
        'analyzed_by_name' => 'Analista Población Pest',
    ]);
}

function popItem(CorporatePopulationUpdate $update, string $action): Item
{
    return Item::query()->where('corporate_population_update_id', $update->id)->where('action', $action)->firstOrFail();
}

function popApply(CorporatePopulationUpdate $update): CorporatePopulationUpdate
{
    PopulationUpdateApplier::approve($update, popActor(), false);

    return PopulationUpdateApplier::apply($update->fresh());
}

it('lee CSV con punto y coma en Latin-1, TXT con tabuladores y Excel, sin perder ceros', function (): void {
    $dir = sys_get_temp_dir().'/pest-pop-'.Str::random(6);
    mkdir($dir);

    file_put_contents($dir.'/a.csv', mb_convert_encoding("CEDULA;NOMBRES;APELLIDOS\n010444385-16;JOSÉ;NUÑEZ\n", 'Windows-1252', 'UTF-8'));
    $csv = PopulationFileReader::read($dir.'/a.csv');
    expect($csv['headers'])->toBe(['CEDULA', 'NOMBRES', 'APELLIDOS'])
        ->and($csv['rows'][0]['values']['CEDULA'])->toBe('010444385-16')
        ->and($csv['rows'][0]['values']['APELLIDOS'])->toBe('NUÑEZ');

    file_put_contents($dir.'/b.txt', "CEDULA\tNOMBRES\n7873895\tEDGAR\n");
    expect(PopulationFileReader::read($dir.'/b.txt')['rows'][0]['values'])->toBe(['CEDULA' => '7873895', 'NOMBRES' => 'EDGAR']);

    $writer = new OpenSpout\Writer\XLSX\Writer;
    $writer->openToFile($dir.'/c.xlsx');
    $writer->addRow(OpenSpout\Common\Entity\Row::fromValues(['CODIGO_DOCUMENTO', 'NACIMIENTO']));
    $writer->addRow(OpenSpout\Common\Entity\Row::fromValues(['012392458-16', new DateTimeImmutable('2016-08-08')]));
    $writer->addRow(OpenSpout\Common\Entity\Row::fromValues([7873895.0, '']));
    $writer->close();

    $xlsx = PopulationFileReader::read($dir.'/c.xlsx');
    // Sin formato de fecha la celda llega como número de serie; la persona lo convierte.
    expect($xlsx['rows'][0]['values'])->toBe(['CODIGO_DOCUMENTO' => '012392458-16', 'NACIMIENTO' => '42590'])
        ->and(PopulationPerson::date($xlsx['rows'][0]['values']['NACIMIENTO']))->toBe('2016-08-08')
        ->and($xlsx['rows'][1]['values']['CODIGO_DOCUMENTO'])->toBe('7873895');

    file_put_contents($dir.'/d.csv', "SOLO_UNA_COLUMNA\nvalor\n");
    expect(fn () => PopulationFileReader::read($dir.'/d.csv'))->toThrow(RuntimeException::class, 'separador');
    expect(fn () => PopulationFileReader::read($dir.'/e.pdf'))->toThrow(RuntimeException::class);
});

it('sugiere el mapeo por encabezado y valida columnas obligatorias y repetidas', function (): void {
    $headers = ['CEDULA', 'NOMBRES', 'APELLIDOS', 'FECHA_NACIMIENTO', 'SEXO'];
    $mapping = PopulationColumnMapping::guess($headers);

    expect($mapping['document'])->toBe('CEDULA')
        ->and($mapping['first_name'])->toBe('NOMBRES')
        ->and($mapping['birth_date'])->toBe('FECHA_NACIMIENTO')
        ->and(fn () => PopulationColumnMapping::validate(['first_name' => 'NOMBRES'], $headers))->toThrow(InvalidArgumentException::class, 'Cédula')
        ->and(fn () => PopulationColumnMapping::validate(['document' => 'CEDULA', 'first_name' => 'CEDULA'], $headers))->toThrow(InvalidArgumentException::class, 'elegida')
        ->and(PopulationColumnMapping::suggest($headers, ['document' => 'NO_EXISTE']))->toBe($mapping);
});

it('normaliza fechas, cédulas y nombres dañados', function (): void {
    expect(PopulationPerson::date('5/5/2015'))->toBe('2015-05-05')
        ->and(PopulationPerson::date('05-05-2015'))->toBe('2015-05-05')
        ->and(PopulationPerson::date('42129'))->toBe('2015-05-05')
        ->and(PopulationPerson::date('1980'))->toBeNull()
        ->and(PopulationPerson::date('31/02/2015'))->toBeNull()
        ->and(PopulationPerson::documentKey('V-010.444.385'))->toBe('10444385')
        ->and(PopulationPerson::storedDocument('010444385-16'))->toBe('010444385-16')
        ->and(PopulationPerson::storedDocument('V-7.873.895'))->toBe('7873895')
        ->and(PopulationPerson::isMinorDocument('010444385-16'))->toBeTrue()
        ->and(PopulationPerson::similarity('KARLENA', 'CEDE?O CORDERO', 'KARLENA CEDEﾑO CORDERO'))->toBeGreaterThan(0.95)
        ->and(PopulationPerson::similarity('JOSE ALBERTO', 'CARMONA BADELL', 'CARMONA BADELL JOSE ALBERTO'))->toBeGreaterThan(0.95);
});

it('analiza sin tocar afiliados y propone cada acción', function (): void {
    $f = popFixture();
    $update = PopulationUpdateAnalyzer::analyze(popUpdate($f['owner']));

    expect($update->status)->toBe(CorporatePopulationUpdate::STATUS_ANALYZED)
        ->and($update->summary['counts'])->toMatchArray([
            Item::ACTION_MATCH => 2,
            Item::ACTION_REACTIVATE => 1,
            Item::ACTION_ADD => 1,
            Item::ACTION_DEACTIVATE => 1,
            Item::ACTION_DUPLICATE_FILE => 1,
        ]);

    $add = popItem($update, Item::ACTION_ADD);
    expect($add->selected)->toBeTrue()
        ->and($add->details['plan_row_id'])->toBe((int) $f['row']->id)
        ->and((float) $add->details['fee'])->toBe(100.0)
        ->and((int) popItem($update, Item::ACTION_DEACTIVATE)->affiliate_corporate_id)->toBe((int) $f['b']->id)
        ->and((int) popItem($update, Item::ACTION_REACTIVATE)->affiliate_corporate_id)->toBe((int) $f['c']->id);

    // Nada cambió en la afiliación.
    expect($f['b']->fresh()->status)->toBe('ACTIVO')
        ->and($f['c']->fresh()->status)->toBe('INACTIVO')
        ->and(AffiliateCorporate::query()->where('affiliation_corporate_id', $f['owner']->id)->count())->toBe(4);
});

it('no propone dar de baja a quien tiene un caso abierto y avisa que lo revise Operaciones', function (): void {
    $f = popFixture();

    $patient = TelemedicinePatient::query()->firstOrFail()->replicate();
    $patient->forceFill(['afilliation_corporate_id' => $f['owner']->id, 'afilliation_id' => null, 'type_affiliation' => 'CORPORATIVO', 'code_affiliation' => $f['owner']->code, 'nro_identificacion' => '22222222'])->saveQuietly();

    $case = TelemedicineCase::query()->firstOrFail()->replicate();
    $case->forceFill(['telemedicine_patient_id' => $patient->id, 'status' => 'EN SEGUIMIENTO', 'code' => 'PEST-'.Str::random(5)])->saveQuietly();

    $analystId = (int) User::query()->value('id');
    $pending = popUpdate($f['owner']);
    $pending->forceFill(['analyzed_by' => $analystId])->save();

    $update = PopulationUpdateAnalyzer::analyze($pending);
    $blocked = popItem($update, Item::ACTION_BLOCKED_OPEN_CASE);

    expect((int) $blocked->affiliate_corporate_id)->toBe((int) $f['b']->id)
        ->and($blocked->selectable)->toBeFalse()
        ->and($blocked->details['open_activity'][0])->toContain('EN SEGUIMIENTO');

    // Las notificaciones de Filament van en cola: se verifica el aviso encolado al analista.
    Queue::assertPushed(Illuminate\Notifications\SendQueuedNotifications::class, fn ($job): bool => $job->notifiables->contains('id', $analystId)
        && str_contains((string) ($job->notification->data['title'] ?? ''), 'casos abiertos')
        && str_contains((string) ($job->notification->data['body'] ?? ''), 'equipo de Operaciones'));
});

it('aplica todo junto, con foto previa, totales incrementales y paciente sincronizado', function (): void {
    $f = popFixture();

    $patient = TelemedicinePatient::query()->firstOrFail()->replicate();
    $patient->forceFill(['afilliation_corporate_id' => $f['owner']->id, 'afilliation_id' => null, 'type_affiliation' => 'CORPORATIVO', 'code_affiliation' => $f['owner']->code, 'nro_identificacion' => '22222222', 'status_affiliation' => 'ACTIVO'])->saveQuietly();

    $update = PopulationUpdateAnalyzer::analyze(popUpdate($f['owner']));
    $applied = popApply($update);

    expect($applied->status)->toBe(CorporatePopulationUpdate::STATUS_APPLIED)
        ->and($f['b']->fresh()->status)->toBe('INACTIVO')
        ->and($f['c']->fresh()->status)->toBe('ACTIVO')
        ->and($patient->fresh()->status_affiliation)->toBe('INACTIVO');

    $carlos = AffiliateCorporate::query()->where('affiliation_corporate_id', $f['owner']->id)->where('nro_identificacion', '44444444')->firstOrFail();
    expect($carlos->status)->toBe('ACTIVO')
        ->and($carlos->birth_date)->toBe('02/02/1990')
        ->and((int) $carlos->age)->toBeGreaterThanOrEqual(36)
        ->and($carlos->sex)->toBe('MASCULINO')
        ->and($carlos->relationship)->toBe('ESPOSO')
        ->and((float) $carlos->fee)->toBe(100.0)
        ->and((int) $carlos->plan_id)->toBe((int) $f['row']->plan_id);

    // +1 alta, +1 reactivación (100), −1 baja (100): población 3 → 4, tarifa 300 → 400.
    $owner = $f['owner']->fresh();
    expect((int) $owner->poblation)->toBe(4)
        ->and((float) $owner->fee_anual)->toBe(400.0)
        ->and((float) $owner->total_amount)->toBe(100.0)
        ->and((int) $f['row']->fresh()->total_persons)->toBe(4)
        ->and(popItem($applied, Item::ACTION_DEACTIVATE)->snapshot['affiliate']['status'])->toBe('ACTIVO')
        ->and(DB::table('logs')->where('action', 'AUDIT_CORPORATE_POPULATION_APPLIED')->where('response', 'like', '%"update_id":'.$applied->id.'%')->exists())->toBeTrue();
});

it('no aplica sobre datos viejos: si la población cambió después del análisis no toca nada', function (): void {
    $f = popFixture();
    $update = PopulationUpdateAnalyzer::analyze(popUpdate($f['owner']));

    $f['b']->forceFill(['status' => 'INACTIVO'])->save();

    $result = popApply($update);

    expect($result->status)->toBe(CorporatePopulationUpdate::STATUS_ANALYZED)
        ->and($result->error)->toContain('cambió después del análisis')
        ->and($f['c']->fresh()->status)->toBe('INACTIVO')
        ->and(AffiliateCorporate::query()->where('affiliation_corporate_id', $f['owner']->id)->count())->toBe(4)
        ->and((int) $f['owner']->fresh()->poblation)->toBe(3);
});

it('exige confirmación explícita para bajas masivas', function (): void {
    $f = popFixture();
    for ($i = 0; $i < 25; $i++) {
        $extra = $f['a']->replicate();
        $extra->forceFill(['first_name' => 'EXTRA'.$i, 'nro_identificacion' => (string) (50000000 + $i)])->save();
    }

    $update = PopulationUpdateAnalyzer::analyze(popUpdate($f['owner']));

    expect(fn () => PopulationUpdateApplier::approve($update, popActor(), false))->toThrow(RuntimeException::class, 'Confirme')
        ->and($update->fresh()->status)->toBe(CorporatePopulationUpdate::STATUS_ANALYZED);

    PopulationUpdateApplier::approve($update, popActor(), true);
    expect($update->fresh()->status)->toBe(CorporatePopulationUpdate::STATUS_APPLYING);
});

it('revierte al punto de partida: borra el alta, devuelve estatus y totales exactos', function (): void {
    $f = popFixture();
    $update = PopulationUpdateAnalyzer::analyze(popUpdate($f['owner']));
    $applied = popApply($update);

    PopulationUpdateRollback::start($applied, popActor(), 'La empresa mandó un archivo equivocado.');
    $rolled = PopulationUpdateRollback::rollback($applied->fresh());

    $owner = $f['owner']->fresh();
    expect($rolled->status)->toBe(CorporatePopulationUpdate::STATUS_ROLLED_BACK)
        ->and($rolled->rollback_summary['not_reverted'])->toBe([])
        ->and($f['b']->fresh()->status)->toBe('ACTIVO')
        ->and($f['c']->fresh()->status)->toBe('INACTIVO')
        ->and(AffiliateCorporate::query()->where('affiliation_corporate_id', $f['owner']->id)->where('nro_identificacion', '44444444')->exists())->toBeFalse()
        ->and((int) $owner->poblation)->toBe(3)
        ->and((float) $owner->fee_anual)->toBe(300.0)
        ->and((int) $f['row']->fresh()->total_persons)->toBe(3);
});

it('no revierte a quien ya tuvo actividad clínica después y solo permite revertir la última carga', function (): void {
    $f = popFixture();
    $update = PopulationUpdateAnalyzer::analyze(popUpdate($f['owner']));
    $applied = popApply($update);

    // PEDRO, reactivado, abre un caso después de aplicar.
    $patient = TelemedicinePatient::query()->firstOrFail()->replicate();
    $patient->forceFill(['afilliation_corporate_id' => $f['owner']->id, 'afilliation_id' => null, 'type_affiliation' => 'CORPORATIVO', 'code_affiliation' => $f['owner']->code, 'nro_identificacion' => '33333333'])->saveQuietly();
    $case = TelemedicineCase::query()->firstOrFail()->replicate();
    $case->forceFill(['telemedicine_patient_id' => $patient->id, 'status' => 'EN SEGUIMIENTO', 'code' => 'PEST-'.Str::random(5)])->saveQuietly();
    DB::table('telemedicine_cases')->where('id', $case->id)->update(['created_at' => now()->addMinute()]);

    // Una carga posterior aplicada bloquea revertir esta.
    $later = popUpdate($f['owner']);
    $later->forceFill(['status' => CorporatePopulationUpdate::STATUS_APPLIED, 'applied_at' => now()->addMinutes(2), 'approved_at' => now()->addMinutes(2)])->save();
    expect(PopulationUpdateRollback::blockingReason($applied->fresh()))->toContain('posterior');
    $later->forceFill(['status' => CorporatePopulationUpdate::STATUS_ROLLED_BACK])->save();

    PopulationUpdateRollback::start($applied->fresh(), popActor(), 'Se revierte para probar la actividad posterior.');
    $rolled = PopulationUpdateRollback::rollback($applied->fresh());

    expect($rolled->status)->toBe(CorporatePopulationUpdate::STATUS_ROLLED_BACK)
        ->and($f['c']->fresh()->status)->toBe('ACTIVO')
        ->and(collect($rolled->rollback_summary['not_reverted'])->pluck('reason')->implode(' '))->toContain('después de aplicar')
        ->and(popItem($rolled, Item::ACTION_REACTIVATE)->apply_status)->toBe(Item::APPLY_NOT_REVERTED)
        ->and($f['b']->fresh()->status)->toBe('ACTIVO');
});

it('la OTP va a los destinatarios del centro de notificaciones, es de un solo uso y por propósito', function (): void {
    $f = popFixture();
    $update = PopulationUpdateAnalyzer::analyze(popUpdate($f['owner']));
    $setting = SystemNotificationRecipientSetting::for(SystemNotificationKey::CorporatePopulationUpdate);

    $setting->forceFill(['notification_emails' => [], 'notification_phones' => []])->save();
    expect(fn () => CorporatePopulationOtp::issue(popActor(), $update, CorporatePopulationOtpChallenge::PURPOSE_APPLY))->toThrow(RuntimeException::class, 'destinatarios');

    $setting->forceFill(['notification_emails' => ['autorizador-pest@tudrencasa.com'], 'is_active' => true])->save();

    $code = null;
    $issued = CorporatePopulationOtp::issue(popActor(), $update, CorporatePopulationOtpChallenge::PURPOSE_APPLY);
    Mail::assertSent(App\Mail\CorporatePopulationOtpMail::class, function ($mail) use (&$code): bool {
        $code = $mail->emailPayload['otp_code'];

        return $mail->recipientEmail === 'autorizador-pest@tudrencasa.com';
    });

    $challenge = $issued['challenge'];
    expect(CorporatePopulationOtp::verifyAndConsume($challenge, $code, popActor(), $update, CorporatePopulationOtpChallenge::PURPOSE_ROLLBACK))->toBeFalse()
        ->and(CorporatePopulationOtp::verifyAndConsume($challenge->fresh(), $code === '000000' ? '111111' : '000000', popActor(), $update, CorporatePopulationOtpChallenge::PURPOSE_APPLY))->toBeFalse()
        ->and(CorporatePopulationOtp::verifyAndConsume($challenge->fresh(), $code, popActor(), $update, CorporatePopulationOtpChallenge::PURPOSE_APPLY))->toBeTrue()
        ->and(CorporatePopulationOtp::verifyAndConsume($challenge->fresh(), $code, popActor(), $update, CorporatePopulationOtpChallenge::PURPOSE_APPLY))->toBeFalse();

    $setting->forceFill(['is_active' => false])->save();
    expect(fn () => CorporatePopulationOtp::issue(popActor(), $update, CorporatePopulationOtpChallenge::PURPOSE_APPLY))->toThrow(RuntimeException::class, 'pausada');
});

it('registra la clave del centro de notificaciones y el permiso solo en Negocios', function (): void {
    $key = SystemNotificationKey::CorporatePopulationUpdate;

    expect(SystemNotificationKey::managed())->toContain($key)
        ->and($key->pausesScheduledTask())->toBeFalse()
        ->and(BusinessFilamentActionPermissionRegistry::modulesForSlug(BusinessFilamentActionPermissionRegistry::UPDATE_CORPORATE_POPULATION))->toBe(['NEGOCIOS']);
});

function popUser(array $departments): User
{
    return User::factory()->make([
        'id' => 987901,
        'name' => 'Usuario Población Pest',
        'email' => 'poblacion-pest-'.uniqid().'@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => $departments,
    ]);
}

it('las pantallas abren para quien tiene permiso y aprobar pide la OTP antes de aplicar', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');

    $f = popFixture();
    $update = PopulationUpdateAnalyzer::analyze(popUpdate($f['owner']));
    SystemNotificationRecipientSetting::for(SystemNotificationKey::CorporatePopulationUpdate)
        ->forceFill(['notification_emails' => ['autorizador-pest@tudrencasa.com'], 'is_active' => true])->save();

    Livewire\Livewire::actingAs(popUser(['SUPERADMIN', 'NEGOCIOS']))
        ->test(App\Filament\Business\Resources\AffiliationCorporates\Pages\CorporatePopulationUpdates::class, ['record' => $f['owner']->id])
        ->assertOk()
        ->assertSee('poblacion.csv');

    $code = null;
    $component = Livewire\Livewire::actingAs(popUser(['SUPERADMIN', 'NEGOCIOS']))
        ->test(App\Filament\Business\Resources\AffiliationCorporates\Pages\CorporatePopulationUpdateDetail::class, ['record' => $f['owner']->id, 'update' => $update->public_id])
        ->assertOk()
        ->assertSee('Lista para revisar')
        ->assertSee('CARLOS RUIZ')
        ->call('mountAction', 'approve')
        ->call('callMountedAction')
        ->assertNotified('Clave enviada');

    Mail::assertSent(App\Mail\CorporatePopulationOtpMail::class, function ($mail) use (&$code): bool {
        $code = $mail->emailPayload['otp_code'];

        return true;
    });

    expect($update->fresh()->status)->toBe(CorporatePopulationUpdate::STATUS_ANALYZED);

    $component
        ->set('mountedActions.0.data.otp_code', $code)
        ->call('callMountedAction')
        ->assertNotified('Aplicando la actualización');

    expect($update->fresh()->status)->toBe(CorporatePopulationUpdate::STATUS_APPLYING)
        ->and($update->fresh()->approved_by_name)->toBe('Usuario Población Pest');
    Queue::assertPushed(App\Jobs\ApplyCorporatePopulationUpdateJob::class);
});

it('sin el permiso las pantallas responden 403', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');

    $f = popFixture();

    Livewire\Livewire::actingAs(popUser(['NEGOCIOS']))
        ->test(App\Filament\Business\Resources\AffiliationCorporates\Pages\CorporatePopulationUpdates::class, ['record' => $f['owner']->id])
        ->assertForbidden();
});
