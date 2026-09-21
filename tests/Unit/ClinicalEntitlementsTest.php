<?php

declare(strict_types=1);

use App\Enums\ClinicalQuotaScope;
use App\Enums\ClinicalServiceChannel;
use App\Enums\SystemNotificationKey;
use App\Models\Benefit;
use App\Models\Limit;
use App\Support\ClinicalEntitlements\ClinicalConsultationConsumption;
use App\Support\ClinicalEntitlements\ClinicalEntitlement;
use App\Support\ClinicalEntitlements\ClinicalEntitlementSnapshot;
use App\Support\ClinicalEntitlements\ClinicalEntitlementWindow;
use App\Support\ClinicalEntitlements\ClinicalServiceOverrideOtp;
use App\Support\ClinicalEntitlements\OperationsAffiliatePlanBenefitsCard;
use App\Support\ClinicalEntitlements\PlanClinicalCompleteness;
use App\Support\ClinicalEntitlements\TelemedicineConsultationClinicalUi;
use Carbon\CarbonImmutable;

it('llama servicios macro al canal del select de la consulta', function (): void {
    expect(ClinicalServiceChannel::Type1->label())->toBe('Servicios Macro')
        ->and(ClinicalServiceChannel::Type1->shortLabel())->toBe('Macro')
        ->and(ClinicalServiceChannel::options()[ClinicalServiceChannel::Type1->value])->toBe('Servicios Macro');
});

it('expone la clave OTP en el centro de notificaciones', function (): void {
    expect(SystemNotificationKey::TelemedicineServiceLimitOverride->label())
        ->toBe('Autorización de servicio fuera de límite')
        ->and(SystemNotificationKey::managed())
        ->toContain(SystemNotificationKey::TelemedicineServiceLimitOverride)
        ->and(SystemNotificationKey::TelemedicineServiceLimitOverride->pausesScheduledTask())
        ->toBeFalse();
});

it('el asistente de planes pide uso clínico junto al beneficio', function (): void {
    $wizard = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Plans/Schemas/PlanWizardForm.php');

    expect($wizard)
        ->toContain('PlanBenefitClinicalFormSchema::fields()')
        ->toContain('PlanClinicalStructurePersistence::persistBenefitDefault')
        ->toContain("Repeater::make('package_benefits')")
        ->and(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Plans/Pages/CreatePlan.php'))
        ->toContain('PlanClinicalStructurePersistence::persist');
});

it('la pagina de uso clinico muestra el nombre del plan y no el codigo', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Plans/Pages/ManagePlanClinicalUsage.php');
    $resource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Plans/PlanResource.php');

    expect($page)
        ->toContain('$plan->description')
        ->and($resource)
        ->toContain("protected static ?string \$recordTitleAttribute = 'description'");
});

it('la persistencia comercial de planes no escribe el mapa clínico', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/Plans/PlanStructurePersistence.php');

    expect($source)
        ->not->toContain('PlanBenefitClinicalSetting')
        ->not->toContain('affiliate_clinical_service_usages');
});

it('telemedicina lista tipo 1 desde el mapa del plan', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php');

    expect($form)
        ->toContain('TelemedicineConsultationClinicalUi::type1Options()')
        ->toContain('self::complementsCheckboxList()')
        ->not->toContain("TelemedicineServiceList::where('level', 1)->get()->pluck('name', 'id')");
});

it('genera otp de 6 digitos y no deja excepcionar a proveedores', function (): void {
    $code = ClinicalServiceOverrideOtp::generateNumericCode(6);

    expect($code)->toHaveLength(6)->toMatch('/^\d{6}$/');

    $proveedor = new App\Models\User([
        'is_proveedor_amd' => true,
        'supplier_id' => 9,
        'departament' => ['OPERACIONES'],
    ]);

    expect(ClinicalServiceOverrideOtp::userMayOverride($proveedor))->toBeFalse();

    $atenmedi = new App\Models\User([
        'is_proveedor_amd' => false,
        'supplier_id' => null,
        'departament' => ['ATENMEDI', 'TELEMEDICINA'],
    ]);

    expect(ClinicalServiceOverrideOtp::userMayOverride($atenmedi))->toBeFalse();

    $tdg = new App\Models\User([
        'is_proveedor_amd' => false,
        'supplier_id' => null,
        'departament' => ['TELEMEDICINA'],
    ]);

    expect(ClinicalServiceOverrideOtp::userMayOverride($tdg))->toBeTrue();
});

