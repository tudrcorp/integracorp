<?php

declare(strict_types=1);

use App\Enums\ClinicalUsageAccessContext;
use App\Jobs\SendNotificacionWhatsApp;
use App\Mail\ClinicalUsageAccessOtpMail;
use App\Models\ClinicalServiceOverrideChallenge;
use App\Models\ClinicalUsageAccessChallenge;
use App\Models\User;
use App\Support\ClinicalEntitlements\ClinicalEntitlementException;
use App\Support\ClinicalEntitlements\ClinicalUsageAccessOtp;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
});

afterEach(function (): void {
    DB::rollBack();
});

it('emite la otp solo a superadmin, la consume y no la reutiliza en otra visita', function (): void {
    Mail::fake();
    Bus::fake([SendNotificacionWhatsApp::class]);

    $analyst = User::factory()->create([
        'email' => 'analista-clinico-'.uniqid('', true).'@tudrencasa.com',
        'departament' => ['NEGOCIOS'],
        'status' => 'ACTIVO',
        'phone' => null,
    ]);

    $super = User::factory()->create([
        'email' => 'superadmin-clinico-'.uniqid('', true).'@tudrencasa.com',
        'departament' => ['SUPERADMIN'],
        'status' => 'ACTIVO',
        'phone' => null,
    ]);

    $issued = ClinicalUsageAccessOtp::issue(
        $analyst,
        ClinicalUsageAccessContext::PlanUsage,
        99,
        'Plan de prueba',
    );

    $code = null;
    Mail::assertSent(ClinicalUsageAccessOtpMail::class, function (ClinicalUsageAccessOtpMail $mail) use ($super, &$code): bool {
        if ($mail->recipientEmail !== $super->email) {
            return false;
        }

        $code = (string) ($mail->emailPayload['otp_code'] ?? '');

        return $mail->recipientEmail === $super->email && $code !== '';
    });

    expect($code)->toHaveLength(6)
        ->and($issued['challenge']->user_id)->toBe($analyst->id)
        ->and(ClinicalUsageAccessOtp::verify($issued['challenge'], $code, (int) $analyst->id))->toBeTrue();

    ClinicalUsageAccessOtp::markConsumed($issued['challenge']);

    expect($issued['challenge']->fresh()?->isConsumed())->toBeTrue()
        ->and(ClinicalUsageAccessOtp::verify($issued['challenge']->fresh(), $code, (int) $analyst->id))->toBeFalse();
});

it('el reenvio de otp devuelve segundos enteros en php 8.4', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-28 02:00:00'));

    try {
        $challenge = new ClinicalUsageAccessChallenge;
        $challenge->last_sent_at = now()->subSeconds(30);

        $override = new ClinicalServiceOverrideChallenge;
        $override->last_sent_at = now();

        expect($challenge->secondsUntilResend())->toBeInt()->toBe(90)
            ->and($override->secondsUntilResend())->toBeInt()->toBe(120);
    } finally {
        Carbon::setTestNow();
    }
});

it('un superadmin no solicita otp de acceso clínico', function (): void {
    $super = User::factory()->make([
        'departament' => ['SUPERADMIN'],
        'status' => 'ACTIVO',
    ]);
    $super->id = 1;

    expect(fn () => ClinicalUsageAccessOtp::issue($super, ClinicalUsageAccessContext::PlanCreate))
        ->toThrow(ClinicalEntitlementException::class, 'Un SUPERADMIN no necesita clave OTP para esta vista.');
});

it('rechaza una clave de otro analista', function (): void {
    $owner = User::factory()->create([
        'email' => 'owner-clinico-'.uniqid('', true).'@tudrencasa.com',
        'departament' => ['NEGOCIOS'],
        'status' => 'ACTIVO',
    ]);
    $intruso = User::factory()->create([
        'email' => 'intruso-clinico-'.uniqid('', true).'@tudrencasa.com',
        'departament' => ['NEGOCIOS'],
        'status' => 'ACTIVO',
    ]);

    $challenge = ClinicalUsageAccessChallenge::query()->create([
        'public_id' => (string) Str::uuid(),
        'user_id' => $owner->id,
        'context' => ClinicalUsageAccessContext::BenefitEdit->value,
        'context_record_id' => 7,
        'subject_label' => 'Beneficio prueba',
        'code_hash' => Hash::make('654321'),
        'expires_at' => now()->addMinutes(5),
        'attempt_count' => 0,
        'max_attempts' => 3,
        'last_sent_at' => now(),
    ]);

    expect(ClinicalUsageAccessOtp::verify($challenge, '654321', (int) $intruso->id))->toBeFalse()
        ->and(ClinicalUsageAccessOtp::verify($challenge->fresh(), '654321', (int) $owner->id))->toBeTrue();
});
