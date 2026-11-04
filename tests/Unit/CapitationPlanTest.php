<?php

declare(strict_types=1);

use App\Enums\CapitationPaymentFrequency;
use App\Filament\Business\Resources\CapitationPlans\CapitationPlanResource;
use App\Filament\Business\Resources\CapitationPlans\Pages\CreateCapitationPlan;
use App\Filament\Business\Resources\CapitationPlans\Pages\EditCapitationPlan;
use App\Filament\Business\Resources\CapitationPlans\Pages\ListCapitationPlans;
use App\Filament\Business\Resources\CapitationPlans\Pages\ViewCapitationPlan;
use App\Models\Benefit;
use App\Models\CapitationPlan;
use App\Models\DoctorNurse;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Business\CapitationPlans\CapitationPlanWriter;
use App\Support\Business\CapitationPlans\CapitationProviders;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\Operations\OutsourcingMedicalDepartmentFields;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Escribe en base de datos: todo ocurre dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Filament::setCurrentPanel('business');
    test()->actingAs(User::factory()->make([
        'id' => 999_999_071,
        'name' => 'QA CAPITADO',
        'email' => 'qa.capitado@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'NEGOCIOS'],
    ]));
});

afterEach(fn () => DB::rollBack());

function proveedorOutsourcing(bool $outsourcing = true, bool $natural = false): Supplier|DoctorNurse
{
    $model = $natural ? DoctorNurse::class : Supplier::class;

    return $model::query()->create([
        'name' => 'QA CAPITADO '.uniqid(),
        'rif' => 'J-'.random_int(10000000, 99999999),
        'is_outsourcing_medical_department' => $outsourcing,
        'outsourcing_monthly_fee_per_affiliate_usd' => $outsourcing ? 12.5 : null,
    ]);
}

/**
 * @return array<string, mixed>
 */
function datosPlan(Supplier|DoctorNurse $provider, array $overrides = []): array
{
    $type = $provider instanceof DoctorNurse ? 'doctor_nurse' : 'supplier';

    return [
        'provider_key' => $type.':'.$provider->id,
        'name' => 'Plan atención primaria',
        'cost_per_affiliate_usd' => '60',
        'payment_frequency' => 'SEMESTRAL',
        'status' => CapitationPlan::STATUS_ACTIVE,
        'valid_from' => '2026-11-01',
        'valid_until' => null,
        'negotiation_type' => 'Tarifa fija por afiliado asignado, revisión semestral.',
        'benefits' => [
            ['benefit_id' => (int) Benefit::query()->where('status', 'ACTIVO')->value('id'), 'custom_name' => null, 'note' => 'Hasta 2 por año'],
            ['benefit_id' => null, 'custom_name' => '  Traslado   urbano  ', 'note' => null],
        ],
        'documents' => [],
        'document_names' => [],
        ...$overrides,
    ];
}

it('el selector solo ofrece proveedores de la red Outsourcing, jurídicos y naturales', function (): void {
    $juridico = proveedorOutsourcing();
    $natural = proveedorOutsourcing(natural: true);
    $fuera = proveedorOutsourcing(outsourcing: false);

    $options = CapitationProviders::search('QA CAPITADO');

    expect($options)->toHaveKey('supplier:'.$juridico->id)
        ->and($options)->toHaveKey('doctor_nurse:'.$natural->id)
        ->and($options)->not->toHaveKey('supplier:'.$fuera->id)
        ->and($options['doctor_nurse:'.$natural->id])->toStartWith('Natural · ');
});

it('guarda el plan con beneficios del catálogo y libres, en orden y con auditoría', function (): void {
    $provider = proveedorOutsourcing();
    $catalogName = (string) Benefit::query()->where('status', 'ACTIVO')->value('description');

    $plan = CapitationPlanWriter::save(null, datosPlan($provider), auth()->user());

    expect($plan->provider_type)->toBe('supplier')
        ->and((int) $plan->provider_id)->toBe($provider->id)
        ->and($plan->payment_frequency)->toBe(CapitationPaymentFrequency::Biannual)
        ->and($plan->monthlyEquivalent())->toBe(10.0)
        ->and($plan->benefits->pluck('name')->all())->toBe([trim($catalogName), 'Traslado urbano'])
        ->and($plan->benefits->first()->note)->toBe('Hasta 2 por año')
        ->and($plan->benefits->last()->benefit_id)->toBeNull()
        ->and($plan->created_by_name)->toBe('QA CAPITADO')
        ->and(DB::table('logs')->where('action', 'AUDIT_CAPITATION_PLAN_CREATED')->where('response', 'like', '%"capitation_plan_id":'.$plan->id.'%')->exists())->toBeTrue();
});