it('calcula la ventana por aniversario de vigencia', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-27'));

    $window = ClinicalEntitlementWindow::currentAnniversaryWindow(CarbonImmutable::parse('2025-03-01'));

    expect($window['starts_at']->toDateString())->toBe('2026-03-01')
        ->and($window['ends_at']->toDateString())->toBe('2027-03-01');

    CarbonImmutable::setTestNow();
});

it('parsea la vigencia en d/m/Y de afiliaciones corporativas', function (): void {
    expect(ClinicalEntitlementWindow::parseToStartOfDay('20/07/2026')?->toDateString())->toBe('2026-07-20')
        ->and(ClinicalEntitlementWindow::parseToStartOfDay('2026-07-20')?->toDateString())->toBe('2026-07-20')
        ->and(ClinicalEntitlementWindow::parseToStartOfDay('20/07/2026 08:15:00')?->toDateString())->toBe('2026-07-20')
        ->and(ClinicalEntitlementWindow::parseToStartOfDay('no-es-fecha'))->toBeNull();

    $patient = new App\Models\TelemedicinePatient(['date_affiliation' => '20/07/2026']);
    $patient->setRelation('afilliation', null);
    $patient->setRelation('afilliationCorporate', null);

    expect(ClinicalEntitlementWindow::effectDate($patient)->toDateString())->toBe('2026-07-20');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-30'));
    $contract = ClinicalEntitlementWindow::forEffectDate(CarbonImmutable::parse('2025-03-01'), ClinicalQuotaScope::PerContract);
    $fromValues = ClinicalEntitlementWindow::effectDateFromValues(null, '20/07/2026', '2024-01-01');

    expect($contract['starts_at']->toDateString())->toBe('2025-03-01')
        ->and($contract['ends_at']->toDateString())->toBe('2026-03-01')
        ->and($fromValues->toDateString())->toBe('2026-07-20')
        ->and(ClinicalEntitlementWindow::forEffectDate($fromValues, ClinicalQuotaScope::Unlimited))->toBeNull();

    CarbonImmutable::setTestNow();
});

it('el helper de cupo agotado pide otp', function (): void {
    $entitlement = new ClinicalEntitlement(
        benefitId: 1,
        benefitLabel: 'LABORATORIOS A DOMICILIO CON FINES DIAGNÓSTICOS',
        channel: ClinicalServiceChannel::Laboratory,
        telemedicineServiceListId: null,
        telemedicineServiceListName: null,
        quotaScope: ClinicalQuotaScope::DistinctCases,
        quota: 2,
        used: 2,
        remaining: 0,
        exhausted: true,
    );

    expect($entitlement->helperText())
        ->toContain('Límite cubierto')
        ->toContain('autorización OTP');
});

it('resume el saldo en etiquetas cortas para la ficha de operaciones', function (): void {
    $agotado = new ClinicalEntitlement(
        benefitId: 1,
        benefitLabel: 'LABORATORIOS A DOMICILIO CON FINES DIAGNÓSTICOS',
        channel: ClinicalServiceChannel::Laboratory,
        telemedicineServiceListId: null,
        telemedicineServiceListName: null,
        quotaScope: ClinicalQuotaScope::DistinctCases,
        quota: 2,
        used: 2,
        remaining: 0,
        exhausted: true,
    );

    expect($agotado->operationsBalanceLabel())->toBe('Agotado')
        ->and($agotado->operationsCountLabel())->toBe('2 / 2 casos')
        ->and($agotado->operationsTone())->toBe('danger')
        ->and($agotado->displayName())->toBe('LABORATORIOS A DOMICILIO CON FINES DIAGNÓSTICOS');

    $card = App\Support\ClinicalEntitlements\OperationsClinicalQuotaCard::viewData(null);

    expect($card['tone'])->toBe('muted')
        ->and($card['rows'])->toBe([])
        ->and($card['message'])->toContain('No hay paciente');

    $emptyAffiliateCard = OperationsAffiliatePlanBenefitsCard::viewData(null);

    expect($emptyAffiliateCard['tone'])->toBe('muted')
        ->and($emptyAffiliateCard['rows'])->toBe([])
        ->and($emptyAffiliateCard['message'])->toContain('No hay afiliado');

    $emptyPatientCard = OperationsAffiliatePlanBenefitsCard::viewDataForPatient(null);

    expect($emptyPatientCard['tone'])->toBe('muted')
        ->and($emptyPatientCard['message'])->toContain('No hay paciente');

    $benefit = new Benefit(['description' => 'LABORATORIOS A DOMICILIO CON FINES DIAGNÓSTICOS']);
    $benefit->id = 1;
    $benefit->setRelation('limit', new Limit(['description' => 'HASTA 2 EVENTOS AL AÑO']));

    $unmapped = new Benefit(['description' => 'SEGUIMIENTO E INTERPRETACION DE RESULTADOS']);
    $unmapped->id = 8;
    $unmapped->setRelation('limit', new Limit(['description' => 'ILIMITADO']));

    $rows = OperationsAffiliatePlanBenefitsCard::rowsFrom(
        new ClinicalEntitlementSnapshot(
            hasPlan: true,
            isComplete: true,
            missingBenefitLabels: [],
            entitlements: [$agotado],
            blockingMessage: '',
        ),
        collect([$benefit, $unmapped]),
    );

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['label'])->toBe('LABORATORIOS A DOMICILIO CON FINES DIAGNÓSTICOS')
        ->and($rows[0]['limit'])->toBe('HASTA 2 EVENTOS AL AÑO')
        ->and($rows[0]['balance'])->toBe('Agotado')
        ->and($rows[0]['clinical'])->toBeTrue()
        ->and($rows[0]['channel'])->toBe('Laboratorio')
        ->and($rows[1]['balance'])->toBe('Sin mapa')
        ->and($rows[1]['clinical'])->toBeFalse();
});

