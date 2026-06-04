<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliation_renovation_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('affiliation_id')->index();
            $table->unsignedBigInteger('affiliate_id')->nullable()->index();
            $table->unsignedBigInteger('source_renovation_id')->nullable();
            $table->timestamp('accepted_at');
            $table->string('accepted_by');
            $table->string('previous_effective_date')->nullable();
            $table->string('new_effective_date');
            $table->date('date_renewal');
            $table->integer('remaining_days_at_accept')->nullable();
            $table->string('status_at_accept');
            $table->string('code_affiliation');
            $table->string('agent_id');
            $table->string('code_agency');
            $table->string('owner_code')->nullable();
            $table->string('owner_agent')->nullable();
            $table->unsignedInteger('plan_id');
            $table->unsignedInteger('coverage_id')->nullable();
            $table->unsignedInteger('age_range_id');
            $table->date('birth_date')->nullable();
            $table->unsignedSmallInteger('age')->nullable();
            $table->decimal('fee', 10, 2);
            $table->decimal('subtotal_anual', 10, 2);
            $table->decimal('subtotal_quarterly', 10, 2);
            $table->decimal('subtotal_biannual', 10, 2);
            $table->decimal('subtotal_monthly', 10, 2);
            $table->unsignedInteger('total_persons');
            $table->string('payment_frequency');
            $table->boolean('is_negotiation_candidate')->default(false);
            $table->text('negotiation_notes')->nullable();
            $table->unsignedInteger('previous_plan_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliation_renovation_histories');
    }
};
