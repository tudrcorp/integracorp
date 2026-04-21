<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Booleanos del formulario/ficha que no existían en la migración inicial de suppliers.
     * (El resto de servicios ya está en create_suppliers_table.)
     *
     * @return list<string>
     */
    private function newBooleanColumns(): array
    {
        return [
            'uci_pediatrica',
            'uci_adulto',
            'estacionamiento_propio',
            'ascensor',
            'robotica',
            'oncologia',
        ];
    }

    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            foreach ($this->newBooleanColumns() as $column) {
                if (! Schema::hasColumn('suppliers', $column)) {
                    $table->boolean($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $toDrop = array_values(array_filter(
                $this->newBooleanColumns(),
                fn (string $column): bool => Schema::hasColumn('suppliers', $column)
            ));

            if ($toDrop !== []) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
