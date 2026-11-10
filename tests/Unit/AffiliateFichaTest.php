<?php

declare(strict_types=1);

use App\Enums\AffiliateFichaScope;
use App\Filament\Operations\Resources\Affiliates\Pages\ListAffiliates;
use App\Filament\Shared\Affiliations\Actions\AffiliateFichaAction;
use App\Http\Controllers\AffiliateFichaPdfController;
use App\Jobs\DeleteAffiliateFichaPublicFileJob;
use App\Jobs\SendAffiliateFichaEmailJob;
use App\Jobs\SendAffiliateFichaWhatsAppJob;
use App\Mail\AffiliateFichaMail;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Permission;
use App\Models\User;
use App\Services\AffiliateFichaPdfService;
use App\Support\Affiliations\AffiliateFicha\AffiliateFichaAccess;
use App\Support\Affiliations\AffiliateFicha\AffiliateFichaData;
use App\Support\Affiliations\AffiliateFicha\AffiliateFichaDelivery;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

function fichaUser(array $departments, bool $clinicalPermissionIn = false, string $module = 'NEGOCIOS', array $attributes = []): User
{
    $user = User::factory()->make([
        'email' => 'analista.ficha@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => $departments,
        ...$attributes,
    ]);

    $permissions = $clinicalPermissionIn
        ? [new Permission(['slug' => BusinessFilamentActionPermissionRegistry::VIEW_AFFILIATE_CLINICAL_FICHA, 'module' => $module])]
        : [];

    $user->setRelation('permissions', new \Illuminate\Database\Eloquent\Collection($permissions));

    return $user;
}

it('cada opción dice qué bloques lleva', function (AffiliateFichaScope $scope, bool $administration, bool $clinical): void {
    expect($scope->includesAdministration())->toBe($administration)
        ->and($scope->includesClinical())->toBe($clinical)
        ->and($scope->label())->not->toBe('')
        ->and($scope->description())->not->toBe('');
})->with([
    'administración' => [AffiliateFichaScope::Administration, true, false],
    'operaciones y telemedicina' => [AffiliateFichaScope::Clinical, false, true],
    'todo' => [AffiliateFichaScope::All, true, true],
]);

it('registra el permiso de datos clínicos en Negocios, Administración y Operaciones', function (): void {
    expect(BusinessFilamentActionPermissionRegistry::modulesForSlug(BusinessFilamentActionPermissionRegistry::VIEW_AFFILIATE_CLINICAL_FICHA))
        ->toBe(['NEGOCIOS', 'ADMINISTRACION', 'OPERACIONES'])
        ->and(BusinessFilamentActionPermissionRegistry::navigationGroupForSlug(BusinessFilamentActionPermissionRegistry::VIEW_AFFILIATE_CLINICAL_FICHA))
        ->toBe('AFILIADOS');
});

it('sin usuario, sin el departamento o siendo proveedor no se abre la ficha', function (): void {
    expect(AffiliateFichaAccess::canOpen(null, 'business'))->toBeFalse()
        ->and(AffiliateFichaAccess::canOpen(fichaUser(['MARKETING']), 'business'))->toBeFalse()
        ->and(AffiliateFichaAccess::canOpen(fichaUser(['OPERACIONES'], attributes: ['supplier_id' => 7]), 'operations'))->toBeFalse()
        ->and(AffiliateFichaAccess::canOpen(fichaUser(['SUPERADMIN']), 'marketing'))->toBeFalse()
        ->and(AffiliateFichaAccess::scopesFor(null, 'business'))->toBe([]);
});

it('sin el permiso clínico solo se ofrece Administración', function (): void {
    $user = fichaUser(['NEGOCIOS']);

    expect(AffiliateFichaAccess::canOpen($user, 'business'))->toBeTrue()
        ->and(AffiliateFichaAccess::scopesFor($user, 'business'))->toBe([AffiliateFichaScope::Administration])
        ->and(AffiliateFichaAccess::canUse($user, 'business', AffiliateFichaScope::Clinical))->toBeFalse()
        ->and(AffiliateFichaAccess::canUse($user, 'business', AffiliateFichaScope::All))->toBeFalse()
        ->and(AffiliateFichaAccess::defaultScopeFor($user, 'business'))->toBe(AffiliateFichaScope::Administration);
});

