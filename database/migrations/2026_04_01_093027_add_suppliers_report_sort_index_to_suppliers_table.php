<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índice compuesto para acelerar el reporte PDF de proveedores (ORDER BY estado, ciudad, nombre vía joins).
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->index(
                ['state_id', 'city_id', 'name'],
                'suppliers_report_sort_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropIndex('suppliers_report_sort_idx');
        });
    }
};