it('la ficha de afiliados de operaciones une beneficios y uso clínico', function (): void {
    $resolver = file_get_contents(dirname(__DIR__, 2).'/app/Support/ClinicalEntitlements/AffiliateClinicalEntitlementResolver.php');
    $card = file_get_contents(dirname(__DIR__, 2).'/app/Support/ClinicalEntitlements/OperationsAffiliatePlanBenefitsCard.php');
    $individual = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Affiliates/Pages/ViewAffiliate.php');
    $corporate = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AffiliateCorporates/Pages/ViewAffiliateCorporate.php');

    expect($resolver)
        ->toContain('function forAffiliate')
        ->toContain('function forAffiliateCorporate')
        ->and($card)->toContain('rowsFrom')
        ->and($individual)->toContain('plan.benefitPlans.limit:id,description')
        ->and($corporate)->toContain('plan.clinicalSettings');
});

it('un plan nulo no está clínicamente completo', function (): void {
    expect(PlanClinicalCompleteness::isComplete(null))->toBeFalse()
        ->and(file_get_contents(dirname(__DIR__, 2).'/app/Support/ClinicalEntitlements/PlanClinicalCompleteness.php'))
        ->toContain('assignedBenefitIds($plan) === []');
});

it('no deja excepcionar a operaciones ni proveedores', function (): void {
    $operaciones = new App\Models\User([
        'is_proveedor_amd' => false,
        'supplier_id' => null,
        'is_doctor' => false,
        'departament' => ['OPERACIONES'],
    ]);

    expect(ClinicalServiceOverrideOtp::userMayOverride($operaciones))->toBeFalse();
});

