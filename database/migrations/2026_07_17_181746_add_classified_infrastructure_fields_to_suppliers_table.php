<?php

declare(strict_types=1);

use App\Support\Operations\SupplierInfrastructureCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            foreach (SupplierInfrastructureCatalog::newBooleanColumns() as $column) {
                if (! Schema::hasColumn('suppliers', $column)) {
                    $table->boolean($column)->nullable();
                }
            }

            foreach (SupplierInfrastructureCatalog::newDescriptionColumns() as $column) {
                if (! Schema::hasColumn('suppliers', $column)) {
                    $table->text($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $columns = array_merge(
                SupplierInfrastructureCatalog::newBooleanColumns(),
                SupplierInfrastructureCatalog::newDescriptionColumns(),
            );

            $existing = array_values(array_filter(
                $columns,
                static fn (string $column): bool => Schema::hasColumn('suppliers', $column),
            ));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
