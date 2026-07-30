<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renovation_corporates', function (Blueprint $table) {
            $table->integer('remaining_days')->default(0)->after('date_renewal');
            $table->integer('plan_id')->default(1)->after('owner_agent');
            $table->integer('coverage_id')->nullable()->after('plan_id');
            $table->integer('age_range_id')->default(1)->after('coverage_id');
            $table->date('birth_date')->nullable()->after('age_range_id');
            $table->unsignedSmallInteger('age')->nullable()->after('birth_date');
            $table->decimal('fee', 8, 2)->default(0)->after('age');
            $table->decimal('subtotal_anual', 8, 2)->default(0)->after('fee');
            $table->decimal('subtotal_quarterly', 8, 2)->default(0)->after('subtotal_anual');
            $table->decimal('subtotal_biannual', 8, 2)->default(0)->after('subtotal_quarterly');
            $table->decimal('subtotal_monthly', 8, 2)->default(0)->after('subtotal_biannual');
            $table->integer('total_persons')->default(0)->after('subtotal_monthly');
            $table->string('payment_frequency')->default('ANUAL')->after('total_persons');
            $table->boolean('is_negotiation_candidate')->default(false)->after('payment_frequency');
            $table->text('negotiation_notes')->nullable()->after('is_negotiation_candidate');
            $table->unsignedInteger('previous_plan_id')->nullable()->after('negotiation_notes');
        });
    }

    public function down(): void
    {
        Schema::table('renovation_corporates', function (Blueprint $table) {
            $table->dropColumn([
                'remaining_days',
                'plan_id',
                'coverage_id',
                'age_range_id',
                'birth_date',
                'age',
                'fee',
                'subtotal_anual',
                'subtotal_quarterly',
                'subtotal_biannual',
                'subtotal_monthly',
                'total_persons',
                'payment_frequency',
                'is_negotiation_candidate',
                'negotiation_notes',
                'previous_plan_id',
            ]);
        });
    }
};
