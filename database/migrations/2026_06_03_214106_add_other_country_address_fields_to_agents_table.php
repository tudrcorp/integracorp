<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table): void {
            if (! Schema::hasColumn('agents', 'country_other_country')) {
                $table->unsignedInteger('country_other_country')->nullable()->after('country_id');
            }

            if (! Schema::hasColumn('agents', 'state_other_country')) {
                $table->string('state_other_country')->nullable()->after('country_other_country');
            }

            if (! Schema::hasColumn('agents', 'city_other_country')) {
                $table->string('city_other_country')->nullable()->after('state_other_country');
            }

            if (! Schema::hasColumn('agents', 'postal_code_other_country')) {
                $table->string('postal_code_other_country')->nullable()->after('city_other_country');
            }

            if (! Schema::hasColumn('agents', 'address_other_country')) {
                $table->string('address_other_country')->nullable()->after('postal_code_other_country');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table): void {
            $columns = [
                'country_other_country',
                'state_other_country',
                'city_other_country',
                'postal_code_other_country',
                'address_other_country',
            ];

            $existingColumns = array_values(array_filter(
                $columns,
                fn (string $column): bool => Schema::hasColumn('agents', $column)
            ));

            if ($existingColumns !== []) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
