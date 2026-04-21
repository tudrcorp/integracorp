<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Texto opcional por servicio (lista de infraestructura / convenio).
     *
     * @return list<string>
     */
    private function listServiceDescriptionColumns(): array
    {
        return [
            'descripcion_urgen_care',
            'descripcion_consulta_aps',
            'descripcion_amd',
            'descripcion_laboratorio_centro',
            'descripcion_laboratorio_domicilio',
            'descripcion_rx_centro',
            'descripcion_rx_domicilio',
            'descripcion_eco_abdominal_centro',
            'descripcion_eco_abdominal_domicilio',
            'descripcion_electrocardiograma_domicilio',
            'descripcion_encologogia',
            'descripcion_uci_uten',
            'descripcion_neonatal',
            'descripcion_ambulancias',
            'descripcion_odontologia',
            'descripcion_oftalmologia',
            'descripcion_otras_unidades_especiales',
        ];
    }

    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            foreach ($this->listServiceDescriptionColumns() as $column) {
                if (! Schema::hasColumn('suppliers', $column)) {
                    $table->text($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $toDrop = array_values(array_filter(
                $this->listServiceDescriptionColumns(),
                fn (string $column): bool => Schema::hasColumn('suppliers', $column)
            ));

            if ($toDrop !== []) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