it('el permiso clínico vale solo en el módulo donde se concedió', function (): void {
    $user = fichaUser(['NEGOCIOS', 'OPERACIONES'], clinicalPermissionIn: true, module: 'OPERACIONES');

    expect(AffiliateFichaAccess::canSeeClinical($user, 'operations'))->toBeTrue()
        ->and(AffiliateFichaAccess::canSeeClinical($user, 'business'))->toBeFalse()
        ->and(AffiliateFichaAccess::defaultScopeFor($user, 'operations'))->toBe(AffiliateFichaScope::Clinical);
});

it('el SUPERADMIN tiene las tres opciones y cada panel preselecciona la suya', function (): void {
    $user = fichaUser(['SUPERADMIN']);

    expect(AffiliateFichaAccess::scopesFor($user, 'business'))->toBe(AffiliateFichaScope::cases())
        ->and(AffiliateFichaAccess::defaultScopeFor($user, 'business'))->toBe(AffiliateFichaScope::All)
        ->and(AffiliateFichaAccess::defaultScopeFor($user, 'administration'))->toBe(AffiliateFichaScope::Administration)
        ->and(AffiliateFichaAccess::defaultScopeFor($user, 'operations'))->toBe(AffiliateFichaScope::Clinical);
});

it('formatea fechas en los formatos que hay en la base, montos y vacíos', function (mixed $value, string $expected): void {
    expect(AffiliateFichaData::date($value))->toBe($expected);
})->with([
    'dd/mm/aaaa' => ['07/11/1951', '07/11/1951'],
    'dd-mm-aaaa' => ['08-02-1940', '08/02/1940'],
    'd/m/aaaa' => ['13/5/2010', '13/05/2010'],
    'aaaa-mm-dd con hora' => ['2026-07-24 10:00:00', '24/07/2026'],
    'vacío' => [null, '—'],
    'ilegible se muestra tal cual' => ['sin fecha', 'sin fecha'],
]);

it('formatea montos y textos vacíos', function (): void {
    expect(AffiliateFichaData::money('1234.5'))->toBe('US$ 1.234,50')
        ->and(AffiliateFichaData::money(null))->toBe('—')
        ->and(AffiliateFichaData::money('abc'))->toBe('—')
        ->and(AffiliateFichaData::text('  '))->toBe('—')
        ->and(AffiliateFichaData::text(['A', '', 'B']))->toBe('A, B');
});

it('la opción elegida decide los bloques de la ficha individual', function (AffiliateFichaScope $scope): void {
    $affiliate = Affiliate::query()->whereHas('affiliation')->latest('id')->first();

    if ($affiliate === null) {
        $this->markTestSkipped('No hay afiliados individuales en la base.');
    }

    $data = AffiliateFichaData::forIndividual($affiliate, $scope);

    expect($data['kind'])->toBe(AffiliateFichaData::KIND_INDIVIDUAL)
        ->and($data['scope'])->toBe($scope->value)
        ->and($data['affiliateRows'])->not->toBeEmpty()
        ->and($data['administration'] !== null)->toBe($scope->includesAdministration())
        ->and($data['clinical'] !== null)->toBe($scope->includesClinical());

    if ($data['administration'] !== null) {
        expect($data['administration'])->toHaveKeys(['feeRows', 'payerRows', 'upgrades', 'collectionsSummary', 'collections', 'payments', 'renovations'])
            ->and(count($data['administration']['payments']['rows']))->toBeLessThanOrEqual(AffiliateFichaData::ROW_LIMIT);
    }

    if ($data['clinical'] !== null) {
        expect($data['clinical'])->toHaveKeys(['linked', 'patientRows', 'healthRows', 'entitlements', 'coordinations']);
    }
})->with(AffiliateFichaScope::cases());