it('deja visible la tilde de especialista aunque el plan no la contemple', function (): void {
    $ui = file_get_contents(dirname(__DIR__, 2).'/app/Support/ClinicalEntitlements/TelemedicineConsultationClinicalUi.php');
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php');
    $consumption = file_get_contents(dirname(__DIR__, 2).'/app/Support/ClinicalEntitlements/ClinicalConsultationConsumption.php');

    $sinEspecialista = new ClinicalEntitlementSnapshot(
        hasPlan: true,
        isComplete: true,
        missingBenefitLabels: [],
        entitlements: [
            new ClinicalEntitlement(
                benefitId: 1,
                benefitLabel: 'MEDICAMENTOS',
                channel: ClinicalServiceChannel::Medication,
                telemedicineServiceListId: null,
                telemedicineServiceListName: null,
                quotaScope: ClinicalQuotaScope::DistinctCases,
                quota: 2,
                used: 0,
                remaining: 2,
                exhausted: false,
            ),
        ],
        blockingMessage: '',
    );

    $conEspecialista = new ClinicalEntitlementSnapshot(
        hasPlan: true,
        isComplete: true,
        missingBenefitLabels: [],
        entitlements: [
            new ClinicalEntitlement(
                benefitId: 9,
                benefitLabel: 'INTERCONSULTA CON ESPECIALISTA',
                channel: ClinicalServiceChannel::Specialist,
                telemedicineServiceListId: null,
                telemedicineServiceListName: null,
                quotaScope: ClinicalQuotaScope::DistinctCases,
                quota: 1,
                used: 0,
                remaining: 1,
                exhausted: false,
            ),
        ],
        blockingMessage: '',
    );

    expect($ui)
        ->toContain('$key === self::SPECIALIST_COMPLEMENT_KEY || self::complementIsEnabled($key)')
        ->and($form)
        ->toContain('shouldNotifySpecialistNotContemplated')
        ->toContain('specialist_clinical_usage_notice')
        ->and($consumption)
        ->toContain('unmappedChannelMayProceedWithoutQuota')
        ->and(TelemedicineConsultationClinicalUi::specialistIsContemplatedIn($sinEspecialista))->toBeFalse()
        ->and(TelemedicineConsultationClinicalUi::specialistIsContemplatedIn($conEspecialista))->toBeTrue()
        ->and(TelemedicineConsultationClinicalUi::specialistIsContemplatedIn(new ClinicalEntitlementSnapshot(
            hasPlan: false,
            isComplete: false,
            missingBenefitLabels: [],
            entitlements: [],
            blockingMessage: '',
        )))->toBeTrue()
        ->and(TelemedicineConsultationClinicalUi::specialistComplementSelected([3]))->toBeTrue()
        ->and(TelemedicineConsultationClinicalUi::specialistComplementSelected([1, 2]))->toBeFalse()
        ->and(ClinicalConsultationConsumption::unmappedChannelMayProceedWithoutQuota(ClinicalServiceChannel::Specialist->value))->toBeTrue()
        ->and(ClinicalConsultationConsumption::unmappedChannelMayProceedWithoutQuota(ClinicalServiceChannel::Medication->value))->toBeFalse();
});

it('distingue laboratorio e imagen aunque compartan la tilde de complementos', function (): void {
    $channels = App\Support\ClinicalEntitlements\ClinicalConsultationConsumption::requestedChannels([
        'complements' => [2],
        'labs' => ['HEMOGRAMA'],
        'studies' => ['RX TORAX'],
    ]);

    expect($channels)
        ->toHaveKey(ClinicalServiceChannel::Laboratory->value)
        ->toHaveKey(ClinicalServiceChannel::Imaging->value)
        ->not->toHaveKey(ClinicalServiceChannel::Medication->value);
});

it('el otp queda en la misma ventana con reenvio a los 2 minutos', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');

    expect($page)
        ->toContain('#[Locked]')
        ->toContain('pendingClinicalOverridePublicId')
        ->toContain('$action->halt()')
        ->toContain('ClinicalServiceOverrideOtp::resend')
        ->toContain('handleRecordCreation')
        ->toContain('closeModalByClickingAway(false)');
});

it('la deriva tipo 1 sale del mapa del plan', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php');

    expect($form)
        ->toContain('TelemedicineConsultationClinicalUi::type1DriftOptions')
        ->toContain('isFollowUpServiceListId')
        ->not->toContain("->different('telemedicine_service_list_id')")
        ->not->toContain("TelemedicineServiceList::query()->where('level', 1)");
});

it('un seguimiento puede derivar a otro seguimiento', function (): void {
    $options = [
        1 => 'ATENCION MÉDICA TELEFONICA (TELEMEDICINA)',
        2 => 'AMD (ASISTENCIA MEDICA DOMICILIARIA)',
        4 => 'SEGUIMIENTO MÉDICO/LECTURA DE RESULTADOS',
        3 => 'TRASLADO EN AMBULANCIA',
    ];

    expect(TelemedicineConsultationClinicalUi::isFollowUpType1Service('SEGUIMIENTO MÉDICO/LECTURA DE RESULTADOS'))->toBeTrue()
        ->and(TelemedicineConsultationClinicalUi::isFollowUpType1Service('AMD (ASISTENCIA MEDICA DOMICILIARIA)'))->toBeFalse();

    $whenFollowUpSelected = TelemedicineConsultationClinicalUi::driftOptionsFromType1($options, 4);
    expect($whenFollowUpSelected)
        ->toHaveKey(4)
        ->toHaveKey(1)
        ->toHaveKey(2)
        ->toHaveKey(3);

    $whenTelemedicineSelected = TelemedicineConsultationClinicalUi::driftOptionsFromType1($options, 1);
    expect($whenTelemedicineSelected)
        ->not->toHaveKey(1)
        ->toHaveKey(4);

    $injected = TelemedicineConsultationClinicalUi::driftOptionsFromType1(
        [1 => 'ATENCION MÉDICA TELEFONICA (TELEMEDICINA)'],
        1,
        [4 => 'SEGUIMIENTO MÉDICO/LECTURA DE RESULTADOS'],
    );
    expect($injected)
        ->not->toHaveKey(1)
        ->toHaveKey(4)
        ->toBe([4 => 'SEGUIMIENTO MÉDICO/LECTURA DE RESULTADOS']);
});

