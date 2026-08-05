<?php

declare(strict_types=1);

use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Operations\AssignCoordinationServiceToSupplier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

function createAssignCoordinationTables(): void
{
    Schema::dropIfExists('observation_cases');
    Schema::dropIfExists('operation_coordination_services');
    Schema::dropIfExists('suppliers');

    Schema::create('suppliers', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('razon_social')->nullable();
        $table->timestamps();
    });

    Schema::create('operation_coordination_services', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('supplier_id')->nullable();
        $table->unsignedBigInteger('telemedicine_case_id')->nullable();
        $table->boolean('assigned_to_supplier_by_tdg')->default(false);
        $table->timestamp('assigned_to_supplier_by_tdg_at')->nullable();
        $table->string('assigned_to_supplier_by_tdg_by')->nullable();
        $table->text('observations')->nullable();
        $table->string('updated_by')->nullable();
        $table->string('managed_by')->nullable();
        $table->timestamps();
    });

    Schema::create('observation_cases', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('telemedicine_case_id')->nullable();
        $table->text('description')->nullable();
        $table->string('created_by')->nullable();
        $table->timestamps();
    });
}

it('asigna la coordinación completa al proveedor con bitácora cuando un analista TDG lo solicita', function (): void {
    ensureSqliteInMemoryDatabaseOrSkip();
    createAssignCoordinationTables();

    $tdgUser = new User;
    $tdgUser->forceFill([
        'id' => 501,
        'name' => 'Analista TDG',
        'email' => 'tdg-assign@example.com',
        'supplier_id' => null,
        'is_proveedor_amd' => false,
        'departament' => ['OPERACIONES'],
        'status' => 'ACTIVO',
    ]);

    $supplier = Supplier::query()->create([
        'name' => 'Proveedor Asignado',
        'razon_social' => 'Proveedor Asignado CA',
    ]);

    $coordination = OperationCoordinationService::query()->create([
        'supplier_id' => null,
        'telemedicine_case_id' => 77,
        'assigned_to_supplier_by_tdg' => false,
        'managed_by' => 'ATENMEDI',
        'observations' => null,
    ]);

    Auth::login($tdgUser);

    $result = AssignCoordinationServiceToSupplier::execute(
        $coordination,
        (int) $supplier->id,
        'Delegación completa de estudios y especialistas al proveedor.',
        $tdgUser,
    );

    $fresh = $result['coordination']->fresh();

    expect($fresh)->not->toBeNull()
        ->and((int) $fresh->supplier_id)->toBe((int) $supplier->id)
        ->and((bool) $fresh->assigned_to_supplier_by_tdg)->toBeTrue()
        ->and($fresh->assigned_to_supplier_by_tdg_by)->toBe('Analista TDG')
        ->and($fresh->assigned_to_supplier_by_tdg_at)->not->toBeNull()
        ->and((string) $fresh->observations)->toContain(AssignCoordinationServiceToSupplier::OBSERVATION_PREFIX)
        ->and((string) $fresh->observations)->toContain('Proveedor Asignado');

    expect(ObservationCase::query()->where('telemedicine_case_id', 77)->count())->toBe(1)
        ->and((string) ObservationCase::query()->where('telemedicine_case_id', 77)->value('description'))
        ->toContain('Delegación completa');

    Auth::logout();
});
