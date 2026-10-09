<?php

declare(strict_types=1);

use App\Enums\SystemNotificationKey;
use App\Jobs\NotifySuperAdminsOfEarlyRenovationJob;
use App\Jobs\SendNotificacionWhatsApp;
use App\Mail\EarlyRenovationAcceptedMail;
use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\AffiliationRenovationHistory;
use App\Models\Renovation;
use App\Models\RenovationCorporate;
use App\Models\SystemNotificationRecipientSetting;
use App\Models\User;
use App\Services\AcceptAffiliationCorporateRenovationsService;
use App\Services\AcceptAffiliationRenovationsService;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Renovations\EarlyRenovationAcceptance;
use App\Support\Renovations\EarlyRenovationAuthorization;
use App\Support\Renovations\EarlyRenovationNotificationPayload;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Esquema mínimo en una conexión sqlite `:memory:` propia: este test escribe y,
 * con o sin config cacheada, nunca debe tocar la base real.
 */
function seedEarlyRenovationSchema(): void
{
    Schema::create('plans', function (Blueprint $table): void {
        $table->id();
        $table->string('description')->nullable();
        $table->string('pricing_mode')->nullable();
        $table->timestamps();
    });

    Schema::create('age_ranges', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('plan_id')->nullable();
        $table->unsignedBigInteger('coverage_id')->nullable();
        $table->string('range')->nullable();
        $table->integer('age_init')->nullable();
        $table->integer('age_end')->nullable();
        $table->timestamps();
    });

    Schema::create('fees', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('plan_id')->nullable();
        $table->unsignedBigInteger('age_range_id')->nullable();
        $table->unsignedBigInteger('coverage_id')->nullable();
        $table->string('coverage')->nullable();
        $table->string('range')->nullable();
        $table->decimal('price', 10, 2)->default(0);
        $table->string('status')->nullable();
        $table->timestamps();
    });

    Schema::create('coverages', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('plan_id')->nullable();
        $table->decimal('price', 10, 2)->default(0);
        $table->timestamps();
    });

    Schema::create('individual_quotes', function (Blueprint $table): void {
        $table->id();
        $table->string('code')->nullable();
        $table->timestamps();
    });

    Schema::create('affiliations', function (Blueprint $table): void {
        $table->id();
        $table->string('code');
        $table->string('status');
        $table->string('code_agency');
        $table->string('owner_code')->nullable();
        $table->string('owner_agent')->nullable();
        $table->string('agent_id')->nullable();
        $table->unsignedBigInteger('individual_quote_id')->nullable();
        $table->string('code_individual_quote')->nullable();
        $table->unsignedBigInteger('plan_id')->nullable();
        $table->unsignedBigInteger('coverage_id')->nullable();
        $table->string('payment_frequency')->nullable();
        $table->string('effective_date')->nullable();
        $table->string('full_name_ti')->nullable();
        $table->string('nro_identificacion_ti')->nullable();
        $table->string('phone_ti')->nullable();
        $table->string('email_ti')->nullable();
        $table->string('birth_date_ti')->nullable();
        $table->integer('age')->nullable();
        $table->integer('family_members')->nullable();
        $table->decimal('fee_anual', 10, 2)->default(0);
        $table->decimal('total_amount', 10, 2)->default(0);
        $table->timestamps();
    });

    Schema::create('affiliates', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliation_id');
        $table->string('full_name');
        $table->string('nro_identificacion');
        $table->string('relationship');
        $table->string('sex');
        $table->string('birth_date')->nullable();
        $table->integer('age')->nullable();
        $table->string('status')->nullable();
        $table->unsignedBigInteger('plan_id')->nullable();
        $table->unsignedBigInteger('coverage_id')->nullable();
        $table->unsignedBigInteger('age_range_id')->nullable();
        $table->decimal('fee', 10, 2)->default(0);
        $table->decimal('total_amount', 10, 2)->default(0);
        $table->string('payment_frequency')->nullable();
        $table->timestamps();
    });

    Schema::create('renovations', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliation_id');
        $table->date('date_renewal');
        $table->integer('remaining_days')->nullable();
        $table->string('status');
        $table->string('created_by');
        $table->string('updated_by');
        $table->string('code_affiliation');
        $table->string('agent_id');
        $table->string('code_agency');
        $table->string('owner_code')->nullable();
        $table->string('owner_agent')->nullable();
        $table->unsignedBigInteger('plan_id');
        $table->unsignedBigInteger('coverage_id')->nullable();
        $table->unsignedBigInteger('age_range_id');
        $table->date('birth_date')->nullable();
        $table->integer('age')->nullable();
        $table->decimal('fee', 10, 2);
        $table->decimal('subtotal_anual', 10, 2);
        $table->decimal('subtotal_quarterly', 10, 2);
        $table->decimal('subtotal_biannual', 10, 2);
        $table->decimal('subtotal_monthly', 10, 2);
        $table->integer('total_persons');
        $table->string('payment_frequency');
        $table->boolean('is_negotiation_candidate')->default(false);
        $table->text('negotiation_notes')->nullable();
        $table->unsignedBigInteger('previous_plan_id')->nullable();
        $table->timestamps();
    });

    Schema::create('affiliation_renovation_histories', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliation_id');
        $table->unsignedBigInteger('affiliate_id')->nullable();
        $table->unsignedBigInteger('source_renovation_id')->nullable();
        $table->timestamp('accepted_at')->nullable();
        $table->string('accepted_by')->nullable();
        $table->unsignedBigInteger('accepted_by_user_id')->nullable();
        $table->string('previous_effective_date')->nullable();
        $table->string('new_effective_date')->nullable();
        $table->date('date_renewal')->nullable();
        $table->integer('remaining_days_at_accept')->nullable();
        $table->string('status_at_accept')->nullable();
        $table->boolean('is_early_acceptance')->default(false);
        $table->integer('days_before_renewal_at_accept')->nullable();
        $table->text('early_acceptance_reason')->nullable();
        $table->string('code_affiliation')->nullable();
        $table->string('agent_id')->nullable();
        $table->string('code_agency')->nullable();
        $table->string('owner_code')->nullable();
        $table->string('owner_agent')->nullable();
        $table->unsignedBigInteger('plan_id')->nullable();
        $table->unsignedBigInteger('coverage_id')->nullable();
        $table->unsignedBigInteger('age_range_id')->nullable();
        $table->date('birth_date')->nullable();
        $table->integer('age')->nullable();
        $table->decimal('fee', 10, 2)->default(0);
        $table->decimal('subtotal_anual', 10, 2)->default(0);
        $table->decimal('subtotal_quarterly', 10, 2)->default(0);
        $table->decimal('subtotal_biannual', 10, 2)->default(0);
        $table->decimal('subtotal_monthly', 10, 2)->default(0);
        $table->integer('total_persons')->nullable();
        $table->string('payment_frequency')->nullable();
        $table->boolean('is_negotiation_candidate')->default(false);
        $table->text('negotiation_notes')->nullable();
        $table->unsignedBigInteger('previous_plan_id')->nullable();
        $table->timestamps();
    });

    Schema::create('collections', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('sale_id')->nullable();
        $table->string('include_date')->nullable();
        $table->string('owner_code')->nullable();
        $table->string('code_agency')->nullable();
        $table->unsignedBigInteger('plan_id')->nullable();
        $table->unsignedBigInteger('coverage_id')->nullable();
        $table->string('agent_id')->nullable();
        $table->string('collection_invoice_number')->nullable();
        $table->string('quote_number')->nullable();
        $table->string('affiliation_code')->nullable();
        $table->string('affiliate_full_name')->nullable();
        $table->string('affiliate_contact')->nullable();
        $table->string('affiliate_ci_rif')->nullable();
        $table->string('affiliate_phone')->nullable();
        $table->string('affiliate_email')->nullable();
        $table->string('affiliate_status')->nullable();
        $table->string('type')->nullable();
        $table->string('service')->nullable();
        $table->string('persons')->nullable();
        $table->decimal('total_amount', 10, 2)->default(0);
        $table->string('payment_method')->nullable();
        $table->string('payment_frequency')->nullable();
        $table->string('next_payment_date')->nullable();
        $table->string('filter_next_payment_date')->nullable();
        $table->string('expiration_date')->nullable();
        $table->string('status')->nullable();
        $table->integer('days')->default(0);
        $table->string('created_by')->nullable();
        $table->timestamps();
    });

    Schema::create('affiliation_corporates', function (Blueprint $table): void {
        $table->id();
        $table->string('name_corporate')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });

    Schema::create('affiliate_corporates', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliation_corporate_id');
        $table->string('status')->nullable();
        $table->timestamps();
    });

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->string('status')->nullable();
        $table->text('departament')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });

    Schema::create('system_notification_recipient_settings', function (Blueprint $table): void {
        $table->id();
        $table->string('notification_key');
        $table->json('notification_emails')->nullable();
        $table->json('notification_phones')->nullable();
        $table->boolean('is_active')->default(true);
        $table->string('updated_by')->nullable();
        $table->timestamps();
    });

    Schema::create('logs', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('action')->nullable();
        $table->string('route')->nullable();
        $table->text('response')->nullable();
        $table->string('method')->nullable();
        $table->string('ip')->nullable();
        $table->text('user_agent')->nullable();
        $table->timestamps();
    });

    DB::table('plans')->insert(['id' => 1, 'description' => 'PLAN INICIAL', 'pricing_mode' => 'PAQUETE']);
    DB::table('age_ranges')->insert(['id' => 1, 'plan_id' => 1, 'range' => '0 a 99', 'age_init' => 0, 'age_end' => 99]);
    DB::table('fees')->insert(['id' => 1, 'plan_id' => 1, 'age_range_id' => 1, 'coverage_id' => null, 'range' => '0 a 99', 'price' => 160, 'status' => 'ACTIVO']);
    DB::table('users')->insert([
        ['id' => 501, 'name' => 'Superadmin Uno', 'email' => 'superadmin1@tudrencasa.com', 'phone' => '04121112233', 'status' => 'ACTIVO', 'departament' => '["SUPERADMIN","NEGOCIOS"]'],
        ['id' => 502, 'name' => 'Superadmin Inactivo', 'email' => 'superadmin2@tudrencasa.com', 'phone' => '04121112244', 'status' => 'INACTIVO', 'departament' => '["SUPERADMIN"]'],
        ['id' => 503, 'name' => 'Analista', 'email' => 'analista@tudrencasa.com', 'phone' => '04121112255', 'status' => 'ACTIVO', 'departament' => '["NEGOCIOS"]'],
    ]);
}

