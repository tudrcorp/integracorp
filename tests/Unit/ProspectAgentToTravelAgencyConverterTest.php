<?php

declare(strict_types=1);

use App\Filament\Business\Resources\ProspectAgents\Pages\EditProspectAgent;
use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;
use App\Models\ProspectAgent;
use App\Models\ProspectAgentContact;
use App\Models\TravelAgency;
use App\Models\TravelAgent;
use App\Observers\ProspectAgentTravelAgencyObserver;
use App\Providers\AppServiceProvider;
use App\Support\ProspectAgents\ProspectAgentToTravelAgencyConverter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    ensureSqliteInMemoryDatabaseOrSkip();

    Schema::dropIfExists('travel_agents');
    Schema::dropIfExists('travel_agencies');
    Schema::dropIfExists('prospect_agent_contacts');
    Schema::dropIfExists('prospect_agents');

    Schema::create('prospect_agents', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('travel_agency_id')->nullable()->unique();
        $table->string('name')->nullable();
        $table->string('type')->nullable();
        $table->string('phone_1')->nullable();
        $table->string('phone_2')->nullable();
        $table->string('email')->nullable();
        $table->text('address')->nullable();
        $table->string('instagram')->nullable();
        $table->unsignedBigInteger('country_id')->nullable();
        $table->unsignedBigInteger('state_id')->nullable();
        $table->unsignedBigInteger('city_id')->nullable();
        $table->string('status')->nullable();
        $table->string('created_by')->nullable();
        $table->string('updated_by')->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    Schema::create('prospect_agent_contacts', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('prospect_agent_id');
        $table->string('name')->nullable();
        $table->string('position')->nullable();
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->string('local_phone')->nullable();
        $table->integer('sort_order')->default(0);
        $table->timestamps();
    });

    Schema::create('travel_agencies', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('prospect_agent_id')->nullable()->unique();
        $table->string('status')->nullable();
        $table->string('fechaIngreso')->nullable();
        $table->string('representante')->nullable();
        $table->string('name')->nullable();
        $table->unsignedBigInteger('country_id')->nullable();
        $table->unsignedBigInteger('state_id')->nullable();
        $table->unsignedBigInteger('city_id')->nullable();
        $table->string('address')->nullable();
        $table->string('phone')->nullable();
        $table->string('phoneAdditional')->nullable();
        $table->string('email')->nullable();
        $table->string('userInstagram')->nullable();
        $table->string('classification')->nullable();
        $table->string('nivel')->nullable();
        $table->string('agenciaPpalNivel1')->nullable();
        $table->string('created_by')->nullable();
        $table->string('updated_by')->nullable();
        $table->uuid('registration_token')->nullable();
        $table->uuid('agency_registration_token')->nullable();
        $table->string('nameSecundario')->nullable();
        $table->string('emailSecundario')->nullable();
        $table->string('phoneSecundario')->nullable();
        $table->timestamps();
    });

    Schema::create('travel_agents', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('travel_agency_id');
        $table->string('name')->nullable();
        $table->string('cargo')->nullable();
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->string('created_by')->nullable();
        $table->string('updated_by')->nullable();
        $table->timestamps();
    });
});