it('rechaza datos inválidos', function (array $overrides, string $field): void {
    $provider = proveedorOutsourcing();

    expect(fn () => CapitationPlanWriter::save(null, datosPlan($provider, $overrides), auth()->user()))
        ->toThrow(function (ValidationException $exception) use ($field): void {
            expect($exception->errors())->toHaveKey($field);
        });
})->with([
    'costo cero' => [['cost_per_affiliate_usd' => '0'], 'data.cost_per_affiliate_usd'],
    'frecuencia inventada' => [['payment_frequency' => 'QUINCENAL'], 'data.payment_frequency'],
    'vigencia al revés' => [['valid_from' => '2026-12-01', 'valid_until' => '2026-11-01'], 'data.valid_until'],
    'negociación corta' => [['negotiation_type' => 'corta'], 'data.negotiation_type'],
    'sin beneficios' => [['benefits' => []], 'data.benefits'],
    'beneficio repetido' => [['benefits' => [['benefit_id' => null, 'custom_name' => 'Consulta'], ['benefit_id' => null, 'custom_name' => 'CONSULTA']]], 'data.benefits'],
    'sin proveedor' => [['provider_key' => null], 'data.provider_key'],
]);

it('no acepta un proveedor fuera de Outsourcing, pero un plan existente lo conserva si después sale', function (): void {
    $fuera = proveedorOutsourcing(outsourcing: false);

    expect(fn () => CapitationPlanWriter::save(null, datosPlan($fuera), auth()->user()))
        ->toThrow(ValidationException::class);

    $provider = proveedorOutsourcing();
    $plan = CapitationPlanWriter::save(null, datosPlan($provider), auth()->user());
    $provider->forceFill(['is_outsourcing_medical_department' => false])->save();

    $updated = CapitationPlanWriter::save($plan, datosPlan($provider, ['cost_per_affiliate_usd' => '75']), auth()->user());

    expect((float) $updated->cost_per_affiliate_usd)->toBe(75.0)
        ->and(DB::table('logs')->where('action', 'AUDIT_CAPITATION_PLAN_UPDATED')->latest('id')->value('response'))->toContain('"cost_per_affiliate_usd":"60.00"');
});

it('no permite dos planes activos con el mismo nombre para el mismo proveedor', function (): void {
    $provider = proveedorOutsourcing();
    $other = proveedorOutsourcing();
    CapitationPlanWriter::save(null, datosPlan($provider), auth()->user());

    expect(fn () => CapitationPlanWriter::save(null, datosPlan($provider, ['name' => 'PLAN ATENCIÓN PRIMARIA']), auth()->user()))
        ->toThrow(ValidationException::class, 'ya tiene un plan activo');

    CapitationPlanWriter::save(null, datosPlan($provider, ['status' => CapitationPlan::STATUS_INACTIVE]), auth()->user());
    CapitationPlanWriter::save(null, datosPlan($other), auth()->user());

    expect(CapitationPlan::query()->forProvider('supplier', $provider->id)->count())->toBe(2);
});

it('eliminar es lógico y queda en la auditoría', function (): void {
    $plan = CapitationPlanWriter::save(null, datosPlan(proveedorOutsourcing()), auth()->user());

    CapitationPlanWriter::delete($plan, auth()->user());

    expect(CapitationPlan::query()->find($plan->id))->toBeNull()
        ->and(CapitationPlan::withTrashed()->find($plan->id))->not->toBeNull()
        ->and(DB::table('logs')->where('action', 'AUDIT_CAPITATION_PLAN_DELETED')->exists())->toBeTrue();
});

