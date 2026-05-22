<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renovations', function (Blueprint $table) {
            $table->boolean('is_negotiation_candidate')
                ->default(false)
                ->after('payment_frequency');
            $table->text('negotiation_notes')->nullable()->after('is_negotiation_candidate');
            $table->unsignedInteger('previous_plan_id')->nullable()->after('negotiation_notes');
        });
    }

    public function down(): void
    {
        Schema::table('renovations', function (Blueprint $table) {
            $table->dropColumn(['is_negotiation_candidate', 'negotiation_notes', 'previous_plan_id']);
        });
    }
};
