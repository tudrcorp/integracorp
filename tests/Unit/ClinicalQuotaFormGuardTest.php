<?php

declare(strict_types=1);

use App\Enums\ClinicalQuotaScope;
use App\Enums\ClinicalServiceChannel;
use App\Models\TelemedicinePatient;
use App\Support\ClinicalEntitlements\ClinicalEntitlement;
use App\Support\ClinicalEntitlements\ClinicalEntitlementSnapshot;
use App\Support\ClinicalEntitlements\ClinicalQuotaFormGuard;
use App\Support\ClinicalEntitlements\TelemedicineConsultationClinicalUi;

uses(Tests\TestCase::class);

afterEach(function (): void {
    TelemedicineConsultationClinicalUi::flush();
    session()->forget('patient');
    Illuminate\Support\Facades\Auth::logout();
});

function quotaEntitlement(ClinicalServiceChannel $channel, bool $exhausted = true, ?int $serviceListId = null): ClinicalEntitlement
{
    return new ClinicalEntitlement(
        benefitId: crc32($channel->value.($serviceListId ?? '')),
        benefitLabel: $channel->shortLabel(),
        channel: $channel,
        telemedicineServiceListId: $serviceListId,
        telemedicineServiceListName: $serviceListId === null ? null : 'TELEMEDICINA',
        quotaScope: ClinicalQuotaScope::PerAffiliationYear,
        quota: 4,
        used: $exhausted ? 4 : 1,
        remaining: $exhausted ? 0 : 3,
        exhausted: $exhausted,
    );
}

/**
 * Deja en sesión un paciente (sin tocar la base) y su derecho clínico ya resuelto.
 *
 * @param  list<ClinicalEntitlement>  $entitlements
 */
function seedQuotaSnapshot(array $entitlements): void
{
    $patient = new TelemedicinePatient;
    $patient->id = 987654;
    session()->put('patient', $patient);

    $snapshot = new ClinicalEntitlementSnapshot(
        hasPlan: true,
        isComplete: true,
        missingBenefitLabels: [],
        entitlements: $entitlements,
        blockingMessage: '',
    );

    $reflection = new ReflectionClass(TelemedicineConsultationClinicalUi::class);
    $reflection->getProperty('cached')->setValue(null, $snapshot);
    $reflection->getProperty('cachedPatientId')->setValue(null, 987654);
}

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

