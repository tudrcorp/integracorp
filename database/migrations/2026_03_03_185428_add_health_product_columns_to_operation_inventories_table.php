<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agrega columnas para medicamentos/productos de salud sin modificar datos existentes.
     */
    public function up(): void
    {
        Schema::table('operation_inventories', function (Blueprint $table) {
            if (! Schema::hasColumn('operation_inventories', 'active_principle')) {
                $table->string('active_principle')->nullable()->after('description')->comment('Principio activo (medicamentos)');
            }
            if (! Schema::hasColumn('operation_inventories', 'laboratory')) {
                $table->string('laboratory')->nullable()->after('concentration')->comment('Laboratorio fabricante');
            }
            if (! Schema::hasColumn('operation_inventories', 'min_stock')) {
                $table->unsignedInteger('min_stock')->default(0)->after('sanitary_registration')->comment('Stock mínimo para alertas');
            }
            if (! Schema::hasColumn('operation_inventories', 'category')) {
                $table->string('category')->nullable()->after('is_active')->comment('Categoría: medicamento, insumo, equipo');
            }
            if (! Schema::hasColumn('operation_inventories', 'barcode')) {
                $table->string('barcode')->nullable()->after('category');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operation_inventories', function (Blueprint $table) {
            $columns = [
                'description', 'active_principle', 'pharmaceutical_form', 'concentration',
                'laboratory', 'sanitary_registration', 'min_stock', 'location',
                'is_active', 'category', 'barcode',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('operation_inventories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
