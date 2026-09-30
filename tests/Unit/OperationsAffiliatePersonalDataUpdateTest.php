<?php

declare(strict_types=1);

use App\Enums\SystemNotificationKey;
use App\Filament\Operations\Resources\Affiliates\AffiliateResource;
use App\Filament\Operations\Resources\Affiliates\Pages\EditAffiliate;
use App\Jobs\NotifyAffiliationsTeamOfAffiliateUpdateJob;
use App\Jobs\SendNotificacionWhatsApp;
use App\Mail\AffiliateUpdatedByOperationsMail;
use App\Models\Affiliate;
use App\Models\Permission;
use App\Models\SystemNotificationRecipientSetting;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Operations\AffiliatePersonalDataUpdater;
use App\Support\Operations\AffiliateUpdateNotificationMessage;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * Con la config cacheada `runningUnitTests()` es falso y `fillForm()` de
 * Filament no hace nada: los formularios se llenan con `set('data.…')`.
 *
 * Edición de datos personales de afiliados individuales desde Operaciones.
 *
 * Escribe en la base: todo corre dentro de una transacción que se revierte al
 * terminar cada test (DatabaseTransactions), que además ejecuta los
 * `afterCommit` como si la transacción del updater se hubiera confirmado.
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

$basePath = dirname(__DIR__, 2);

