<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\AffiliateCorporates\AffiliateCorporateResource;
use App\Filament\Operations\Resources\AffiliateCorporates\Pages\EditAffiliateCorporate;
use App\Jobs\NotifyAffiliationsTeamOfAffiliateUpdateJob;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\Permission;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Operations\AffiliatePersonalDataUpdater;
use App\Support\Operations\AffiliateUpdateNotificationMessage;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * Edición de datos personales de afiliados corporativos desde Operaciones.
 *
 * Escribe en la base dentro de una transacción que se revierte al terminar
 * cada test (DatabaseTransactions). Con la config cacheada `fillForm()` de
 * Filament no hace nada: los formularios se llenan con `set('data.…')`.
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

$basePath = dirname(__DIR__, 2);

function corporateFreeDocument(): string
{
    do {
        $document = (string) random_int(800000000, 899999999);
    } while (
        AffiliateCorporate::query()->where('nro_identificacion', $document)->exists()
        || TelemedicinePatient::query()->where('nro_identificacion', $document)->exists()
    );

    return $document;
}

/**
 * Datos con el desorden real de la tabla: sexo `M`, fecha sin ceros, teléfono
 * con guion y nombres en minúsculas.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeCorporateAffiliate(array $attributes = []): AffiliateCorporate
{
    $affiliate = new AffiliateCorporate;
    $affiliate->forceFill([
        'affiliation_corporate_id' => (int) AffiliationCorporate::query()->min('id'),
        'first_name' => 'Christofer Enrique',
        'last_name' => 'Urdaneta Fonseca',
        'nro_identificacion' => corporateFreeDocument(),
        'birth_date' => '7/5/1983',
        'age' => '43',
        'sex' => 'M',
        'phone' => '0424-8611567',
        'email' => 'Christofer@Example.com',
        'address' => 'Puerto La Cruz',
        'full_name_emergency' => 'Kimberly Coronado',
        'phone_emergency' => '0424-8692417',
        'status' => 'ACTIVO',
        'plan_id' => 1,
        'fee' => 50,
        'subtotal_anual' => 600,
        'payment_frequency' => 'MENSUAL',
        ...$attributes,
    ])->save();

    return $affiliate->refresh();
}

/**
 * @param  list<string>  $departments
 * @param  list<string>  $slugs
 */
function corporateOperationsAnalyst(array $departments = ['OPERACIONES'], array $slugs = []): User
{
    $user = new User;
    $user->forceFill([
        'id' => random_int(900000, 999999),
        'name' => 'Analista Operaciones',
        'email' => 'analista@tudrencasa.com',
        'departament' => $departments,
        'status' => 'ACTIVO',
    ]);

    $user->setRelation('permissions', collect($slugs)->map(fn (string $slug): Permission => tap(new Permission, fn (Permission $permission) => $permission->forceFill([
        'id' => random_int(900000, 999999),
        'name' => $slug,
        'slug' => $slug,
        'module' => 'OPERACIONES',
    ]))));

    return $user;
}

beforeEach(function (): void {
    Queue::fake([NotifyAffiliationsTeamOfAffiliateUpdateJob::class]);
});

it('guardar el formulario sin tocar nada no es un cambio aunque los datos estén en otro formato', function (): void {
    $affiliate = makeCorporateAffiliate();

    $result = AffiliatePersonalDataUpdater::update($affiliate, [
        'first_name' => 'CHRISTOFER ENRIQUE',
        'last_name' => 'URDANETA  FONSECA',
        'nro_identificacion' => $affiliate->nro_identificacion,
        'sex' => 'MASCULINO',
        'birth_date' => '07/05/1983',
        'phone' => '04248611567',
        'email' => 'christofer@example.com',
        'address' => 'PUERTO LA CRUZ',
        'full_name_emergency' => 'KIMBERLY CORONADO',
        'phone_emergency' => '04248692417',
    ], null);

    expect($result->hasChanges())->toBeFalse()
        ->and($affiliate->refresh()->sex)->toBe('M')
        ->and($affiliate->phone)->toBe('0424-8611567');

    Queue::assertNothingPushed();
});