it('arma la ficha corporativa con sus datos propios', function (): void {
    $affiliate = AffiliateCorporate::query()->whereHas('affiliationCorporate')->latest('id')->first();

    if ($affiliate === null) {
        $this->markTestSkipped('No hay afiliados corporativos en la base.');
    }

    $data = AffiliateFichaData::forCorporate($affiliate, AffiliateFichaScope::All);
    $labels = array_column($data['affiliateRows'], 0);

    expect($data['kind'])->toBe(AffiliateFichaData::KIND_CORPORATE)
        ->and($labels)->toContain('Cargo en la empresa')
        ->and(array_column($data['affiliationRows'], 0))->toContain('Empresa')
        ->and(array_column($data['clinical']['healthRows'], 0))->toContain('Condición médica declarada');
});

it('un listado largo trae a lo sumo el tope de filas y dice cuántas quedaron fuera', function (): void {
    $patientId = DB::table('telemedicine_consultation_patients')
        ->select('telemedicine_patient_id')
        ->groupBy('telemedicine_patient_id')
        ->havingRaw('count(*) > ?', [AffiliateFichaData::ROW_LIMIT])
        ->value('telemedicine_patient_id');

    $document = $patientId === null ? null : DB::table('telemedicine_patients')->where('id', $patientId)->value('nro_identificacion');
    $affiliate = $document === null ? null : Affiliate::query()->where('nro_identificacion', $document)->first();

    if ($affiliate === null) {
        $this->markTestSkipped('No hay un afiliado con más consultas que el tope.');
    }

    $consultations = AffiliateFichaData::forIndividual($affiliate, AffiliateFichaScope::Clinical)['clinical']['consultations'];

    expect($consultations['rows'])->toHaveCount(AffiliateFichaData::ROW_LIMIT)
        ->and($consultations['hidden'])->toBe($consultations['total'] - AffiliateFichaData::ROW_LIMIT)
        ->and($consultations['hidden'])->toBeGreaterThan(0);
});

it('genera un PDF y lo reutiliza mientras los datos no cambien', function (): void {
    $affiliate = Affiliate::query()->whereHas('affiliation')->latest('id')->first();

    if ($affiliate === null) {
        $this->markTestSkipped('No hay afiliados individuales en la base.');
    }

    $first = AffiliateFichaPdfService::render(AffiliateFichaData::KIND_INDIVIDUAL, $affiliate, AffiliateFichaScope::Administration);
    $second = AffiliateFichaPdfService::render(AffiliateFichaData::KIND_INDIVIDUAL, $affiliate->fresh(), AffiliateFichaScope::Administration);

    expect(str_starts_with($first['binary'], '%PDF'))->toBeTrue()
        ->and($second['binary'])->toBe($first['binary'])
        ->and($first['filename'])->toEndWith('-administracion.pdf');
});

it('rechaza un tipo de afiliado que no corresponde con el modelo', function (): void {
    AffiliateFichaPdfService::data(AffiliateFichaData::KIND_CORPORATE, new Affiliate, AffiliateFichaScope::All);
})->throws(InvalidArgumentException::class);

it('la ruta exige firma', function (): void {
    $this->actingAs(fichaUser(['SUPERADMIN']))
        ->get('/fichas/afiliado/individual/1/administracion/business/vista')
        ->assertForbidden();
});

it('sin el permiso clínico la ruta firmada niega la ficha con datos médicos', function (): void {
    $url = AffiliateFichaPdfController::url(AffiliateFichaData::KIND_INDIVIDUAL, 1, AffiliateFichaScope::Clinical, 'business', AffiliateFichaPdfController::MODE_PREVIEW);

    $this->actingAs(fichaUser(['NEGOCIOS']))->get($url)->assertForbidden();
});

it('con permiso entrega el PDF en línea para la vista previa y como adjunto para la descarga', function (): void {
    $affiliate = Affiliate::query()->whereHas('affiliation')->latest('id')->first();

    if ($affiliate === null) {
        $this->markTestSkipped('No hay afiliados individuales en la base.');
    }

    $user = fichaUser(['SUPERADMIN']);

    $preview = $this->actingAs($user)->get(AffiliateFichaPdfController::url(AffiliateFichaData::KIND_INDIVIDUAL, (int) $affiliate->id, AffiliateFichaScope::All, 'business', AffiliateFichaPdfController::MODE_PREVIEW));
    $download = $this->actingAs($user)->get(AffiliateFichaPdfController::url(AffiliateFichaData::KIND_INDIVIDUAL, (int) $affiliate->id, AffiliateFichaScope::All, 'business', AffiliateFichaPdfController::MODE_DOWNLOAD));

    $preview->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $download->assertOk();

    expect($preview->headers->get('Content-Disposition'))->toStartWith('inline;')
        ->and($download->headers->get('Content-Disposition'))->toStartWith('attachment;');
});

