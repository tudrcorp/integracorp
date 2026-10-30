<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guardias de los médicos de TDG por sede.
 *
 * - `medical_guard_sites`: catálogo de sedes; una sede nueva no pide programar.
 * - `medical_guards`: una fila por médico y guardia, con fecha y hora reales.
 *   `guard_date` es el día en que empieza (una guardia que cruza la medianoche
 *   cuenta para ese día) y anular es lógico, para no perder lo que ya se midió.
 *
 * Idempotente: se puede aplicar con `--path` aunque parte ya exista.
 */
return new class extends Migration
{
    private const SITES = [
        ['name' => 'Diagno Center 3 de Febrero', 'sort_order' => 1],
        ['name' => 'Diagnomóvil Mene Grande', 'sort_order' => 2],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('medical_guard_sites')) {
            Schema::create('medical_guard_sites', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120)->unique();
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        foreach (self::SITES as $site) {
            if (! DB::table('medical_guard_sites')->where('name', $site['name'])->exists()) {
                DB::table('medical_guard_sites')->insert([...$site, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        if (! Schema::hasTable('medical_guards')) {
            Schema::create('medical_guards', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('medical_guard_site_id');
                $table->unsignedBigInteger('telemedicine_doctor_id');
                $table->date('guard_date');
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->string('notes', 255)->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->unsignedBigInteger('deleted_by_user_id')->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->index(['guard_date', 'medical_guard_site_id'], 'mg_date_site_idx');
                $table->index(['telemedicine_doctor_id', 'starts_at'], 'mg_doctor_start_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_guards');
        Schema::dropIfExists('medical_guard_sites');
    }
};