function operationsAffiliateFreeDocument(): string
{
    do {
        $document = (string) random_int(900000000, 999999999);
    } while (
        Affiliate::query()->where('nro_identificacion', $document)->exists()
        || TelemedicinePatient::query()->where('nro_identificacion', $document)->exists()
    );

    return $document;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function makeOperationsAffiliate(array $attributes = []): Affiliate
{
    $affiliationId = (int) App\Models\Affiliation::query()->min('id');

    $affiliate = new Affiliate;
    $affiliate->forceFill([
        'affiliation_id' => $affiliationId,
        'full_name' => 'AFILIADO DE PRUEBA',
        'nro_identificacion' => operationsAffiliateFreeDocument(),
        'sex' => 'FEMENINO',
        'birth_date' => '10/05/1990',
        'age' => '36',
        'relationship' => 'HIJA',
        'status' => 'ACTIVO',
        'phone' => '584141112233',
        'email' => 'prueba@example.com',
        'address' => 'CARACAS',
        'plan_id' => 1,
        'age_range_id' => 1,
        'fee' => 100,
        'total_amount' => 100,
        ...$attributes,
    ])->save();

    return $affiliate->refresh();
}

function makeOperationsTelemedicinePatient(string $document, array $attributes = []): TelemedicinePatient
{
    $patient = new TelemedicinePatient;
    $patient->forceFill([
        'full_name' => 'PACIENTE DE PRUEBA',
        'nro_identificacion' => $document,
        'sex' => 'FEMENINO',
        'phone' => '584140000000',
        'email' => 'paciente@example.com',
        ...$attributes,
    ])->save();

    return $patient->refresh();
}

function configureAffiliateUpdateRecipients(bool $active = true, array $emails = ['afiliaciones@example.com'], array $phones = ['04141234567']): void
{
    SystemNotificationRecipientSetting::for(SystemNotificationKey::OperationsAffiliateUpdate)
        ->forceFill([
            'is_active' => $active,
            'notification_emails' => $emails,
            'notification_phones' => $phones,
        ])->save();
}

/**
 * @param  list<string>  $departments
 * @param  list<string>  $slugs
 */
function operationsAnalyst(array $departments = ['OPERACIONES'], array $slugs = []): User
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

// ── Updater ────────────────────────────────────────────────────────────────

it('guarda solo los datos personales e ignora plan, tarifa, montos, estado y parentesco', function (): void {
    $affiliate = makeOperationsAffiliate();

    $result = AffiliatePersonalDataUpdater::update($affiliate, [
        'full_name' => 'Nombre   Corregido',
        'plan_id' => 999,
        'coverage_id' => 999,
        'age_range_id' => 999,
        'fee' => 1,
        'total_amount' => 1,
        'status' => 'INACTIVO',
        'relationship' => 'TITULAR',
        'affiliation_id' => 999999,
        'vaucherIls' => 'X',
    ], null);

    $fresh = $affiliate->refresh();

    expect($result->changes)->toHaveKeys(['full_name'])->toHaveCount(1)
        ->and($fresh->full_name)->toBe('NOMBRE CORREGIDO')
        ->and((int) $fresh->plan_id)->toBe(1)
        ->and((int) $fresh->age_range_id)->toBe(1)
        ->and((float) $fresh->fee)->toBe(100.0)
        ->and($fresh->status)->toBe('ACTIVO')
        ->and($fresh->relationship)->toBe('HIJA')
        ->and($fresh->vaucherIls)->toBeNull();
});

it('normaliza cédula, correo, teléfono y fecha, y deriva la edad', function (): void {
    $affiliate = makeOperationsAffiliate();
    $document = operationsAffiliateFreeDocument();

    $result = AffiliatePersonalDataUpdater::update($affiliate, [
        'nro_identificacion' => 'V-'.number_format((int) $document, 0, ',', '.'),
        'email' => '  NUEVO@Example.COM ',
        'phone' => '+58 414-999 8877',
        'birth_date' => '15-03-2000',
        'age' => '99',
    ], null);

    $fresh = $affiliate->refresh();

    expect($fresh->nro_identificacion)->toBe($document)
        ->and($fresh->email)->toBe('nuevo@example.com')
        ->and($fresh->phone)->toBe('+584149998877')
        ->and($fresh->birth_date)->toBe('15/03/2000')
        ->and($fresh->age)->toBe((string) Illuminate\Support\Carbon::create(2000, 3, 15)->age)
        ->and($result->changes)->toHaveKey('age');
});

it('no cuenta como cambio una fecha guardada con guiones que es la misma', function (): void {
    $affiliate = makeOperationsAffiliate(['birth_date' => '10-05-1990']);

    $result = AffiliatePersonalDataUpdater::update($affiliate, ['birth_date' => '10/05/1990'], null);

    expect($result->hasChanges())->toBeFalse();
    Queue::assertNothingPushed();
});

it('sin cambios no guarda ni avisa', function (): void {
    $affiliate = makeOperationsAffiliate();
    $updatedAt = $affiliate->updated_at;

    $result = AffiliatePersonalDataUpdater::update($affiliate, [
        'full_name' => 'afiliado de prueba',
        'email' => 'PRUEBA@example.com',
    ], null);

    expect($result->hasChanges())->toBeFalse()
        ->and($affiliate->refresh()->updated_at?->equalTo($updatedAt))->toBeTrue();
    Queue::assertNothingPushed();
});

it('encola el aviso a Afiliaciones con cada cambio antes y después', function (): void {
    $affiliate = makeOperationsAffiliate();

    AffiliatePersonalDataUpdater::update($affiliate, ['full_name' => 'OTRO NOMBRE', 'phone' => '584241112233'], null);

    Queue::assertPushed(NotifyAffiliationsTeamOfAffiliateUpdateJob::class, function (NotifyAffiliationsTeamOfAffiliateUpdateJob $job) use ($affiliate): bool {
        $changes = collect($job->payload['changes'])->keyBy('field');

        return $job->payload['affiliate_id'] === $affiliate->id
            && $changes['full_name']['before'] === 'AFILIADO DE PRUEBA'
            && $changes['full_name']['after'] === 'OTRO NOMBRE'
            && $changes['phone']['after'] === '584241112233'
            && $job->payload['source_label'] === 'Edición de datos personales';
    });
});

it('actualiza el paciente de telemedicina vinculado solo en los campos que cambiaron', function (): void {
    $affiliate = makeOperationsAffiliate();
    $patient = makeOperationsTelemedicinePatient($affiliate->nro_identificacion, ['email' => 'propio@telemedicina.com']);
    $newDocument = operationsAffiliateFreeDocument();

    $result = AffiliatePersonalDataUpdater::update($affiliate, [
        'nro_identificacion' => $newDocument,
        'full_name' => 'NOMBRE NUEVO',
        'email' => 'otro@example.com',
    ], null);

    $patient->refresh();

    expect($result->telemedicineSynced)->toBeTrue()
        ->and($patient->nro_identificacion)->toBe($newDocument)
        ->and($patient->full_name)->toBe('NOMBRE NUEVO')
        ->and($patient->email)->toBe('propio@telemedicina.com');
});

it('bloquea una cédula que ya pertenece a otro paciente de telemedicina y no guarda nada', function (): void {
    $affiliate = makeOperationsAffiliate();
    $otherDocument = operationsAffiliateFreeDocument();
    makeOperationsTelemedicinePatient($otherDocument, ['full_name' => 'OTRA PERSONA']);

    expect(fn () => AffiliatePersonalDataUpdater::update($affiliate, [
        'nro_identificacion' => $otherDocument,
        'full_name' => 'NO DEBE GUARDARSE',
    ], null))->toThrow(ValidationException::class, 'OTRA PERSONA');

    expect($affiliate->refresh()->full_name)->toBe('AFILIADO DE PRUEBA');
    Queue::assertNothingPushed();
});

it('avisa cuando la edad nueva sale del rango tarifario sin tocar la tarifa', function (): void {
    $rangeId = (int) App\Models\AgeRange::query()->where('age_init', 0)->where('age_end', 45)->value('id');
    $affiliate = makeOperationsAffiliate(['age_range_id' => $rangeId]);

    $result = AffiliatePersonalDataUpdater::update($affiliate, ['birth_date' => '01/01/1950'], null);

    expect($result->ageRangeWarning)->toContain('fuera del rango tarifario')
        ->and((int) $affiliate->refresh()->age_range_id)->toBe($rangeId)
        ->and((float) $affiliate->fee)->toBe(100.0);

    $inside = AffiliatePersonalDataUpdater::update($affiliate, ['birth_date' => '01/01/2000'], null);

    expect($inside->ageRangeWarning)->toBeNull();
})->skip(fn (): bool => ! App\Models\AgeRange::query()->where('age_init', 0)->where('age_end', 45)->exists(), 'No hay un rango 0–45 en la base.');

it('marca en el aviso que los datos del titular en la afiliación no cambiaron', function (): void {
    $affiliate = makeOperationsAffiliate(['relationship' => 'TITULAR']);

    AffiliatePersonalDataUpdater::update($affiliate, ['full_name' => 'TITULAR CORREGIDO'], null);

    Queue::assertPushed(NotifyAffiliationsTeamOfAffiliateUpdateJob::class, fn ($job): bool => str_contains((string) $job->payload['titular_note'], 'TITULAR'));
});

it('la dirección fijada con el mapa pasa por el mismo updater y avisa', function () use ($basePath): void {
    $trait = file_get_contents($basePath.'/app/Filament/Operations/Concerns/AppliesOperationsAddressFromMaps.php');

    preg_match('/function applyAffiliateLocationFromMaps\(.*?\n    \}/s', $trait, $method);

    expect($method[0] ?? '')
        ->toContain('AffiliatePersonalDataUpdater::update(')
        ->toContain('AffiliatePersonalDataUpdater::SOURCE_MAPS_ADDRESS')
        ->not->toContain('$record->save()');
});

// ── Mensaje y job ──────────────────────────────────────────────────────────

it('el WhatsApp detalla afiliado, cambios, analista y advertencias', function (): void {
    $body = AffiliateUpdateNotificationMessage::whatsappBody([
        'affiliate_name' => 'MARIA PEREZ',
        'affiliate_document' => '22171244',
        'relationship' => 'TITULAR',
        'affiliation_code' => 'TDEC-IND-000001',
        'plan' => 'PLAN ESPECIAL',
        'changes' => [['field' => 'phone', 'label' => 'Teléfono', 'before' => '584140000000', 'after' => '584141111111']],
        'source_label' => 'Edición de datos personales',
        'updated_by' => 'Analista',
        'updated_at' => '29/09/2026 10:00',
        'telemedicine_synced' => true,
        'age_range_warning' => 'La edad (80 años) quedó fuera del rango tarifario asignado (0 a 45).',
        'titular_note' => 'Es el TITULAR',
        'url' => 'https://example.test/operations/affiliates/1',
    ]);

    expect($body)
        ->toContain('AFILIADO ACTUALIZADO · OPERACIONES')
        ->toContain('Teléfono: 584140000000 → *584141111111*')
        ->toContain('TDEC-IND-000001')
        ->toContain('Analista: Analista')
        ->toContain('Paciente de telemedicina: actualizado')
        ->toContain('*TARIFA:*')
        ->toContain('*TITULAR:*');
});

it('el job no envía nada si el aviso está pausado', function (): void {
    configureAffiliateUpdateRecipients(active: false);
    Mail::fake();
    Bus::fake([SendNotificacionWhatsApp::class]);

    (new NotifyAffiliationsTeamOfAffiliateUpdateJob(['affiliate_id' => 1, 'changes' => []]))->handle();

    Mail::assertNothingSent();
    Bus::assertNotDispatchedSync(SendNotificacionWhatsApp::class);
});

it('el job envía correo y WhatsApp a los contactos del centro de notificaciones una sola vez', function (): void {
    configureAffiliateUpdateRecipients();
    Mail::fake();
    Bus::fake([SendNotificacionWhatsApp::class]);

    $job = new NotifyAffiliationsTeamOfAffiliateUpdateJob([
        'affiliate_id' => 1,
        'affiliate_name' => 'MARIA',
        'affiliation_code' => 'TDEC-IND-1',
        'changes' => [['field' => 'phone', 'label' => 'Teléfono', 'before' => 'a', 'after' => 'b']],
    ]);

    $job->handle();
    $job->handle();

    Mail::assertSent(AffiliateUpdatedByOperationsMail::class, 1);
    Mail::assertSent(AffiliateUpdatedByOperationsMail::class, fn (AffiliateUpdatedByOperationsMail $mail): bool => $mail->hasTo('afiliaciones@example.com'));
    Bus::assertDispatchedSyncTimes(SendNotificacionWhatsApp::class, 1);
});

it('el correo se renderiza con la tabla de cambios', function (): void {
    $html = view('mails.affiliate-updated-by-operations', [
        'affiliate_name' => 'MARIA PEREZ',
        'affiliation_code' => 'TDEC-IND-1',
        'changes' => [['field' => 'full_name', 'label' => 'Nombre completo', 'before' => 'MARIA', 'after' => 'MARIA PEREZ']],
        'age_range_warning' => 'Fuera de rango',
        'updated_by' => 'Analista',
    ])->render();

    expect($html)
        ->toContain('Cambios realizados (1)')
        ->toContain('Nombre completo')
        ->toContain('Fuera de rango');
});

// ── Permisos y centro de notificaciones ────────────────────────────────────

it('registra el permiso de edición solo en Operaciones, grupo AFILIADOS', function (): void {
    $slug = BusinessFilamentActionPermissionRegistry::EDIT_INDIVIDUAL_AFFILIATE_PERSONAL_DATA;
    $definition = BusinessFilamentActionPermissionRegistry::all()[$slug];

    expect($slug)->toBe('editar-afiliados-individuales')
        ->and($definition['group'])->toBe('AFILIADOS')
        ->and($definition['modules'])->toBe(['OPERACIONES']);
});

it('solo puede editar quien tenga el permiso; nadie puede eliminar', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('operations'));
    $affiliate = new Affiliate;

    $this->actingAs(operationsAnalyst(slugs: ['afiliados-individuales']));
    expect(AffiliateResource::canEdit($affiliate))->toBeFalse()
        ->and(AffiliateResource::canDelete($affiliate))->toBeFalse();

    $this->actingAs(operationsAnalyst(slugs: [
        'afiliados-individuales',
        BusinessFilamentActionPermissionRegistry::EDIT_INDIVIDUAL_AFFILIATE_PERSONAL_DATA,
    ]));
    expect(AffiliateResource::canEdit($affiliate))->toBeTrue()
        ->and(AffiliateResource::canDelete($affiliate))->toBeFalse();

    $this->actingAs(operationsAnalyst(['SUPERADMIN', 'OPERACIONES']));
    expect(AffiliateResource::canEdit($affiliate))->toBeTrue();
});

