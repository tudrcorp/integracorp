<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospect_agents', function (Blueprint $table): void {
            if (! Schema::hasColumn('prospect_agents', 'address')) {
                $table->text('address')->nullable()->after('city_id');
            }

            if (! Schema::hasColumn('prospect_agents', 'website')) {
                $table->string('website', 255)->nullable()->after('address');
            }

            if (! Schema::hasColumn('prospect_agents', 'social_networks')) {
                $table->text('social_networks')->nullable()->after('website');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prospect_agents', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                Schema::hasColumn('prospect_agents', 'address') ? 'address' : null,
                Schema::hasColumn('prospect_agents', 'website') ? 'website' : null,
                Schema::hasColumn('prospect_agents', 'social_networks') ? 'social_networks' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
