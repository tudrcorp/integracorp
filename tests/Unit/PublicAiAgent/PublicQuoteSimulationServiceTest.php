<?php

declare(strict_types=1);

use App\Services\PublicAiAgent\PublicQuoteSimulationService;
use App\Support\AffiliationAffiliateFeeCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    ensureSqliteInMemoryDatabaseOrSkip();

    Schema::dropIfExists('fees');
    Schema::dropIfExists('age_ranges');
    Schema::dropIfExists('coverages');
    Schema::dropIfExists('plans');

    Schema::create('plans', function (Blueprint $table): void {
        $table->id();
        $table->string('description')->nullable();
        $table->string('type')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });

    Schema::create('coverages', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('plan_id')->nullable();
        $table->decimal('price', 12, 2)->default(0);
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
        $table->unsignedBigInteger('age_range_id')->nullable();
        $table->unsignedBigInteger('coverage_id')->nullable();
        $table->decimal('price', 12, 2)->default(0);
        $table->string('range')->nullable();
        $table->timestamps();
    });

    DB::table('plans')->insert([
        ['id' => 1, 'description' => 'Plan Inicial', 'type' => 'BASICO', 'status' => 'ACTIVO'],
        ['id' => 2, 'description' => 'Plan Ideal', 'type' => 'BASICO', 'status' => 'ACTIVO'],
    ]);

    DB::table('coverages')->insert([
        ['id' => 22, 'plan_id' => 2, 'price' => 5000],
    ]);

    DB::table('age_ranges')->insert([
        ['id' => 1, 'plan_id' => 1, 'coverage_id' => null, 'range' => '0 a 120', 'age_init' => 0, 'age_end' => 120],
        ['id' => 2, 'plan_id' => 2, 'coverage_id' => 22, 'range' => '18 a 59', 'age_init' => 18, 'age_end' => 59],
    ]);

    DB::table('fees')->insert([
        ['id' => 101, 'age_range_id' => 1, 'coverage_id' => null, 'price' => 100, 'range' => '0 a 120'],
        ['id' => 102, 'age_range_id' => 2, 'coverage_id' => 22, 'price' => 240, 'range' => '18 a 59'],
    ]);
});

it('simula cotizacion con desglose por frecuencias', function (): void {
    $service = new PublicQuoteSimulationService(new AffiliationAffiliateFeeCalculator);

    $result = $service->simulate([
        'plan_id' => 2,
        'coverage_id' => 22,
        'members' => [
            ['age' => 45, 'quantity' => 2],
        ],
    ]);

    expect($result['totals']['annual'])->toBe(480.0)
        ->and($result['totals']['semiannual'])->toBe(240.0)
        ->and($result['totals']['quarterly'])->toBe(120.0)
        ->and($result['totals']['monthly'])->toBe(40.0);
});

it('requiere cobertura para planes distintos al inicial', function (): void {
    $service = new PublicQuoteSimulationService(new AffiliationAffiliateFeeCalculator);

    expect(fn () => $service->simulate([
        'plan_id' => 2,
        'members' => [
            ['age' => 45, 'quantity' => 1],
        ],
    ]))->toThrow(ValidationException::class);
});