beforeEach(function (): void {
    config()->set('database.connections.early_renovation_testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    $this->previousConnection = config('database.default');
    config()->set('database.default', 'early_renovation_testing');
    DB::purge('early_renovation_testing');
    DB::setDefaultConnection('early_renovation_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:');

    seedEarlyRenovationSchema();
    EarlyRenovationAcceptance::flushPermissionCache();
    Filament::setCurrentPanel('business');
});

afterEach(function (): void {
    DB::purge('early_renovation_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
    EarlyRenovationAcceptance::flushPermissionCache();
    Auth::forgetUser();
});

/**
 * Afiliación ACTIVA del Plan Inicial (paquete, tarifa única 0–99 años) con su
 * renovación a `$daysUntilRenewal` días.
 */
function earlyRenovationFixture(int $daysUntilRenewal, string $status = 'VIGENTE', int $planId = 1, string $birthDate = '15/03/1985'): Renovation
{
    $renewalDate = Carbon::today()->addDays($daysUntilRenewal);
    $code = 'TEST-EARLY-'.strtoupper(bin2hex(random_bytes(4)));

    $affiliation = Affiliation::query()->create([
        'code' => $code,
        'status' => 'ACTIVA',
        'code_agency' => 'TDG-100',
        'owner_code' => 'TDG-100',
        'plan_id' => $planId,
        'coverage_id' => null,
        'payment_frequency' => 'ANUAL',
        'effective_date' => $renewalDate->copy()->subYear()->format('d/m/Y'),
        'full_name_ti' => 'TITULAR PRUEBA ANTICIPADA',
        'fee_anual' => 160,
        'total_amount' => 160,
    ]);

    Affiliate::query()->create([
        'affiliation_id' => $affiliation->id,
        'full_name' => 'TITULAR PRUEBA ANTICIPADA',
        'nro_identificacion' => '99'.random_int(100000, 999999),
        'relationship' => 'TITULAR',
        'sex' => 'MASCULINO',
        'birth_date' => $birthDate,
        'status' => 'ACTIVO',
        'plan_id' => $planId,
        'age_range_id' => 1,
        'fee' => 160,
        'total_amount' => 160,
        'payment_frequency' => 'ANUAL',
    ]);

    return Renovation::query()->create([
        'affiliation_id' => $affiliation->id,
        'date_renewal' => $renewalDate->toDateString(),
        'remaining_days' => $daysUntilRenewal,
        'status' => $status,
        'created_by' => 'SISTEMA',
        'updated_by' => 'SISTEMA',
        'code_affiliation' => $code,
        'agent_id' => '',
        'code_agency' => 'TDG-100',
        'owner_code' => 'TDG-100',
        'plan_id' => $planId,
        'coverage_id' => null,
        'age_range_id' => 1,
        'fee' => 160,
        'subtotal_anual' => 160,
        'subtotal_quarterly' => 40,
        'subtotal_biannual' => 80,
        'subtotal_monthly' => 13.33,
        'total_persons' => 1,
        'payment_frequency' => 'ANUAL',
        'is_negotiation_candidate' => false,
    ]);
}

function earlyAuthorization(): EarlyRenovationAuthorization
{
    return new EarlyRenovationAuthorization(
        reason: 'El cliente pidió renovar antes de viajar y ya pagó.',
        userId: 1,
        userName: 'Analista de prueba',
        userEmail: 'analista@tudrencasa.com',
    );
}

function actingAsDepartments(array $departments, int $id = 999_001): User
{
    $user = User::factory()->make([
        'name' => 'Usuario prueba',
        'email' => 'prueba'.$id.'@tudrencasa.com',
        'departament' => $departments,
        'status' => 'ACTIVO',
    ]);
    $user->id = $id;
    $user->setRelation('permissions', new EloquentCollection);
    Auth::setUser($user);

    return $user;
}

it('sin autorización omite la renovación fuera de período y explica por qué', function (): void {
    Queue::fake();
    $renovation = earlyRenovationFixture(48);

    $result = app(AcceptAffiliationRenovationsService::class)
        ->accept(new EloquentCollection([$renovation]), 'Analista de prueba');

    expect($result->accepted)->toBe(0)
        ->and($result->skipped)->toBe(1)
        ->and($result->earlyAccepted)->toBe(0)
        ->and($result->messages[0])->toContain('faltan 48 días')
        ->and($result->messages[0])->toContain('permiso de renovación anticipada')
        ->and(Renovation::query()->whereKey($renovation->id)->exists())->toBeTrue()
        ->and(AffiliationRenovationHistory::query()->where('source_renovation_id', $renovation->id)->exists())->toBeFalse();

    Queue::assertNotPushed(NotifySuperAdminsOfEarlyRenovationJob::class);
});

it('con autorización renueva antes de tiempo, deja la traza y conserva el aniversario', function (): void {
    Queue::fake();
    $renovation = earlyRenovationFixture(48);
    $renewalDate = $renovation->date_renewal->format('d/m/Y');

    $result = app(AcceptAffiliationRenovationsService::class)
        ->accept(new EloquentCollection([$renovation]), 'Analista de prueba', null, earlyAuthorization());

    expect($result->accepted)->toBe(1)
        ->and($result->skipped)->toBe(0)
        ->and($result->earlyAccepted)->toBe(1)
        ->and(Renovation::query()->whereKey($renovation->id)->exists())->toBeFalse();

    $history = AffiliationRenovationHistory::query()->where('source_renovation_id', $renovation->id)->firstOrFail();

    expect($history->is_early_acceptance)->toBeTrue()
        ->and($history->days_before_renewal_at_accept)->toBe(48)
        ->and($history->early_acceptance_reason)->toBe('El cliente pidió renovar antes de viajar y ya pagó.')
        ->and($history->accepted_by_user_id)->toBe(1)
        ->and($history->status_at_accept)->toBe('VIGENTE')
        ->and($history->new_effective_date)->toBe($renewalDate)
        ->and(Affiliation::query()->find($renovation->affiliation_id)->effective_date)->toBe($renewalDate);

    Queue::assertPushed(NotifySuperAdminsOfEarlyRenovationJob::class, function (NotifySuperAdminsOfEarlyRenovationJob $job) use ($renovation): bool {
        return $job->payload['kind'] === 'individual'
            && count($job->payload['items']) === 1
            && $job->payload['items'][0]['affiliation_code'] === $renovation->code_affiliation
            && $job->payload['items'][0]['days_before_renewal'] === 48
            && $job->payload['reason'] === 'El cliente pidió renovar antes de viajar y ya pagó.';
    });
});

it('la tarifa anticipada usa la edad que tendrá el titular en la fecha de renovación', function (): void {
    Queue::fake();

    DB::table('plans')->insert(['id' => 7, 'description' => 'PAQUETE POR EDAD', 'pricing_mode' => 'PAQUETE']);
    DB::table('age_ranges')->insert([
        ['id' => 70, 'plan_id' => 7, 'range' => '0 a 40', 'age_init' => 0, 'age_end' => 40],
        ['id' => 71, 'plan_id' => 7, 'range' => '41 a 99', 'age_init' => 41, 'age_end' => 99],
    ]);
    DB::table('fees')->insert([
        ['plan_id' => 7, 'age_range_id' => 70, 'coverage_id' => null, 'range' => '0 a 40', 'price' => 160, 'status' => 'ACTIVO'],
        ['plan_id' => 7, 'age_range_id' => 71, 'coverage_id' => null, 'range' => '41 a 99', 'price' => 300, 'status' => 'ACTIVO'],
    ]);

    /** Tiene 40 hoy y cumple 41 a los 30 días, antes de la renovación (a 60 días). */
    $birthDate = Carbon::today()->addDays(30)->subYears(41)->format('d/m/Y');
    $renovation = earlyRenovationFixture(60, 'VIGENTE', 7, $birthDate);

    app(AcceptAffiliationRenovationsService::class)
        ->accept(new EloquentCollection([$renovation]), 'Analista de prueba', null, earlyAuthorization());

    $titular = Affiliate::query()->where('affiliation_id', $renovation->affiliation_id)->firstOrFail();

    expect((float) $titular->fee)->toBe(300.0)
        ->and((int) $titular->age_range_id)->toBe(71)
        ->and((int) $titular->age)->toBe(41);
});

it('una renovación en período no se marca como anticipada aunque llegue la autorización', function (): void {
    Queue::fake();
    $renovation = earlyRenovationFixture(20, 'PERIODO DE RENOVACION');

    $result = app(AcceptAffiliationRenovationsService::class)
        ->accept(new EloquentCollection([$renovation]), 'Analista de prueba', null, earlyAuthorization());

    $history = AffiliationRenovationHistory::query()->where('source_renovation_id', $renovation->id)->firstOrFail();

    expect($result->accepted)->toBe(1)
        ->and($result->earlyAccepted)->toBe(0)
        ->and($history->is_early_acceptance)->toBeFalse()
        ->and($history->early_acceptance_reason)->toBeNull();

    Queue::assertNotPushed(NotifySuperAdminsOfEarlyRenovationJob::class);
});

it('en una selección mixta sin autorización acepta la que está en período y omite la anticipada', function (): void {
    Queue::fake();
    $inPeriod = earlyRenovationFixture(10, 'PERIODO DE RENOVACION');
    $early = earlyRenovationFixture(90);

    $result = app(AcceptAffiliationRenovationsService::class)
        ->accept(new EloquentCollection([$inPeriod, $early]), 'Analista de prueba');

    expect($result->accepted)->toBe(1)
        ->and($result->skipped)->toBe(1)
        ->and($result->messages[0])->toContain($early->code_affiliation)
        ->and(Renovation::query()->whereKey($early->id)->exists())->toBeTrue();
});

it('la renovación corporativa fuera de período también exige autorización', function (): void {
    $renovation = new RenovationCorporate([
        'code_affiliation' => 'TDEC-COR-TEST',
        'status' => 'VIGENTE',
        'date_renewal' => Carbon::today()->addDays(70)->toDateString(),
    ]);

    $result = app(AcceptAffiliationCorporateRenovationsService::class)
        ->accept(new EloquentCollection([$renovation]), 'Analista de prueba');

    expect($result->accepted)->toBe(0)
        ->and($result->skipped)->toBe(1)
        ->and($result->messages[0])->toContain('faltan 70 días');
});

it('el formulario sin confirmar no arma autorización', function (): void {
    actingAsDepartments(['SUPERADMIN']);

    expect(EarlyRenovationAcceptance::authorizationFromFormData([]))->toBeNull()
        ->and(EarlyRenovationAcceptance::authorizationFromFormData(['early_confirmed' => false, 'early_reason' => 'Motivo suficientemente largo']))->toBeNull();
});

it('rechaza la confirmación de un usuario sin el permiso aunque fuerce el formulario', function (): void {
    actingAsDepartments(['NEGOCIOS'], 999_002);

    expect(EarlyRenovationAcceptance::currentUserCan())->toBeFalse();

    EarlyRenovationAcceptance::authorizationFromFormData([
        'early_confirmed' => true,
        'early_reason' => 'Motivo suficientemente largo para pasar',
    ]);
})->throws(InvalidArgumentException::class, 'No tiene permiso');

it('exige un motivo de al menos 15 caracteres sin contar etiquetas ni espacios', function (): void {
    actingAsDepartments(['SUPERADMIN']);

    EarlyRenovationAcceptance::authorizationFromFormData([
        'early_confirmed' => true,
        'early_reason' => '  <b>corto</b>      ',
    ]);
})->throws(InvalidArgumentException::class, 'mínimo 15');

it('arma la autorización con el usuario y el motivo limpio', function (): void {
    actingAsDepartments(['SUPERADMIN'], 999_003);

    $authorization = EarlyRenovationAcceptance::authorizationFromFormData([
        'early_confirmed' => true,
        'early_reason' => "  El cliente   pidió\nrenovar antes  ",
    ]);

    expect($authorization)->toBeInstanceOf(EarlyRenovationAuthorization::class)
        ->and($authorization->userId)->toBe(999_003)
        ->and($authorization->reason)->toBe('El cliente pidió renovar antes');
});

it('el aviso de WhatsApp y el asunto dicen qué se renovó, cuánto faltaba, quién y por qué', function (): void {
    $payload = EarlyRenovationNotificationPayload::build('individual', [[
        'history_id' => 10,
        'affiliation_code' => 'TDEC-IND-000256',
        'holder' => 'MARÍA PÉREZ',
        'date_renewal' => '25/11/2026',
        'days_before_renewal' => 48,
        'plan' => 'PLAN IDEAL',
        'annual_amount' => 311.0,
        'payment_frequency' => 'TRIMESTRAL',
        'total_persons' => 1,
        'url' => null,
    ]], earlyAuthorization());

    $body = EarlyRenovationNotificationPayload::whatsappBody($payload);

    expect(EarlyRenovationNotificationPayload::emailSubject($payload))->toBe('Renovación anticipada · TDEC-IND-000256 · faltaban 48 días')
        ->and($body)->toContain('RENOVACIÓN ANTICIPADA')
        ->and($body)->toContain('TDEC-IND-000256')
        ->and($body)->toContain('faltaban 48 días')
        ->and($body)->toContain('se abre a 30 días')
        ->and($body)->toContain('Analista de prueba')
        ->and($body)->toContain('El cliente pidió renovar antes de viajar y ya pagó.')
        ->and($body)->toContain('US$ 311,00');
});

it('el job avisa por correo y WhatsApp a los SUPERADMIN y contactos configurados', function (): void {
    Mail::fake();
    Bus::fake([SendNotificacionWhatsApp::class]);

    $setting = SystemNotificationRecipientSetting::for(SystemNotificationKey::EarlyRenovationAcceptance);
    $setting->update([
        'notification_emails' => ['control-renovaciones@tudrencasa.com'],
        'notification_phones' => ['04141234567'],
        'is_active' => true,
    ]);

    (new NotifySuperAdminsOfEarlyRenovationJob(
        EarlyRenovationNotificationPayload::build('individual', [['history_id' => 1, 'affiliation_code' => 'X', 'days_before_renewal' => 40]], earlyAuthorization()),
    ))->handle();

    $sentTo = Mail::sent(EarlyRenovationAcceptedMail::class)->map(fn (EarlyRenovationAcceptedMail $mail): string => $mail->recipientEmail)->sort()->values()->all();

    expect($sentTo)->toBe(['control-renovaciones@tudrencasa.com', 'superadmin1@tudrencasa.com']);
    Bus::assertDispatchedSyncTimes(SendNotificacionWhatsApp::class, 2);
});

it('con la alerta inactiva en el Centro de notificaciones no envía nada', function (): void {
    Mail::fake();
    Bus::fake([SendNotificacionWhatsApp::class]);

    SystemNotificationRecipientSetting::for(SystemNotificationKey::EarlyRenovationAcceptance)->update(['is_active' => false]);

    (new NotifySuperAdminsOfEarlyRenovationJob(
        EarlyRenovationNotificationPayload::build('individual', [['history_id' => 1, 'affiliation_code' => 'X']], earlyAuthorization()),
    ))->handle();

    Mail::assertNothingSent();
    Bus::assertNotDispatchedSync(SendNotificacionWhatsApp::class);
});

it('la alerta está en el Centro de notificaciones y no pausa la renovación', function (): void {
    $key = SystemNotificationKey::EarlyRenovationAcceptance;

    expect(SystemNotificationKey::managed())->toContain($key)
        ->and($key->label())->toBe('Renovación anticipada')
        ->and($key->pausesScheduledTask())->toBeFalse()
        ->and($key->flowSteps())->toHaveCount(4)
        ->and($key->defaultEmails())->toBe([])
        ->and($key->defaultPhones())->toBe([]);
});

it('el permiso existe en Negocios y en Administración', function (): void {
    $definition = BusinessFilamentActionPermissionRegistry::all()[BusinessFilamentActionPermissionRegistry::ACCEPT_EARLY_RENOVATION];

    expect(BusinessFilamentActionPermissionRegistry::ACCEPT_EARLY_RENOVATION)->toBe('aceptar-renovacion-anticipada')
        ->and($definition['modules'])->toBe(['NEGOCIOS', 'ADMINISTRACION'])
        ->and(EarlyRenovationAcceptance::PERMISSION)->toBe('aceptar-renovacion-anticipada');
});

it('las tablas muestran la acción anticipada solo con permiso y la marcan en ámbar', function (string $path): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/'.$path);

    expect($source)
        ->toContain('->visible(fn (Renovation')
        ->toContain('! EarlyRenovationAcceptance::isEarly($record) || EarlyRenovationAcceptance::currentUserCan()')
        ->toContain("'Renovar anticipadamente'")
        ->toContain('EarlyRenovationAcceptance::authorizationFromFormData($data)');
})->with([
    'individuales' => 'Renovations/RenovationsTable.php',
    'corporativas' => 'RenovationCorporates/RenovationsCorporateTable.php',
]);

it('el formulario pide confirmación y motivo, y calcula la tarifa a la fecha de renovación', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Renovations/AcceptRenovationActionForm.php');
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/AcceptAffiliationRenovationsService.php');

    expect($form)
        ->toContain("Checkbox::make('early_confirmed')")
        ->toContain("Textarea::make('early_reason')")
        ->toContain('Está renovando antes del período configurado');

    expect($service)->toContain('$renovation->date_renewal->copy()->startOfDay()');
});

