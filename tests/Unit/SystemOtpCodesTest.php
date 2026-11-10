<?php

declare(strict_types=1);

use App\Enums\ClinicalUsageAccessContext;
use App\Enums\SystemOtpCodeKind;
use App\Filament\Business\Resources\SystemOtpCodes\Pages\ListSystemOtpCodes;
use App\Filament\Business\Resources\SystemOtpCodes\SystemOtpCodeResource;
use App\Jobs\SendNotificacionWhatsApp;
use App\Mail\ClinicalUsageAccessOtpMail;
use App\Models\ClinicalUsageAccessChallenge;
use App\Models\SystemOtpCode;
use App\Models\User;
use App\Support\ClinicalEntitlements\ClinicalUsageAccessOtp;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Otp\SystemOtpCodeRegistry;
use Carbon\Carbon;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(Tests\TestCase::class);

/**
 * Escribe en base de datos: todo dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Mail::fake();
    Bus::fake([SendNotificacionWhatsApp::class]);
});

afterEach(function (): void {
    Carbon::setTestNow();
    DB::rollBack();
});

/**
 * @return array{analyst: User, super: User}
 */
function usuariosClavesOtpQa(): array
{
    return [
        'analyst' => User::factory()->create([
            'name' => 'ANALISTA OTP QA',
            'email' => 'analista-otp-'.uniqid('', true).'@tudrencasa.com',
            'departament' => ['NEGOCIOS'],
            'status' => 'ACTIVO',
            'phone' => null,
        ]),
        'super' => User::factory()->create([
            'name' => 'SUPER OTP QA',
            'email' => 'super-otp-'.uniqid('', true).'@tudrencasa.com',
            'departament' => ['SUPERADMIN'],
            'status' => 'ACTIVO',
            'phone' => null,
        ]),
    ];
}

function codigoEnviadoQa(string $recipient): string
{
    $code = '';

    Mail::assertSent(ClinicalUsageAccessOtpMail::class, function (ClinicalUsageAccessOtpMail $mail) use ($recipient, &$code): bool {
        if ($mail->recipientEmail !== $recipient) {
            return false;
        }

        $code = (string) ($mail->emailPayload['otp_code'] ?? '');

        return true;
    });

    return $code;
}

function filaOtpQa(ClinicalUsageAccessChallenge $challenge): ?SystemOtpCode
{
    return SystemOtpCode::query()
        ->withReplacedFlag()
        ->with('challenge')
        ->where('challenge_type', $challenge->getMorphClass())
        ->where('challenge_id', $challenge->getKey())
        ->orderByDesc('id')
        ->first();
}

it('guarda una copia cifrada de la clave emitida, con quién la pidió, y la marca usada al validarla', function (): void {
    ['analyst' => $analyst, 'super' => $super] = usuariosClavesOtpQa();

    $issued = ClinicalUsageAccessOtp::issue($analyst, ClinicalUsageAccessContext::PlanUsage, 99, 'Plan QA');
    $code = codigoEnviadoQa($super->email);
    $row = filaOtpQa($issued['challenge']);

    expect($code)->toHaveLength(6)
        ->and($row)->not->toBeNull()
        ->and($row->kind)->toBe(SystemOtpCodeKind::ClinicalUsageAccess)
        ->and($row->code)->toBe($code)
        ->and(DB::table('system_otp_codes')->where('id', $row->id)->value('code'))->not->toBe($code)
        ->and($row->requested_by_user_id)->toBe($analyst->id)
        ->and($row->requested_by_name)->toBe('ANALISTA OTP QA')
        ->and($row->subject)->toContain('Plan QA')
        ->and($row->emails_sent)->toBeGreaterThan(0)
        ->and($row->status())->toBe(SystemOtpCode::STATUS_ACTIVE)
        ->and($row->toArray())->not->toHaveKey('code');

    expect(ClinicalUsageAccessOtp::verify($issued['challenge'], $code, (int) $analyst->id))->toBeTrue();
    ClinicalUsageAccessOtp::markConsumed($issued['challenge']);

    expect(filaOtpQa($issued['challenge'])->status())->toBe(SystemOtpCode::STATUS_USED);
});

it('un reenvío deja la clave anterior como reemplazada y la nueva vigente', function (): void {
    ['analyst' => $analyst] = usuariosClavesOtpQa();

    $issued = ClinicalUsageAccessOtp::issue($analyst, ClinicalUsageAccessContext::PlanUsage, 99, 'Plan QA');
    $first = filaOtpQa($issued['challenge']);

    Carbon::setTestNow(now()->addMinutes(3));
    ClinicalUsageAccessOtp::resend($issued['challenge']->fresh(), $analyst, ClinicalUsageAccessContext::PlanUsage, 99, 'Plan QA');

    $rows = SystemOtpCode::query()->withReplacedFlag()->with('challenge')
        ->where('challenge_id', $issued['challenge']->getKey())
        ->where('challenge_type', $issued['challenge']->getMorphClass())
        ->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->id)->toBe($first->id)
        ->and($rows[0]->status())->toBe(SystemOtpCode::STATUS_REPLACED)
        ->and($rows[1]->status())->toBe(SystemOtpCode::STATUS_ACTIVE)
        ->and($rows[1]->code)->not->toBe('');
});