it('guarda solo los datos personales e ignora plan, tarifa, subtotales, frecuencia y estado', function (): void {
    $affiliate = makeCorporateAffiliate();

    $result = AffiliatePersonalDataUpdater::update($affiliate, [
        'position_company' => 'Analista de compras',
        'plan_id' => 999,
        'fee' => 1,
        'subtotal_anual' => 1,
        'payment_frequency' => 'ANUAL',
        'status' => 'INACTIVO',
        'relationship' => 'TITULAR',
        'condition_medical' => 'X',
        'affiliation_corporate_id' => 999999,
    ], null);

    $fresh = $affiliate->refresh();

    expect(array_keys($result->changes))->toBe(['position_company'])
        ->and($fresh->position_company)->toBe('ANALISTA DE COMPRAS')
        ->and((int) $fresh->plan_id)->toBe(1)
        ->and((float) $fresh->fee)->toBe(50.0)
        ->and((float) $fresh->subtotal_anual)->toBe(600.0)
        ->and($fresh->payment_frequency)->toBe('MENSUAL')
        ->and($fresh->status)->toBe('ACTIVO')
        ->and($fresh->condition_medical)->toBeNull();
});

it('un cambio real de sexo se guarda en forma canónica', function (): void {
    $affiliate = makeCorporateAffiliate();

    $result = AffiliatePersonalDataUpdater::update($affiliate, ['sex' => 'FEMENINO'], null);

    expect($result->changes['sex']['before'])->toBe('M')
        ->and($affiliate->refresh()->sex)->toBe('FEMENINO');
});

it('actualiza el paciente de telemedicina: nombre armado, cédula, correo y teléfono', function (): void {
    $affiliate = makeCorporateAffiliate();
    $patient = new TelemedicinePatient;
    $patient->forceFill([
        'full_name' => 'CHRISTOFER ENRIQUE URDANETA FONSECA',
        'nro_identificacion' => $affiliate->nro_identificacion,
        'sex' => 'MASCULINO',
        'address' => 'DIRECCION PROPIA DE TELEMEDICINA',
    ])->save();
    $newDocument = corporateFreeDocument();

    $result = AffiliatePersonalDataUpdater::update($affiliate, [
        'first_name' => 'Christopher',
        'nro_identificacion' => $newDocument,
        'email' => 'nuevo@example.com',
        'phone' => '0414-1112233',
    ], null);

    $patient->refresh();

    expect($result->telemedicineSynced)->toBeTrue()
        ->and($patient->full_name)->toBe('CHRISTOPHER URDANETA FONSECA')
        ->and($patient->nro_identificacion)->toBe($newDocument)
        ->and($patient->email)->toBe('nuevo@example.com')
        ->and($patient->phone)->toBe('04141112233')
        ->and($patient->address)->toBe('DIRECCION PROPIA DE TELEMEDICINA');
});

it('bloquea una cédula de otro paciente de telemedicina sin guardar nada', function (): void {
    $affiliate = makeCorporateAffiliate();
    $otherDocument = corporateFreeDocument();
    $other = new TelemedicinePatient;
    $other->forceFill(['full_name' => 'OTRA PERSONA', 'nro_identificacion' => $otherDocument, 'sex' => 'FEMENINO'])->save();

    expect(fn () => AffiliatePersonalDataUpdater::update($affiliate, [
        'nro_identificacion' => $otherDocument,
        'first_name' => 'NO DEBE GUARDARSE',
    ], null))->toThrow(ValidationException::class, 'OTRA PERSONA');

    expect($affiliate->refresh()->first_name)->toBe('Christofer Enrique');
    Queue::assertNothingPushed();
});

