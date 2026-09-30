<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La asignación de caso con «Nueva Ubicación» guarda si la dirección tiene
 * estacionamiento para ambulancia y el parentesco de quien atiende, pero la
 * tabla nunca tuvo esas columnas y el teléfono alternativo, opcional en el
 * formulario, era obligatorio aquí. Resultado: toda asignación con ubicación
 * nueva fallaba.
 */
return new class extends Migration
{
    private const TABLE = 'another_addresses';

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table): void {
            if (! Schema::hasColumn(self::TABLE, 'ambulanceParking')) {
                $table->boolean('ambulanceParking')->nullable()->after('phone_2');
            }

            if (! Schema::hasColumn(self::TABLE, 'relationship')) {
                $table->string('relationship', 50)->nullable()->after('ambulanceParking');
            }
        });

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->string('phone_2')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table(self::TABLE)->whereNull('phone_2')->update(['phone_2' => '']);

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->string('phone_2')->nullable(false)->change();
        });

        Schema::table(self::TABLE, function (Blueprint $table): void {
            foreach (['relationship', 'ambulanceParking'] as $column) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