it('calcula el estado: no entregada, vencida, intentos agotados y anulada', function (): void {
    $challenge = new ClinicalUsageAccessChallenge(['attempt_count' => 0, 'max_attempts' => 3]);
    $make = function (array $attributes, ?ClinicalUsageAccessChallenge $related) {
        $row = new SystemOtpCode($attributes + ['emails_sent' => 1, 'phones_sent' => 0, 'expires_at' => now()->addMinutes(3)]);
        $row->setRelation('challenge', $related);

        return $row;
    };

    expect($make(['emails_sent' => 0, 'phones_sent' => 0], $challenge)->status())->toBe(SystemOtpCode::STATUS_UNDELIVERED)
        ->and($make(['expires_at' => now()->subMinute()], $challenge)->status())->toBe(SystemOtpCode::STATUS_EXPIRED)
        ->and($make([], new ClinicalUsageAccessChallenge(['attempt_count' => 3, 'max_attempts' => 3]))->status())->toBe(SystemOtpCode::STATUS_LOCKED)
        ->and($make([], new ClinicalUsageAccessChallenge(['consumed_at' => now()]))->status())->toBe(SystemOtpCode::STATUS_CANCELLED)
        ->and($make([], null)->status())->toBe(SystemOtpCode::STATUS_CANCELLED)
        ->and($make(['used_at' => now()], null)->status())->toBe(SystemOtpCode::STATUS_USED)
        ->and($make([], $challenge)->status())->toBe(SystemOtpCode::STATUS_ACTIVE);
});

it('a las 24 horas la clave deja de verse y la limpieza la borra', function (): void {
    ['analyst' => $analyst] = usuariosClavesOtpQa();
    $issued = ClinicalUsageAccessOtp::issue($analyst, ClinicalUsageAccessContext::PlanUsage, 99, 'Plan QA');
    $row = filaOtpQa($issued['challenge']);

    $old = SystemOtpCode::query()->findOrFail($row->id)->replicate();
    $old->save();
    DB::table('system_otp_codes')->where('id', $old->id)->update(['created_at' => now()->subHours(SystemOtpCode::RETENTION_HOURS)->subMinute()]);

    expect(SystemOtpCode::query()->withinRetention()->pluck('id')->all())->toContain($row->id)->not->toContain($old->id)
        ->and((new SystemOtpCode)->prunable()->pluck('id')->all())->toContain($old->id)->not->toContain($row->id);

    (new SystemOtpCode)->pruneAll();

    expect(SystemOtpCode::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(SystemOtpCode::query()->whereKey($row->id)->exists())->toBeTrue();
});

it('guardar la copia nunca rompe la entrega de la clave', function (): void {
    SystemOtpCodeRegistry::record(SystemOtpCodeKind::PopulationApply, new ClinicalUsageAccessChallenge, '123456', null, null, null, [], 1, 1, now());
    SystemOtpCodeRegistry::record(SystemOtpCodeKind::PopulationApply, ClinicalUsageAccessChallenge::query()->make(['id' => 1]), '', null, null, null, [], 1, 1, now());
    SystemOtpCodeRegistry::markUsed(new ClinicalUsageAccessChallenge);

    expect(true)->toBeTrue();
});

it('las tres claves OTP del sistema dejan su copia y marcan el uso', function (string $path): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);

    expect($source)->toContain('SystemOtpCodeRegistry::record(')
        ->and($source)->toContain('SystemOtpCodeRegistry::markUsed($challenge)');
})->with([
    'población corporativa' => 'app/Support/AffiliationCorporates/Population/CorporatePopulationOtp.php',
    'servicio fuera de cupo' => 'app/Support/ClinicalEntitlements/ClinicalServiceOverrideOtp.php',
    'acceso a cupos clínicos' => 'app/Support/ClinicalEntitlements/ClinicalUsageAccessOtp.php',
]);

it('solo SUPERADMIN ve la pantalla y no aparece como permiso asignable', function (): void {
    ['analyst' => $analyst, 'super' => $super] = usuariosClavesOtpQa();

    test()->actingAs($analyst);
    expect(SystemOtpCodeResource::canAccess())->toBeFalse()
        ->and(SystemOtpCodeResource::shouldRegisterNavigation())->toBeFalse()
        ->and(SystemOtpCodeResource::getEloquentQuery()->count())->toBe(0);

    test()->actingAs($super);
    expect(SystemOtpCodeResource::canAccess())->toBeTrue()
        ->and(SystemOtpCodeResource::getNavigationGroup())->toBe('CONFIGURACIÓN')
        ->and(SystemOtpCodeResource::canCreate())->toBeFalse()
        ->and(DepartmentNavigationPermissionRegistry::isSuperAdminOnly(SystemOtpCodeResource::class))->toBeTrue();
});

it('la pantalla lista la clave enmascarada y al verla la muestra y la audita', function (): void {
    ['analyst' => $analyst, 'super' => $super] = usuariosClavesOtpQa();
    $issued = ClinicalUsageAccessOtp::issue($analyst, ClinicalUsageAccessContext::PlanUsage, 99, 'Plan QA');
    $code = codigoEnviadoQa($super->email);
    $row = filaOtpQa($issued['challenge']);

    Filament::setCurrentPanel('business');
    test()->actingAs($super);

    Livewire\Livewire::test(ListSystemOtpCodes::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$row])
        ->assertSee('ANALISTA OTP QA')
        ->assertSee('••• •••')
        ->assertDontSee($code)
        ->mountAction(TestAction::make('reveal')->table($row))
        ->assertActionMounted(TestAction::make('reveal')->table($row))
        ->assertMountedActionModalSee([$code, 'VIGENTE', 'ANALISTA OTP QA', 'Copiar clave']);

    expect(DB::table('logs')->where('action', 'AUDIT_SYSTEM_OTP_CODE_REVEALED')->where('response', 'like', '%"system_otp_code_id":'.$row->id.'%')->exists())->toBeTrue();
});