it('el aviso identifica al afiliado corporativo, su empresa y enlaza a su ficha', function (): void {
    $affiliate = makeCorporateAffiliate();

    AffiliatePersonalDataUpdater::update($affiliate, ['phone_emergency' => '0412-0000000'], null);

    Queue::assertPushed(NotifyAffiliationsTeamOfAffiliateUpdateJob::class, function (NotifyAffiliationsTeamOfAffiliateUpdateJob $job) use ($affiliate): bool {
        $payload = $job->payload;

        return $payload['kind'] === AffiliatePersonalDataUpdater::KIND_CORPORATE
            && $payload['kind_label'] === 'corporativo'
            && $payload['affiliate_name'] === 'Christofer Enrique Urdaneta Fonseca'
            && $payload['titular_note'] === null
            && $payload['age_range_warning'] === null
            && str_contains((string) $payload['url'], '/operations/affiliate-corporates/'.$affiliate->id)
            && $payload['changes'][0]['label'] === 'Teléfono de emergencia';
    });
});

it('el WhatsApp y el asunto dicen que es corporativo y nombran la empresa', function (): void {
    $payload = [
        'kind_label' => 'corporativo',
        'affiliate_name' => 'MARIA PEREZ',
        'affiliation_code' => 'TDEC-COR-1',
        'company' => 'EMPRESA DEMO C.A.',
        'changes' => [],
    ];

    expect(AffiliateUpdateNotificationMessage::whatsappBody($payload))
        ->toContain('afiliado corporativo')
        ->toContain('• Empresa: EMPRESA DEMO C.A.')
        ->and(AffiliateUpdateNotificationMessage::emailSubject($payload))
        ->toStartWith('Afiliado corporativo actualizado por Operaciones');
});

it('la dirección fijada con el mapa del corporativo pasa por el mismo updater', function () use ($basePath): void {
    $trait = file_get_contents($basePath.'/app/Filament/Operations/Concerns/AppliesOperationsAddressFromMaps.php');

    preg_match('/function applyAffiliateCorporateLocationFromMaps\(.*?\n    \}/s', $trait, $method);

    expect($method[0] ?? '')
        ->toContain('AffiliatePersonalDataUpdater::update(')
        ->toContain('AffiliatePersonalDataUpdater::SOURCE_MAPS_ADDRESS')
        ->not->toContain('$record->save()');
});

it('registra el permiso de edición corporativa solo en Operaciones', function (): void {
    $slug = BusinessFilamentActionPermissionRegistry::EDIT_CORPORATE_AFFILIATE_PERSONAL_DATA;
    $definition = BusinessFilamentActionPermissionRegistry::all()[$slug];

    expect($slug)->toBe('editar-afiliados-corporativos')
        ->and($definition['group'])->toBe('AFILIADOS')
        ->and($definition['modules'])->toBe(['OPERACIONES']);
});

it('el permiso individual no habilita la edición corporativa, ni al revés', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('operations'));
    $record = new AffiliateCorporate;

    $this->actingAs(corporateOperationsAnalyst(slugs: [
        'afiliados-corporativos',
        BusinessFilamentActionPermissionRegistry::EDIT_INDIVIDUAL_AFFILIATE_PERSONAL_DATA,
    ]));
    expect(AffiliateCorporateResource::canEdit($record))->toBeFalse();

    $this->actingAs(corporateOperationsAnalyst(slugs: [
        'afiliados-corporativos',
        BusinessFilamentActionPermissionRegistry::EDIT_CORPORATE_AFFILIATE_PERSONAL_DATA,
    ]));
    expect(AffiliateCorporateResource::canEdit($record))->toBeTrue()
        ->and(AffiliateCorporateResource::canDelete($record))->toBeFalse();
});

it('la tabla y la ficha ofrecen «Editar» solo a quien puede editar', function () use ($basePath): void {
    $table = file_get_contents($basePath.'/app/Filament/Operations/Resources/AffiliateCorporates/Tables/AffiliateCorporatesTable.php');
    $view = file_get_contents($basePath.'/app/Filament/Operations/Resources/AffiliateCorporates/Pages/ViewAffiliateCorporate.php');

    expect($table)
        ->toContain('EditAction::make()')
        ->toContain("AffiliateCorporateResource::getUrl('edit', ['record' => \$record])")
        ->toContain('->visible(fn (AffiliateCorporate $record): bool => AffiliateCorporateResource::canEdit($record))');

    expect($view)
        ->toContain("Action::make('edit_personal_data')")
        ->toContain('AffiliateCorporateResource::canEdit($this->getRecord())');
});