it('crea, ve y edita el plan desde las pantallas de Negocios', function (): void {
    $provider = proveedorOutsourcing();
    $benefitId = (int) Benefit::query()->where('status', 'ACTIVO')->value('id');

    Livewire::test(CreateCapitationPlan::class)
        ->set('data.provider_key', 'supplier:'.$provider->id)
        ->assertSee('Tarifa de referencia en su ficha')
        ->set('data.name', 'Plan domiciliario')
        ->set('data.cost_per_affiliate_usd', '30')
        ->set('data.payment_frequency', 'TRIMESTRAL')
        ->assertSee('≈ US$ 10,00 por mes')
        ->set('data.negotiation_type', 'Tarifa trimestral por afiliado asignado.')
        ->set('data.benefits', ['a' => ['benefit_id' => $benefitId, 'custom_name' => null, 'note' => null]])
        ->call('create')
        ->assertHasNoFormErrors();

    $plan = CapitationPlan::query()->forProvider('supplier', $provider->id)->sole();

    Livewire::test(ViewCapitationPlan::class, ['record' => $plan->id])
        ->assertSee('Plan domiciliario')
        ->assertSee('Trimestral (≈ US$ 10,00 por mes)');

    Livewire::test(EditCapitationPlan::class, ['record' => $plan->id])
        ->assertSet('data.provider_key', 'supplier:'.$provider->id)
        ->set('data.status', CapitationPlan::STATUS_INACTIVE)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($plan->fresh()->status)->toBe(CapitationPlan::STATUS_INACTIVE);

    Livewire::test(ListCapitationPlans::class)
        ->searchTable($provider->name)
        ->assertCanSeeTableRecords([$plan]);
});

it('la ficha del proveedor en Operaciones lista sus planes activos', function (): void {
    $provider = proveedorOutsourcing();
    CapitationPlanWriter::save(null, datosPlan($provider), auth()->user());

    $summary = (fn () => self::capitationPlansSummary($provider))->call(new OutsourcingMedicalDepartmentFields);

    expect((string) $summary)->toContain('Plan atención primaria')
        ->and((string) $summary)->toContain('US$ 60,00 por afiliado, semestral')
        ->and((string) $summary)->toContain('2 beneficios');
});

it('está en Negocios → CONFIGURACIÓN con su permiso de menú', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(CapitationPlanResource::class))->toBe(['planes-de-capitado'])
        ->and(DepartmentNavigationPermissionRegistry::moduleFor(CapitationPlanResource::class))->toBe('NEGOCIOS')
        ->and(CapitationPlanResource::getNavigationGroup())->toBe('CONFIGURACIÓN')
        ->and(CapitationPlanResource::getNavigationLabel())->toBe('Planes de Capitado');
});

it('guarda los documentos con su nombre original y la ficha los ofrece para abrir y descargar', function (): void {
    Illuminate\Support\Facades\Storage::fake('local');
    Illuminate\Support\Facades\Storage::disk('local')->put('capitation-plans/acuerdo.pdf', '%PDF-1.4 prueba');

    $plan = CapitationPlanWriter::save(null, datosPlan(proveedorOutsourcing(), [
        'documents' => ['capitation-plans/acuerdo.pdf', 'capitation-plans/no-existe.pdf'],
        'document_names' => ['capitation-plans/acuerdo.pdf' => 'Acuerdo firmado.pdf', 'capitation-plans/no-existe.pdf' => 'Anexo.pdf', 'otro.pdf' => 'Basura.pdf'],
    ]), auth()->user());

    expect($plan->documents)->toBe(['capitation-plans/acuerdo.pdf', 'capitation-plans/no-existe.pdf'])
        ->and($plan->document_names)->toBe(['capitation-plans/acuerdo.pdf' => 'Acuerdo firmado.pdf', 'capitation-plans/no-existe.pdf' => 'Anexo.pdf']);

    Livewire::test(ViewCapitationPlan::class, ['record' => $plan->id])
        ->assertSee('Acuerdo firmado.pdf')
        ->assertSee('Abrir')
        ->assertSee('Anexo.pdf — el archivo no está disponible.');
});

it('los beneficios se cargan en una tabla y elegir del catálogo limpia el texto libre de esa fila', function (): void {
    $benefitId = (int) Benefit::query()->where('status', 'ACTIVO')->value('id');

    $component = Livewire::test(CreateCapitationPlan::class)
        ->assertSee('Del catálogo')
        ->assertSee('O escriba el beneficio')
        ->assertSee('Nota')
        ->assertSee('Agregar beneficio o servicio');

    $key = array_key_first($component->get('data.benefits'));

    $component
        ->set("data.benefits.{$key}.custom_name", 'Texto que se reemplaza')
        ->set("data.benefits.{$key}.benefit_id", $benefitId)
        ->assertSet("data.benefits.{$key}.custom_name", null);

    $source = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/CapitationPlans/Schemas/CapitationPlanForm.php');

    expect($source)->toContain('->table([')
        ->and($source)->toContain("TableColumn::make('Del catálogo')")
        ->and($source)->toContain('->compact()');
});