it('el histórico tiene pestaña y filtro de anticipadas', function (string $path): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/'.$path))
        ->toContain("'anticipadas' => Tab::make('Anticipadas')")
        ->toContain("TernaryFilter::make('is_early_acceptance')");
})->with([
    'individuales' => 'RenovationHistories/RenovationHistoriesTable.php',
    'corporativas' => 'RenovationCorporateHistories/RenovationCorporateHistoriesTable.php',
]);

it('el correo se renderiza con la tabla de renovaciones, el motivo y quién autorizó', function (): void {
    $payload = EarlyRenovationNotificationPayload::build('corporate', [[
        'history_id' => 3,
        'affiliation_code' => 'TDEC-COR-000010',
        'holder' => 'EMPRESA DEMO C.A.',
        'date_renewal' => '01/01/2027',
        'days_before_renewal' => 85,
        'plan' => 'PLAN ESPECIAL',
        'annual_amount' => 12500.5,
        'payment_frequency' => 'SEMESTRAL',
        'total_persons' => 25,
        'url' => 'https://www.integracorp.test/business/historico/3',
    ]], earlyAuthorization());

    $html = (new EarlyRenovationAcceptedMail($payload, 'superadmin1@tudrencasa.com', 'Asunto'))->render();

    expect($html)
        ->toContain('Renovación anticipada')
        ->toContain('TDEC-COR-000010')
        ->toContain('EMPRESA DEMO C.A.')
        ->toContain('Empresa')
        ->toContain('faltaban 85 días')
        ->toContain('US$ 12.500,50')
        ->toContain('25 personas')
        ->toContain('El cliente pidió renovar antes de viajar y ya pagó.')
        ->toContain('Analista de prueba')
        ->toContain('https://www.integracorp.test/business/historico/3');
});