it('el formulario corporativo no ofrece plan, tarifas, subtotales ni eliminar', function () use ($basePath): void {
    $form = file_get_contents($basePath.'/app/Filament/Operations/Resources/AffiliateCorporates/Schemas/AffiliateCorporatePersonalDataForm.php');
    $page = file_get_contents($basePath.'/app/Filament/Operations/Resources/AffiliateCorporates/Pages/EditAffiliateCorporate.php');

    foreach (["'plan_id'", "'coverage_id'", "'fee'", "'subtotal_anual'", "'subtotal_payment_frequency'", "'subtotal_daily'", "'payment_frequency'", "'status'", "'relationship'", "'condition_medical'", "'vaucherIls'", "'initial_date'"] as $forbidden) {
        expect($form)->not->toContain($forbidden);
    }

    expect($page)
        ->not->toContain('DeleteAction')
        ->toContain('use EditsAffiliatePersonalData;');
});

it('la página carga el formulario con los datos normalizados, guarda y redirige', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('operations'));
    $this->actingAs(corporateOperationsAnalyst(['SUPERADMIN', 'OPERACIONES']));
    $affiliate = makeCorporateAffiliate();

    Livewire::test(EditAffiliateCorporate::class, ['record' => $affiliate->getRouteKey()])
        ->assertOk()
        ->assertSee('Editar datos del afiliado corporativo')
        ->assertSchemaStateSet([
            'first_name' => 'Christofer Enrique',
            'sex' => 'MASCULINO',
            'birth_date' => '07/05/1983',
        ])
        ->set('data.position_company', 'Gerente')
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect(AffiliateCorporateResource::getUrl('view', ['record' => $affiliate]));

    $fresh = $affiliate->refresh();

    expect($fresh->position_company)->toBe('GERENTE')
        ->and($fresh->sex)->toBe('M')
        ->and($fresh->phone)->toBe('0424-8611567');
});

it('la página valida cédula y teléfonos', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('operations'));
    $this->actingAs(corporateOperationsAnalyst(['SUPERADMIN', 'OPERACIONES']));
    $affiliate = makeCorporateAffiliate();

    Livewire::test(EditAffiliateCorporate::class, ['record' => $affiliate->getRouteKey()])
        ->set('data.nro_identificacion', 'ABC')
        ->set('data.phone_emergency', '12')
        ->set('data.first_name', '')
        ->call('save')
        ->assertHasFormErrors(['nro_identificacion', 'phone_emergency', 'first_name' => 'required']);

    Queue::assertNothingPushed();
});

it('sin permiso la URL de edición corporativa responde 403', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('operations'));
    $this->actingAs(corporateOperationsAnalyst(slugs: ['afiliados-corporativos']));
    $affiliate = makeCorporateAffiliate();

    Livewire::test(EditAffiliateCorporate::class, ['record' => $affiliate->getRouteKey()])
        ->assertForbidden();
});

it('valida cédulas y teléfonos con los formatos que ya existen en la base', function (): void {
    expect(AffiliatePersonalDataUpdater::documentIsValid('V-12.345.678'))->toBeTrue()
        ->and(AffiliatePersonalDataUpdater::documentIsValid('12345678'))->toBeTrue()
        ->and(AffiliatePersonalDataUpdater::documentIsValid('ABC'))->toBeFalse()
        ->and(AffiliatePersonalDataUpdater::documentIsValid('1234'))->toBeFalse()
        ->and(AffiliatePersonalDataUpdater::phoneIsValid('0424-8611567'))->toBeTrue()
        ->and(AffiliatePersonalDataUpdater::phoneIsValid('+58 (414) 123-4567'))->toBeTrue()
        ->and(AffiliatePersonalDataUpdater::phoneIsValid(''))->toBeTrue()
        ->and(AffiliatePersonalDataUpdater::phoneIsValid('12'))->toBeFalse()
        ->and(AffiliatePersonalDataUpdater::phoneIsValid('0414-ABC-1234'))->toBeFalse()
        ->and(AffiliatePersonalDataUpdater::normalizeBirthDate('7/5/2009'))->toBe('07/05/2009');
});