it('un afiliado inexistente responde 404', function (): void {
    $url = AffiliateFichaPdfController::url(AffiliateFichaData::KIND_CORPORATE, 999999999, AffiliateFichaScope::Administration, 'business', AffiliateFichaPdfController::MODE_PREVIEW);

    $this->actingAs(fichaUser(['SUPERADMIN']))->get($url)->assertNotFound();
});

it('la acción está en el menú de cada afiliado de los seis listados', function (string $path, string $kind): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);

    expect($source)->toContain("AffiliateFichaAction::make(AffiliateFichaData::{$kind})");
})->with([
    'Negocios individual' => ['app/Filament/Business/Resources/Affiliations/RelationManagers/AffiliatesRelationManager.php', 'KIND_INDIVIDUAL'],
    'Negocios corporativo' => ['app/Filament/Business/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php', 'KIND_CORPORATE'],
    'Administración individual' => ['app/Filament/Administration/Resources/Affiliations/RelationManagers/AffiliatesRelationManager.php', 'KIND_INDIVIDUAL'],
    'Administración corporativo' => ['app/Filament/Administration/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php', 'KIND_CORPORATE'],
    'Operaciones individual' => ['app/Filament/Operations/Resources/Affiliates/Tables/AffiliatesTable.php', 'KIND_INDIVIDUAL'],
    'Operaciones corporativo' => ['app/Filament/Operations/Resources/AffiliateCorporates/Tables/AffiliateCorporatesTable.php', 'KIND_CORPORATE'],
]);

it('la vista previa tiene indicador de carga y una descarga sin wire:navigate', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/shared/affiliations/affiliate-ficha-preview.blade.php');

    expect($view)->toContain('animate-spin')
        ->toContain('x-on:load="loaded = true"')
        ->toContain('wire:key="affiliate-ficha-{{ $fichaScopeKey }}"')
        ->not->toContain('<x-filament::button')
        ->not->toMatch('/<a\b[^>]*wire:navigate/s');
});

/*
 * ---------------------------------------------------------------------------
 * Envío por correo y por WhatsApp
 * ---------------------------------------------------------------------------
 */

function fichaSender(): User
{
    return User::factory()->make([
        'id' => 999_999_611,
        'name' => 'QA FICHA',
        'email' => 'qa.ficha@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'OPERACIONES'],
    ]);
}

it('reparte los correos sin repetir a nadie y separa los inválidos', function (): void {
    $split = AffiliateFichaDelivery::splitEmails(
        ['Uno@Correo.com', 'dos@correo.com', 'no-es-correo'],
        ['dos@correo.com', 'tres@correo.com', ''],
        ['uno@correo.com', 'cuatro@correo.com', 'cuatro@correo.com'],
    );

    expect($split['to'])->toBe(['uno@correo.com', 'dos@correo.com'])
        ->and($split['cc'])->toBe(['tres@correo.com'])
        ->and($split['bcc'])->toBe(['cuatro@correo.com'])
        ->and($split['invalid'])->toBe(['no-es-correo']);
});

it('encola el correo con Para, varios CC y varios CCO', function (): void {
    Queue::fake();

    $error = AffiliateFichaDelivery::queueEmail(
        fichaSender(), AffiliateFichaData::KIND_INDIVIDUAL, 15, AffiliateFichaScope::All, 'operations',
        ['afiliado@correo.com'], ['testigo1@correo.com', 'testigo2@correo.com'], ['oculta1@correo.com', 'oculta2@correo.com'], ' Hola ',
    );

    expect($error)->toBeNull();

    Queue::assertPushed(SendAffiliateFichaEmailJob::class, fn (SendAffiliateFichaEmailJob $job): bool => $job->to === ['afiliado@correo.com']
        && $job->cc === ['testigo1@correo.com', 'testigo2@correo.com']
        && $job->bcc === ['oculta1@correo.com', 'oculta2@correo.com']
        && $job->scope === 'todo'
        && $job->message === 'Hola');
});