it('crea una agencia de viaje al convertir un prospecto aliado activo', function (): void {
    $suffix = uniqid('conv', true);

    $prospect = ProspectAgent::query()->create([
        'name' => 'Viajes Andes '.$suffix,
        'type' => 'agencia-viajes',
        'phone_1' => '04121234567',
        'phone_2' => '02121234567',
        'email' => 'Andes-'.$suffix.'@Example.test',
        'address' => 'Av. Principal 123',
        'instagram' => '@andesviajes',
        'status' => ProspectAgentLabels::STATUS_EN_PROCESO_NEGOCIACION,
        'created_by' => 'Ana Pest',
        'country_id' => 189,
    ]);

    ProspectAgentContact::query()->create([
        'prospect_agent_id' => $prospect->getKey(),
        'name' => 'Carlos Mendoza',
        'position' => 'Gerente comercial',
        'email' => 'Carlos@Example.test',
        'phone' => '04149876543',
        'sort_order' => 0,
    ]);

    expect(ProspectAgentToTravelAgencyConverter::convertIfEligible($prospect->fresh()))->toBeNull();

    $prospect->update(['status' => ProspectAgentLabels::STATUS_ALIADO_ACTIVO]);

    $agency = ProspectAgentToTravelAgencyConverter::existingAgency($prospect->fresh())
        ?? ProspectAgentToTravelAgencyConverter::convertIfEligible($prospect->fresh());

    expect($agency)->toBeInstanceOf(TravelAgency::class)
        ->and($agency->name)->toBe(mb_strtoupper('Viajes Andes '.$suffix))
        ->and($agency->email)->toBe(mb_strtolower('Andes-'.$suffix.'@Example.test'))
        ->and($agency->phone)->toBe('04121234567')
        ->and($agency->phoneAdditional)->toBe('02121234567')
        ->and($agency->address)->toBe('Av. Principal 123')
        ->and($agency->userInstagram)->toBe('andesviajes')
        ->and($agency->classification)->toBe('AGENCIA DE VIAJES')
        ->and($agency->status)->toBe('Activo')
        ->and($agency->nivel)->toBe('2')
        ->and($agency->agenciaPpalNivel1)->toBe('TDEV')
        ->and($agency->prospect_agent_id)->toBe($prospect->getKey())
        ->and($agency->representante)->toBe('CARLOS MENDOZA')
        ->and($agency->registration_token)->not->toBeEmpty()
        ->and($prospect->fresh()->travel_agency_id)->toBe($agency->getKey());

    expect(
        TravelAgent::query()
            ->where('travel_agency_id', $agency->getKey())
            ->where('name', 'CARLOS MENDOZA')
            ->exists()
    )->toBeTrue();
});

it('no duplica la agencia si el prospecto ya fue convertido', function (): void {
    $suffix = uniqid('idem', true);

    $prospect = ProspectAgent::query()->create([
        'name' => 'Idem Viajes '.$suffix,
        'type' => 'mayorista-viajes',
        'phone_1' => '04121112233',
        'email' => 'idem-'.$suffix.'@example.test',
        'status' => ProspectAgentLabels::STATUS_ALIADO_ACTIVO,
        'created_by' => 'Ana Pest',
    ]);

    $first = ProspectAgentToTravelAgencyConverter::convertIfEligible($prospect->fresh());
    $second = ProspectAgentToTravelAgencyConverter::convertIfEligible($prospect->fresh());

    expect($first)->toBeInstanceOf(TravelAgency::class)
        ->and($second)->toBeInstanceOf(TravelAgency::class)
        ->and($second->getKey())->toBe($first->getKey())
        ->and($first->classification)->toBe('MAYORISTA')
        ->and(
            TravelAgency::query()->where('prospect_agent_id', $prospect->getKey())->count()
        )->toBe(1);
});

it('mapea el tipo del prospecto a la clasificación de la agencia', function (): void {
    expect(ProspectAgentToTravelAgencyConverter::classificationFromType('agencia-viajes'))->toBe('AGENCIA DE VIAJES')
        ->and(ProspectAgentToTravelAgencyConverter::classificationFromType('mayorista-viajes'))->toBe('MAYORISTA')
        ->and(ProspectAgentToTravelAgencyConverter::classificationFromType('freelance'))->toBe('FREELANCE')
        ->and(ProspectAgentToTravelAgencyConverter::classificationFromType('agente-corretaje'))->toBe('AGENTE DE CORRETAJE')
        ->and(ProspectAgentToTravelAgencyConverter::classificationFromType('cliente-corporativo'))->toBe('AGENCIA DE VIAJES');
});

it('registra el observer y notifica en la edición del prospecto', function (): void {
    $provider = (string) file_get_contents(dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php');
    $edit = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/ProspectAgents/Pages/EditProspectAgent.php');
    $observer = (string) file_get_contents(dirname(__DIR__, 2).'/app/Observers/ProspectAgentTravelAgencyObserver.php');

    expect($provider)
        ->toContain('ProspectAgentTravelAgencyObserver')
        ->toContain('ProspectAgent::observe(ProspectAgentTravelAgencyObserver::class)');

    expect($edit)
        ->toContain('function afterSave')
        ->toContain('ProspectAgentToTravelAgencyConverter')
        ->toContain('TravelAgencyResource::getUrl')
        ->toContain('Aliado activo en Agencias de viaje');

    expect($observer)
        ->toContain('function created')
        ->toContain('function updated')
        ->toContain("wasChanged('status')")
        ->toContain('ProspectAgentToTravelAgencyConverter::convertIfEligible');

    expect(class_exists(ProspectAgentTravelAgencyObserver::class))->toBeTrue()
        ->and(class_exists(EditProspectAgent::class))->toBeTrue()
        ->and(class_exists(AppServiceProvider::class))->toBeTrue();
});
