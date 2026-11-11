<?php

declare(strict_types=1);

use App\Enums\ClinicalUsageAccessContext;
use App\Enums\SystemOtpCodeKind;
use App\Models\Permission;
use App\Models\User;
use App\Support\ClinicalEntitlements\ClinicalUsageAccessOtp;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry as Registry;
use App\Support\Otp\SelfAuthorizedOtp;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    Mail::fake();
    Queue::fake();
    Illuminate\Support\Facades\Bus::fake([App\Jobs\SendNotificacionWhatsApp::class]);
});

afterEach(function (): void {
    DB::rollBack();
});

/**
 * @param  list<string>  $departments
 * @param  list<array{0: string, 1: string}>  $permissions  [slug, módulo]
 */
function saoUser(array $departments, array $permissions, int $id = 987940): User
{
    $user = User::factory()->make(['id' => $id, 'name' => 'Usuario Clave Propia Pest', 'email' => 'sao-pest-'.uniqid().'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => $departments]);

    return $user->setRelation('permissions', new Collection(array_map(fn (array $p): Permission => new Permission(['slug' => $p[0], 'module' => $p[1]]), $permissions)));
}

it('cada permiso vale solo para su tipo de clave y en su módulo', function (): void {
    $doctor = saoUser(['TELEMEDICINA'], [[Registry::SELF_OTP_CLINICAL_LIMIT_OVERRIDE, 'TELEMEDICINA']]);
    $wrongModule = saoUser(['NEGOCIOS'], [[Registry::SELF_OTP_CLINICAL_LIMIT_OVERRIDE, 'NEGOCIOS']], 987941);
    $analyst = saoUser(['NEGOCIOS'], [[Registry::SELF_OTP_CLINICAL_USAGE_ACCESS, 'NEGOCIOS']], 987942);

    expect(SelfAuthorizedOtp::userCan($doctor, SystemOtpCodeKind::ClinicalLimitOverride))->toBeTrue()
        ->and(SelfAuthorizedOtp::userCan($doctor, SystemOtpCodeKind::ClinicalUsageAccess))->toBeFalse()
        ->and(SelfAuthorizedOtp::userCan($wrongModule, SystemOtpCodeKind::ClinicalLimitOverride))->toBeFalse()
        ->and(SelfAuthorizedOtp::userCan($analyst, SystemOtpCodeKind::ClinicalUsageAccess))->toBeTrue()
        ->and(SelfAuthorizedOtp::userCan($analyst, SystemOtpCodeKind::PopulationApply))->toBeFalse()
        ->and(SelfAuthorizedOtp::userCan(null, SystemOtpCodeKind::PopulationApply))->toBeFalse()
        ->and(array_keys(Registry::all()))->toContain(Registry::SELF_OTP_POPULATION_APPLY, Registry::SELF_OTP_POPULATION_ROLLBACK, Registry::SELF_OTP_CLINICAL_USAGE_ACCESS, Registry::SELF_OTP_CLINICAL_LIMIT_OVERRIDE)
        ->and(Registry::all()[Registry::SELF_OTP_CLINICAL_LIMIT_OVERRIDE]['modules'])->toBe(['TELEMEDICINA']);
});

it('acceso a uso clínico: el analista con permiso entra con su propia clave y la clave sirve una sola vez', function (): void {
    $analyst = saoUser(['NEGOCIOS'], [[Registry::SELF_OTP_CLINICAL_USAGE_ACCESS, 'NEGOCIOS']]);
    $kind = SystemOtpCodeKind::ClinicalUsageAccess;

    $issued = ClinicalUsageAccessOtp::issue($analyst, ClinicalUsageAccessContext::PlanEdit, 26, 'Plan Repsol');
    $challenge = $issued['challenge'];

    $code = SelfAuthorizedOtp::resolveCode($challenge, $analyst, $kind, true, 'Ningún SUPERADMIN respondió y hay que corregir el cupo.', null);
    expect(ClinicalUsageAccessOtp::verify($challenge, $code, (int) $analyst->id))->toBeTrue();

    ClinicalUsageAccessOtp::markConsumed($challenge);
    SelfAuthorizedOtp::recordUse($kind, $challenge, $analyst, 'Ningún SUPERADMIN respondió y hay que corregir el cupo.', 'Plan Repsol', [], []);

    expect(fn () => SelfAuthorizedOtp::resolveCode($challenge->fresh(), $analyst, $kind, true, 'Ningún SUPERADMIN respondió y hay que corregir el cupo.', null))
        ->toThrow(RuntimeException::class, 'venció o se usó');

    Queue::assertPushed(App\Jobs\SendOtpSelfAuthorizationNoticeJob::class, fn ($job): bool => $job->emails !== [] || $job->phones !== []);
});

it('sin la casilla marcada se usa la clave escrita, aunque tenga el permiso', function (): void {
    $analyst = saoUser(['NEGOCIOS'], [[Registry::SELF_OTP_CLINICAL_USAGE_ACCESS, 'NEGOCIOS']]);
    $issued = ClinicalUsageAccessOtp::issue($analyst, ClinicalUsageAccessContext::PlanEdit, 26, 'Plan Repsol');

    expect(SelfAuthorizedOtp::resolveCode($issued['challenge'], $analyst, SystemOtpCodeKind::ClinicalUsageAccess, false, null, '123456'))->toBe('123456');
});