it('agrega el aviso al centro de notificaciones como alerta no programada', function (): void {
    $key = SystemNotificationKey::OperationsAffiliateUpdate;

    expect(SystemNotificationKey::managed())->toContain($key)
        ->and($key->pausesScheduledTask())->toBeFalse()
        ->and($key->label())->toBe('Actualización de afiliados (Operaciones)')
        ->and($key->defaultEmails())->toBe([])
        ->and($key->defaultPhones())->toBe([]);
});

// ── UI ─────────────────────────────────────────────────────────────────────

it('la tabla muestra «Editar» solo a quien puede editar y lleva a la página de edición', function () use ($basePath): void {
    $table = file_get_contents($basePath.'/app/Filament/Operations/Resources/Affiliates/Tables/AffiliatesTable.php');

    expect($table)
        ->toContain('EditAction::make()')
        ->toContain("->label('Editar')")
        ->toContain("AffiliateResource::getUrl('edit', ['record' => \$record])")
        ->toContain('->visible(fn (Affiliate $record): bool => AffiliateResource::canEdit($record))');
});

it('la página de edición no ofrece eliminar ni campos de plan, cobertura o montos', function () use ($basePath): void {
    $page = file_get_contents($basePath.'/app/Filament/Operations/Resources/Affiliates/Pages/EditAffiliate.php');
    $form = file_get_contents($basePath.'/app/Filament/Operations/Resources/Affiliates/Schemas/AffiliatePersonalDataForm.php');

    $concern = file_get_contents($basePath.'/app/Filament/Operations/Concerns/EditsAffiliatePersonalData.php');

    expect($page)
        ->not->toContain('DeleteAction')
        ->toContain('use EditsAffiliatePersonalData;')
        ->toContain('AffiliatePersonalDataForm::configure($schema)');

    expect($concern)
        ->not->toContain('DeleteAction')
        ->toContain('AffiliatePersonalDataUpdater::update(');

    foreach (["'plan_id'", "'coverage_id'", "'age_range_id'", "'fee'", "'total_amount'", "'payment_frequency'", "'vaucherIls'", "::make('status')->required", "::make('relationship')->required"] as $forbidden) {
        expect($form)->not->toContain($forbidden);
    }
});

