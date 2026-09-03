<?php

declare(strict_types=1);

use App\Enums\ClinicalServiceChannel;
use App\Support\ClinicalEntitlements\ClinicalQuotaFormGuard;

uses(Tests\TestCase::class);

/**
 * Doble mínimo de la página de creación: expone la propiedad de overrides
 * verificados y el método de acciones de cabecera que el guard exige.
 */
function fakeQuotaPage(array $verified = []): object
{
    return new class($verified)
    {
        public array $verifiedClinicalOverrideIds = [];

        public function __construct(array $verified)
        {
            $this->verifiedClinicalOverrideIds = $verified;
        }

        public function getHeaderActions(): array
        {
            return [];
        }
    };
}

it('solo aplica donde existe el botón de autorización OTP', function (): void {
    expect(ClinicalQuotaFormGuard::isEnforced(fakeQuotaPage()))->toBeTrue()
        ->and(ClinicalQuotaFormGuard::isEnforced(new stdClass))->toBeFalse()
        ->and(ClinicalQuotaFormGuard::isEnforced(null))->toBeFalse()
        ->and(ClinicalQuotaFormGuard::isEnforced('no es un componente'))->toBeFalse();
});

it('lee los canales con autorización OTP ya verificada', function (): void {
    $page = fakeQuotaPage([
        ClinicalServiceChannel::Medication->value => 12,
        ClinicalServiceChannel::Laboratory->value => 33,
    ]);

    expect(ClinicalQuotaFormGuard::verifiedChannels($page))
        ->toBe(['MEDICATION', 'LABORATORY']);

    expect(ClinicalQuotaFormGuard::verifiedChannels(new stdClass))->toBe([]);
});

it('no bloquea nada en pantallas sin el botón OTP', function (): void {
    expect(ClinicalQuotaFormGuard::isBlocked(new stdClass, ClinicalServiceChannel::Medication))->toBeFalse()
        ->and(ClinicalQuotaFormGuard::helperText(new stdClass, ClinicalServiceChannel::Medication))->toBeNull();
});

it('no bloquea sin paciente en sesión', function (): void {
    session()->forget('patient');

    $page = fakeQuotaPage();

    expect(ClinicalQuotaFormGuard::isBlocked($page, ClinicalServiceChannel::Medication))->toBeFalse()
        ->and(ClinicalQuotaFormGuard::exhaustedEntitlement(ClinicalServiceChannel::Medication))->toBeNull();
});

it('la regla no falla con el campo vacío', function (): void {
    $rule = ClinicalQuotaFormGuard::rule(fakeQuotaPage(), ClinicalServiceChannel::Laboratory);

    $failed = false;
    $fail = function () use (&$failed): void {
        $failed = true;
    };

    $rule('labs', null, $fail);
    $rule('labs', [], $fail);
    $rule('labs', '', $fail);

    expect($failed)->toBeFalse();
});

it('la regla de complementos solo mira la tilde de medicamentos', function (): void {
    $page = fakeQuotaPage();

    expect(ClinicalQuotaFormGuard::blockedComplementChannel($page, [2, 3]))->toBeNull()
        ->and(ClinicalQuotaFormGuard::blockedComplementChannel($page, []))->toBeNull()
        ->and(ClinicalQuotaFormGuard::blockedComplementChannel($page, null))->toBeNull();
});

it('el mensaje de bloqueo guía según quien pueda pedir la clave', function (): void {
    $entitlement = new App\Support\ClinicalEntitlements\ClinicalEntitlement(
        benefitId: 1,
        benefitLabel: 'Consulta',
        channel: ClinicalServiceChannel::Medication,
        telemedicineServiceListId: null,
        telemedicineServiceListName: null,
        quotaScope: App\Enums\ClinicalQuotaScope::PerAffiliationYear,
        quota: 2,
        used: 2,
        remaining: 0,
        exhausted: true,
    );

    // Sin usuario autenticado nadie puede pedir OTP: se indica escalar a TDG.
    expect(ClinicalQuotaFormGuard::message($entitlement))
        ->toContain('No puede cargarlo')
        ->toContain('escale el caso a TDG')
        ->not->toContain('de la cabecera');

    $doctor = new App\Models\User;
    $doctor->is_doctor = true;
    $doctor->departament = ['TELEMEDICINA'];
    Illuminate\Support\Facades\Auth::login($doctor);

    expect(ClinicalQuotaFormGuard::message($entitlement))
        ->toContain('No puede cargarlo')
        ->toContain('Autorizar servicio extra (OTP)')
        ->toContain('para poder continuar');

    Illuminate\Support\Facades\Auth::logout();
});

it('bloquea con el cupo cubierto sin mirar si el caso ya contaba', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/ClinicalEntitlements/ClinicalQuotaFormGuard.php');

    // Es deliberadamente más estricto que assertCanSave(), que sí deja pasar un uso
    // extra dentro de un caso ya contabilizado con alcance «En casos diferentes».
    expect($source)
        ->not->toContain('shouldConsumeForScope')
        ->toContain('! $entitlement->exhausted')
        ->toContain('$snapshot->forType1($serviceListId)')
        ->toContain('$snapshot->forChannel($channel)')
        ->toContain('! $snapshot->hasPlan || ! $snapshot->isComplete');
});

it('conecta el guard a los campos que consumen cupo', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php');

    // Servicios Macro en los dos pasos del asistente.
    expect(substr_count($form, 'ClinicalQuotaFormGuard::rule($livewire, ClinicalServiceChannel::Type1)'))->toBe(2);

    // Laboratorio, imagenología y especialista: cubiertos y no cubiertos.
    expect(substr_count($form, 'ClinicalQuotaFormGuard::rule($livewire, ClinicalServiceChannel::Laboratory)'))->toBe(2)
        ->and(substr_count($form, 'ClinicalQuotaFormGuard::rule($livewire, ClinicalServiceChannel::Imaging)'))->toBe(2)
        ->and(substr_count($form, 'ClinicalQuotaFormGuard::rule($livewire, ClinicalServiceChannel::Specialist)'))->toBe(2);

    // Medicamentos se valida en la tilde de complementos.
    expect($form)->toContain('ClinicalQuotaFormGuard::complementsRule($livewire)');

    // Aviso inmediato y texto fijo bajo el campo.
    expect(substr_count($form, 'ClinicalQuotaFormGuard::notifyIfBlocked'))->toBeGreaterThanOrEqual(9)
        ->and(substr_count($form, 'ClinicalQuotaFormGuard::helperText'))->toBeGreaterThanOrEqual(8);
});

it('el asistente valida el paso al avanzar, sin pasos saltables', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php');

    expect($form)->not->toContain('skippableSteps');
});
