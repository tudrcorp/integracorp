<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traza de la eliminación lógica de un plan de capitado: quién lo eliminó y por qué.
 *
 * Idempotente: cada columna se agrega solo si no existe (el esquema local se construyó
 * desde un dump y `migrate` a secas no corre).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('capitation_plans')) {
            return;
        }

        Schema::table('capitation_plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('capitation_plans', 'deletion_reason')) {
                $table->text('deletion_reason')->nullable()->after('updated_by_name');
            }

            if (! Schema::hasColumn('capitation_plans', 'deleted_by_user_id')) {
                $table->unsignedBigInteger('deleted_by_user_id')->nullable()->after('deletion_reason');
            }

            if (! Schema::hasColumn('capitation_plans', 'deleted_by_name')) {
                $table->string('deleted_by_name')->nullable()->after('deleted_by_user_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('capitation_plans')) {
            return;
        }

        Schema::table('capitation_plans', function (Blueprint $table): void {
            foreach (['deleted_by_name', 'deleted_by_user_id', 'deletion_reason'] as $column) {
                if (Schema::hasColumn('capitation_plans', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