it('no encola el correo si falta el destinatario, hay inválidos o se pasa del tope', function (array $to, array $cc, string $expected): void {
    Queue::fake();

    $error = AffiliateFichaDelivery::queueEmail(fichaSender(), AffiliateFichaData::KIND_INDIVIDUAL, 15, AffiliateFichaScope::Administration, 'operations', $to, $cc, [], null);

    expect($error)->toContain($expected);
    Queue::assertNothingPushed();
})->with([
    'sin Para' => [[], ['a@correo.com'], 'al menos un destinatario'],
    'inválido' => [['a@correo.com'], ['malo@'], 'malo@'],
    'demasiados CC' => [['a@correo.com'], array_map(fn (int $i): string => "c{$i}@correo.com", range(1, 11)), 'hasta 10'],
]);

it('no envía datos clínicos sin el permiso', function (): void {
    Queue::fake();

    $error = AffiliateFichaDelivery::queueEmail(fichaUser(['NEGOCIOS']), AffiliateFichaData::KIND_INDIVIDUAL, 15, AffiliateFichaScope::Clinical, 'business', ['a@correo.com'], [], [], null);

    expect($error)->toContain('operaciones y telemedicina');
    Queue::assertNothingPushed();
});

it('encola el WhatsApp solo con un número válido', function (): void {
    Queue::fake();

    expect(AffiliateFichaDelivery::queueWhatsApp(fichaSender(), AffiliateFichaData::KIND_CORPORATE, 15, AffiliateFichaScope::Administration, 'operations', '123', null))
        ->toContain('no es válido');
    Queue::assertNothingPushed();

    expect(AffiliateFichaDelivery::queueWhatsApp(fichaSender(), AffiliateFichaData::KIND_CORPORATE, 15, AffiliateFichaScope::Administration, 'operations', '04121234567', 'Saludos'))
        ->toBeNull();
    Queue::assertPushed(SendAffiliateFichaWhatsAppJob::class, fn (SendAffiliateFichaWhatsAppJob $job): bool => $job->phone === '04121234567' && $job->message === 'Saludos');
});

it('el job envía un solo correo con el PDF adjunto, CC y CCO', function (): void {
    $affiliate = Affiliate::query()->whereHas('affiliation')->latest('id')->first();

    if ($affiliate === null) {
        $this->markTestSkipped('No hay afiliados individuales en la base.');
    }

    $sender = User::query()->whereJsonContains('departament', 'SUPERADMIN')->first();

    if ($sender === null) {
        $this->markTestSkipped('No hay un usuario SUPERADMIN para el job.');
    }

    Mail::fake();

    (new SendAffiliateFichaEmailJob(AffiliateFichaData::KIND_INDIVIDUAL, (int) $affiliate->id, 'administracion', 'business', (int) $sender->id, ['para@correo.com'], ['cc1@correo.com', 'cc2@correo.com'], ['cco@correo.com'], 'Mensaje'))->handle();

    Mail::assertSent(AffiliateFichaMail::class, fn (AffiliateFichaMail $mail): bool => $mail->hasTo('para@correo.com')
        && $mail->hasCc('cc1@correo.com') && $mail->hasCc('cc2@correo.com')
        && $mail->hasBcc('cco@correo.com')
        && str_starts_with($mail->pdfBinary, '%PDF')
        && $mail->analystMessage === 'Mensaje');
    Mail::assertSentCount(1);
});

it('el borrado del archivo temporal de WhatsApp solo toca su carpeta', function (): void {
    $directory = public_path('storage/'.SendAffiliateFichaWhatsAppJob::PUBLIC_DIRECTORY);
    @mkdir($directory, 0755, true);
    $name = str_repeat('a', 47).'Z.pdf';
    file_put_contents($directory.'/'.$name, '%PDF-prueba');
    $outside = public_path('storage/no-borrar-ficha-prueba.pdf');
    file_put_contents($outside, '%PDF-prueba');

    (new DeleteAffiliateFichaPublicFileJob(SendAffiliateFichaWhatsAppJob::PUBLIC_DIRECTORY.'/'.$name))->handle();
    (new DeleteAffiliateFichaPublicFileJob('no-borrar-ficha-prueba.pdf'))->handle();
    (new DeleteAffiliateFichaPublicFileJob(SendAffiliateFichaWhatsAppJob::PUBLIC_DIRECTORY.'/../no-borrar-ficha-prueba.pdf'))->handle();

    expect(is_file($directory.'/'.$name))->toBeFalse()
        ->and(is_file($outside))->toBeTrue();

    @unlink($outside);
});