it('el ledger no crea otro uso si el caso ya cuenta', function (): void {
    $ledger = file_get_contents(dirname(__DIR__, 2).'/app/Support/ClinicalEntitlements/ClinicalUsageLedger.php');

    expect($ledger)
        ->toContain('if (! $countsAsNewUnit)')
        ->toContain('return $existing');
});

it('el superadmin entra al uso clínico sin otp y el analista no', function (): void {
    $super = new App\Models\User([
        'departament' => ['SUPERADMIN', 'NEGOCIOS'],
    ]);
    $analista = new App\Models\User([
        'departament' => ['NEGOCIOS'],
    ]);

    expect(App\Support\ClinicalEntitlements\ClinicalUsageAccessOtp::userMayBypass($super))->toBeTrue()
        ->and(App\Support\ClinicalEntitlements\ClinicalUsageAccessOtp::userMayBypass($analista))->toBeFalse()
        ->and(App\Support\ClinicalEntitlements\ClinicalUsageAccessOtp::userMayBypass(null))->toBeFalse();
});

it('el otp de acceso clínico usa casillas individuales', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/forms/components/otp-boxes.blade.php');

    expect($view)
        ->toContain('ic-otp-boxes')
        ->toContain('one-time-code')
        ->toContain('onPaste')
        ->toContain('maxlength="1"');
});

it('el acceso clínico pide otp de superadmin y solo dura la visita', function (): void {
    $root = dirname(__DIR__, 2);
    $gate = file_get_contents($root.'/app/Filament/Business/Concerns/InteractsWithClinicalUsageAccessGate.php');
    $otp = file_get_contents($root.'/app/Support/ClinicalEntitlements/ClinicalUsageAccessOtp.php');
    $usage = file_get_contents($root.'/app/Filament/Business/Resources/Plans/Pages/ManagePlanClinicalUsage.php');
    $createPlan = file_get_contents($root.'/app/Filament/Business/Resources/Plans/Pages/CreatePlan.php');
    $editPlan = file_get_contents($root.'/app/Filament/Business/Resources/Plans/Pages/EditPlan.php');
    $createBenefit = file_get_contents($root.'/app/Filament/Business/Resources/Benefits/Pages/CreateBenefit.php');
    $editBenefit = file_get_contents($root.'/app/Filament/Business/Resources/Benefits/Pages/EditBenefit.php');
    $schema = file_get_contents($root.'/app/Support/ClinicalEntitlements/PlanBenefitClinicalFormSchema.php');

    expect($gate)
        ->toContain('#[Locked]')
        ->toContain('public bool $clinicalUsageUnlocked = false')
        ->toContain('Ambiente restrictivo de IntegraCorp')
        ->toContain("\$this->defaultAction = 'unlockClinicalUsage'")
        ->toContain('forceRender()')
        ->toContain('OtpBoxesInput::make(\'otp_code\')')
        ->not->toContain('session()->put')
        ->and($otp)
        ->toContain("where('departament', 'like', '%SUPERADMIN%')")
        ->toContain('UserNavigationAccess::isSuperAdmin')
        ->not->toContain('SystemNotificationRecipients')
        ->and($usage)
        ->toContain('InteractsWithClinicalUsageAccessGate')
        ->toContain('clinicalUsageAccessBlocksPage(): bool')
        ->toContain('assertClinicalUsageUnlocked')
        ->toContain('clinicalUsageIsUnlocked()')
        ->and($createPlan)
        ->toContain('InteractsWithClinicalUsageAccessGate')
        ->toContain('if (! $this->clinicalUsageIsUnlocked())')
        ->and($editPlan)
        ->toContain('InteractsWithClinicalUsageAccessGate')
        ->toContain('if ($this->clinicalUsageIsUnlocked())')
        ->and($createBenefit)
        ->toContain('InteractsWithClinicalUsageAccessGate')
        ->and($editBenefit)
        ->toContain('InteractsWithClinicalUsageAccessGate')
        ->and($schema)
        ->toContain('Uso clínico bloqueado')
        ->toContain('ClinicalUsageAccessOtp::allowsEditingOnCurrentPage()');
});