it('la página de edición carga el formulario, guarda y redirige al detalle', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('operations'));
    $this->actingAs(operationsAnalyst(['SUPERADMIN', 'OPERACIONES']));
    $affiliate = makeOperationsAffiliate(['birth_date' => '10-05-1990']);

    Livewire::test(EditAffiliate::class, ['record' => $affiliate->getRouteKey()])
        ->assertOk()
        ->assertSchemaStateSet([
            'full_name' => 'AFILIADO DE PRUEBA',
            'birth_date' => '10/05/1990',
        ])
        ->set('data.full_name', 'Nombre Desde La Pantalla')
        ->set('data.phone', '584149990000')
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect(AffiliateResource::getUrl('view', ['record' => $affiliate]));

    expect($affiliate->refresh()->full_name)->toBe('NOMBRE DESDE LA PANTALLA')
        ->and($affiliate->phone)->toBe('584149990000');
});

it('la página de edición valida cédula y teléfono', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('operations'));
    $this->actingAs(operationsAnalyst(['SUPERADMIN', 'OPERACIONES']));
    $affiliate = makeOperationsAffiliate();

    Livewire::test(EditAffiliate::class, ['record' => $affiliate->getRouteKey()])
        ->set('data.nro_identificacion', 'V-12')
        ->set('data.phone', '123')
        ->set('data.full_name', '')
        ->call('save')
        ->assertHasFormErrors(['nro_identificacion', 'phone', 'full_name' => 'required']);

    Queue::assertNothingPushed();
});