it('con el cupo cubierto oculta lo cubierto y no bloquea fuera de servicios macro', function (ClinicalServiceChannel $channel): void {
    seedQuotaSnapshot([quotaEntitlement($channel)]);
    $page = fakeQuotaPage();

    expect(ClinicalQuotaFormGuard::coveredIsHidden($page, $channel))->toBeTrue()
        ->and(ClinicalQuotaFormGuard::isBlocked($page, $channel))->toBeFalse()
        ->and(ClinicalQuotaFormGuard::helperText($page, $channel))->toBeNull()
        ->and(ClinicalQuotaFormGuard::uncoveredOnlyNotice($page, $channel))
        ->toContain('Límite cubierto: 4 de 4 usos')
        ->toContain('no cubiertos por el plan')
        ->not->toContain('OTP');

    $failed = false;
    ClinicalQuotaFormGuard::rule($page, $channel)('campo', ['ALGO'], function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
})->with([
    'medicamentos' => [ClinicalServiceChannel::Medication],
    'laboratorio' => [ClinicalServiceChannel::Laboratory],
    'imagenología' => [ClinicalServiceChannel::Imaging],
    'especialista' => [ClinicalServiceChannel::Specialist],
]);

it('con cupo disponible no oculta nada', function (): void {
    seedQuotaSnapshot([quotaEntitlement(ClinicalServiceChannel::Laboratory, exhausted: false)]);
    $page = fakeQuotaPage();

    expect(ClinicalQuotaFormGuard::coveredIsHidden($page, ClinicalServiceChannel::Laboratory))->toBeFalse()
        ->and(ClinicalQuotaFormGuard::uncoveredOnlyNotice($page, ClinicalServiceChannel::Laboratory))->toBeNull()
        ->and(ClinicalQuotaFormGuard::coveredIsHidden($page, ClinicalServiceChannel::Imaging))->toBeFalse();
});

it('cada canal se evalúa por separado aunque compartan la tilde', function (): void {
    seedQuotaSnapshot([
        quotaEntitlement(ClinicalServiceChannel::Laboratory),
        quotaEntitlement(ClinicalServiceChannel::Imaging, exhausted: false),
    ]);
    $page = fakeQuotaPage();

    expect(ClinicalQuotaFormGuard::coveredIsHidden($page, ClinicalServiceChannel::Laboratory))->toBeTrue()
        ->and(ClinicalQuotaFormGuard::coveredIsHidden($page, ClinicalServiceChannel::Imaging))->toBeFalse();

    $notices = ClinicalQuotaFormGuard::uncoveredOnlyComplementNotices($page, [2]);

    expect($notices)->toHaveCount(1)
        ->and($notices[0])->toStartWith('Laboratorio:')
        ->toContain('laboratorios no cubiertos');
});

it('servicios macro sigue bloqueando con otp y nunca se oculta', function (): void {
    seedQuotaSnapshot([quotaEntitlement(ClinicalServiceChannel::Type1, serviceListId: 10)]);
    $page = fakeQuotaPage();

    expect(ClinicalQuotaFormGuard::coveredIsHidden($page, ClinicalServiceChannel::Type1))->toBeFalse()
        ->and(ClinicalQuotaFormGuard::isBlocked($page, ClinicalServiceChannel::Type1, 10))->toBeTrue()
        ->and(ClinicalQuotaFormGuard::helperText($page, ClinicalServiceChannel::Type1, 10))->toContain('autorización OTP');

    $failed = null;
    ClinicalQuotaFormGuard::rule($page, ClinicalServiceChannel::Type1)('telemedicine_service_list_id', 10, function (string $message) use (&$failed): void {
        $failed = $message;
    });

    expect($failed)->toContain('No puede cargarlo');

    $verificada = fakeQuotaPage([ClinicalServiceChannel::Type1->value => 5]);

    expect(ClinicalQuotaFormGuard::isBlocked($verificada, ClinicalServiceChannel::Type1, 10))->toBeFalse();
});

it('fuera de la pantalla de creación no oculta lo cubierto', function (): void {
    seedQuotaSnapshot([quotaEntitlement(ClinicalServiceChannel::Medication)]);

    expect(ClinicalQuotaFormGuard::coveredIsHidden(new stdClass, ClinicalServiceChannel::Medication))->toBeFalse()
        ->and(ClinicalQuotaFormGuard::uncoveredOnlyComplementNotices(new stdClass, [1, 2, 3]))->toBe([]);
});

it('los complementos se mapean a sus canales', function (): void {
    expect(ClinicalQuotaFormGuard::complementChannels(1))->toBe([ClinicalServiceChannel::Medication])
        ->and(ClinicalQuotaFormGuard::complementChannels(2))->toBe([ClinicalServiceChannel::Laboratory, ClinicalServiceChannel::Imaging])
        ->and(ClinicalQuotaFormGuard::complementChannels(3))->toBe([ClinicalServiceChannel::Specialist])
        ->and(ClinicalQuotaFormGuard::complementChannels(99))->toBe([])
        ->and(ClinicalQuotaFormGuard::uncoveredOnlyComplementNotices(fakeQuotaPage(), null))->toBe([]);
});

it('el mensaje de bloqueo guía según quien pueda pedir la clave', function (): void {
    $entitlement = new App\Support\ClinicalEntitlements\ClinicalEntitlement(
        benefitId: 1,
        benefitLabel: 'Consulta',
        channel: ClinicalServiceChannel::Type1,
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

    // Servicios Macro en los dos pasos del asistente: bloqueo con OTP.
    expect(substr_count($form, 'ClinicalQuotaFormGuard::rule($livewire, ClinicalServiceChannel::Type1)'))->toBe(2)
        ->and(substr_count($form, 'ClinicalQuotaFormGuard::notifyIfBlocked('))->toBe(2)
        ->and(substr_count($form, 'ClinicalQuotaFormGuard::helperText('))->toBe(2);

    // El resto ya no bloquea ni pide OTP: oculta lo cubierto con el cupo agotado.
    foreach (['Medication', 'Laboratory', 'Imaging', 'Specialist'] as $channel) {
        expect($form)
            ->not->toContain("ClinicalQuotaFormGuard::rule(\$livewire, ClinicalServiceChannel::{$channel})")
            ->toContain('ClinicalQuotaFormGuard::uncoveredOnlyNotice(');
    }

    expect($form)
        ->not->toContain('complementsRule')
        ->not->toContain('blockedComplementChannel')
        ->toContain('ClinicalQuotaFormGuard::coveredIsHidden($livewire, ClinicalServiceChannel::Laboratory)')
        ->toContain('ClinicalQuotaFormGuard::coveredIsHidden($livewire, ClinicalServiceChannel::Imaging)')
        ->toContain('ClinicalQuotaFormGuard::coveredIsHidden($livewire, ClinicalServiceChannel::Specialist)')
        ->toContain('ClinicalQuotaFormGuard::coveredIsHidden($schema->getLivewire(), ClinicalServiceChannel::Medication)')
        ->toContain('ClinicalQuotaFormGuard::notifyUncoveredOnlyComplements($livewire, $state, $old)');
});

it('con el cupo de medicamentos cubierto el listado solo deja «No cubierto»', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php');

    expect($form)
        ->toContain('$showMedicationInventory = ! $providerCoverage && ! $medicationUncoveredOnly;')
        ->toContain("\$showMedicationInventory ? TableColumn::make('Inventario TDC')")
        ->toContain('$medicationUncoveredOnly ? null : TableColumn::make($coveredColumnLabel)')
        ->toContain('...(! $showMedicationInventory ? [] : [')
        ->toContain('...($medicationUncoveredOnly ? [] : [')
        ->toContain('->required($medicationUncoveredOnly)')
        ->toContain('TelemedicineMedicationCoverage::rowIsCovered($row)');
});

it('la autorización otp de la cabecera solo ofrece servicios macro', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');

    expect($page)
        ->toContain('! $entitlement->exhausted || ! $entitlement->channel->requiresOverrideWhenExhausted()')
        ->toContain('return $entitlement?->channel->requiresOverrideWhenExhausted() ? $entitlement : null;')
        ->toContain('if ($channel === null || ! $channel->requiresOverrideWhenExhausted())');
});

it('el asistente valida el paso al avanzar, sin pasos saltables', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php');

    expect($form)->not->toContain('skippableSteps');
});