it('la modal de Operaciones abre con la vista previa y envía por correo desde el pie', function (): void {
    $affiliate = Affiliate::query()->whereHas('affiliation')->latest('id')->first();

    if ($affiliate === null) {
        $this->markTestSkipped('No hay afiliados individuales en la base.');
    }

    Queue::fake();
    $this->actingAs(fichaSender());
    Filament::setCurrentPanel('operations');

    Livewire::test(ListAffiliates::class)
        ->mountTableAction('affiliateFicha', $affiliate)
        ->assertMountedActionModalSeeHtml('Vista previa de la ficha del afiliado')
        ->assertMountedActionModalSee('Enviar por correo')
        ->set('mountedActions.0.data.ficha_scope', 'todo')
        ->set('mountedActions.0.data.ficha_to', ['para@correo.com'])
        ->set('mountedActions.0.data.ficha_cc', ['cc@correo.com'])
        ->set('mountedActions.0.data.ficha_bcc', ['cco1@correo.com', 'cco2@correo.com'])
        ->callMountedTableAction(['channel' => 'correo'])
        ->assertNotified('Ficha en camino por correo');

    Queue::assertPushed(SendAffiliateFichaEmailJob::class, fn (SendAffiliateFichaEmailJob $job): bool => $job->affiliateId === (int) $affiliate->id
        && $job->bcc === ['cco1@correo.com', 'cco2@correo.com']
        && $job->panelId === 'operations');
});

it('la modal no cierra ni encola si el envío por correo no tiene destinatario', function (): void {
    $affiliate = Affiliate::query()->whereHas('affiliation')->latest('id')->first();

    if ($affiliate === null) {
        $this->markTestSkipped('No hay afiliados individuales en la base.');
    }

    Queue::fake();
    $this->actingAs(fichaSender());
    Filament::setCurrentPanel('operations');

    Livewire::test(ListAffiliates::class)
        ->mountTableAction('affiliateFicha', $affiliate)
        ->set('mountedActions.0.data.ficha_scope', 'administracion')
        ->set('mountedActions.0.data.ficha_to', [])
        ->callMountedTableAction(['channel' => 'correo'])
        ->assertNotified('No se envió la ficha');

    Queue::assertNothingPushed();
});

it('la URL de la vista previa no cambia entre re-renders de la modal y vence en el futuro', function (): void {
    $first = AffiliateFichaPdfController::url(AffiliateFichaData::KIND_INDIVIDUAL, 15, AffiliateFichaScope::All, 'business', AffiliateFichaPdfController::MODE_PREVIEW);
    $this->travel(20)->seconds();
    $second = AffiliateFichaPdfController::url(AffiliateFichaData::KIND_INDIVIDUAL, 15, AffiliateFichaScope::All, 'business', AffiliateFichaPdfController::MODE_PREVIEW);

    parse_str((string) parse_url($first, PHP_URL_QUERY), $query);

    expect((int) $query['expires'])->toBeGreaterThanOrEqual(now()->addMinutes(30)->getTimestamp() - 30)
        ->and((int) $query['expires'])->toBeLessThanOrEqual(now()->addMinutes(61)->getTimestamp());

    if (intdiv(now()->subSeconds(20)->getTimestamp(), 1800) === intdiv(now()->getTimestamp(), 1800)) {
        expect($second)->toBe($first);
    }
});

it('la ficha abre como panel lateral a la derecha en individuales y corporativos', function (string $kind): void {
    expect(AffiliateFichaAction::make($kind)->isModalSlideOver())->toBeTrue();
})->with([AffiliateFichaData::KIND_INDIVIDUAL, AffiliateFichaData::KIND_CORPORATE]);
