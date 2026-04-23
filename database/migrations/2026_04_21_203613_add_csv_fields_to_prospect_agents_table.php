<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospect_agents', function (Blueprint $table): void {
            if (! Schema::hasColumn('prospect_agents', 'initial_observ')) {
                $table->text('initial_observ')->nullable()->after('reference_by');
            }
            if (! Schema::hasColumn('prospect_agents', 'instagram')) {
                $table->text('instagram')->nullable()->after('initial_observ');
            }
            if (! Schema::hasColumn('prospect_agents', 'classification')) {
                $table->string('classification', 512)->nullable()->after('instagram');
            }
        });
    }

    public function down(): void
    {
        Schema::table('prospect_agents', function (Blueprint $table): void {
            $table->dropColumn(['initial_observ', 'instagram', 'classification']);
        });
    }
};
