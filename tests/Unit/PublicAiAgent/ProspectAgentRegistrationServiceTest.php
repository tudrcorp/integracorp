<?php

declare(strict_types=1);

use App\Models\ProspectAgent;
use App\Models\ProspectAgentObservation;
use App\Services\PublicAiAgent\ProspectAgentRegistrationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('prospect_agent_observations');
    Schema::dropIfExists('prospect_agents');
    Schema::dropIfExists('cities');
    Schema::dropIfExists('states');
    Schema::dropIfExists('countries');

    Schema::create('countries', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    Schema::create('states', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('country_id');
        $table->string('definition')->nullable();
    });

    Schema::create('cities', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('country_id');
        $table->unsignedBigInteger('state_id');
        $table->string('definition')->nullable();
    });

    Schema::create('prospect_agents', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('type');
        $table->string('phone_1');
        $table->string('phone_2');
        $table->string('email')->unique();
        $table->string('state_id');
        $table->string('city_id');
        $table->string('country_id');
        $table->string('status');
        $table->string('created_by');
        $table->string('updated_by');
        $table->string('reference_by');
        $table->text('initial_observ')->nullable();
        $table->text('instagram')->nullable();
        $table->string('classification', 512)->nullable();
        $table->timestamps();
    });

    Schema::create('prospect_agent_observations', function (Blueprint $table): void {
        $table->id();
        $table->string('prospect_agent_id');
        $table->string('observation');
        $table->string('created_by');
        $table->timestamps();
    });

    DB::table('countries')->insert(['id' => 1, 'name' => 'Venezuela']);
    DB::table('states')->insert(['id' => 10, 'country_id' => 1, 'definition' => 'Miranda']);
    DB::table('cities')->insert(['id' => 100, 'country_id' => 1, 'state_id' => 10, 'definition' => 'Caracas']);
});

it('crea el preregistro y guarda una observación inicial', function (): void {
    $service = new ProspectAgentRegistrationService;

    $result = $service->create([
        'name' => 'Mariana Perez',
        'email' => 'mariana@example.com',
        'phone_1' => '04121234567',
        'phone_2' => '',
        'country_id' => 1,
        'state_id' => 10,
        'city_id' => 100,
        'type' => 'agente-corretaje',
        'status' => 'captación',
        'reference_by' => 'whatsapp-comercial',
        'conversation_summary' => 'Cliente solicita iniciar preregistro como agente.',
    ]);

    expect($result['prospect_agent_id'])->toBeInt()
        ->and($result['message'])->toBe('Preregistro creado exitosamente.');

    expect(ProspectAgent::query()->count())->toBe(1)
        ->and(ProspectAgentObservation::query()->count())->toBe(1);
});

it('valida el formato del teléfono principal', function (): void {
    $service = new ProspectAgentRegistrationService;

    expect(fn () => $service->create([
        'name' => 'Pedro Ruiz',
        'email' => 'pedro@example.com',
        'phone_1' => '+58 412-123-45-67',
        'country_id' => 1,
        'state_id' => 10,
        'city_id' => 100,
        'type' => 'agente-corretaje',
    ]))->toThrow(ValidationException::class);
});