it('sin permiso la URL de edición responde 403', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('operations'));
    $this->actingAs(operationsAnalyst(slugs: ['afiliados-individuales']));
    $affiliate = makeOperationsAffiliate();

    Livewire::test(EditAffiliate::class, ['record' => $affiliate->getRouteKey()])
        ->assertForbidden();
});

it('el encabezado muestra el nombre, el estado y los datos clave del afiliado', function (): void {
    $affiliate = makeOperationsAffiliate(['full_name' => 'CARLOS <REY> GOMEZ', 'relationship' => 'TITULAR']);
    $affiliate->load('affiliation:id,code');

    $html = view('filament.operations.affiliates.edit-header', [
        'name' => $affiliate->full_name,
        'status' => $affiliate->status,
        'chips' => [
            'C.I.' => $affiliate->nro_identificacion,
            'Afiliación' => $affiliate->affiliation?->code,
            'Parentesco' => $affiliate->relationship,
        ],
    ])->render();

    expect($html)
        ->toContain('Editar datos del afiliado')
        ->toContain('CARLOS &lt;REY&gt; GOMEZ')
        ->toContain('ACTIVO')
        ->toContain('C.I.')
        ->toContain($affiliate->nro_identificacion)
        ->toContain('Parentesco')
        ->toContain('TITULAR')
        ->toContain('el equipo de Afiliaciones recibe el detalle');

    if (filled($affiliate->affiliation?->code)) {
        expect($html)->toContain($affiliate->affiliation->code);
    }
});

it('la pestaña del navegador conserva un título en texto plano', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('operations'));
    $this->actingAs(operationsAnalyst(['SUPERADMIN', 'OPERACIONES']));
    $affiliate = makeOperationsAffiliate();

    Livewire::test(EditAffiliate::class, ['record' => $affiliate->getRouteKey()])
        ->assertOk()
        ->assertSee('AFILIADO DE PRUEBA')
        ->assertSee('Solo datos personales.');

    expect((new EditAffiliate)->getTitle())->toBe('Editar datos del afiliado');
});

it('el formulario no repite la sección de afiliación: esos datos ya están en el encabezado', function () use ($basePath): void {
    $form = file_get_contents($basePath.'/app/Filament/Operations/Resources/Affiliates/Schemas/AffiliatePersonalDataForm.php');

    expect($form)
        ->not->toContain("Section::make('Afiliación')")
        ->not->toContain('TextEntry::make');
});
