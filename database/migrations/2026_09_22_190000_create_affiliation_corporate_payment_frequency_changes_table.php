<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('affiliation_corporate_payment_frequency_changes')) {
            return;
        }

        Schema::create('affiliation_corporate_payment_frequency_changes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('batch_uuid')->index();
            $table->unsignedBigInteger('affiliation_corporate_id');
            $table->string('affiliation_code')->nullable();
            $table->string('affiliation_name')->nullable();
            $table->string('previous_frequency', 30)->nullable();
            $table->string('new_frequency', 30);
            $table->decimal('fee_anual', 12, 2)->default(0);
            $table->decimal('previous_total_amount', 12, 2)->default(0);
            $table->decimal('new_total_amount', 12, 2)->default(0);
            $table->decimal('pending_balance', 12, 2)->default(0);
            $table->json('cancelled_collections')->nullable();
            $table->json('created_collections')->nullable();
            $table->json('snapshot')->nullable();
            $table->string('status', 20)->default('APLICADO');
            $table->unsignedBigInteger('performed_by_id')->nullable();
            $table->string('performed_by_name')->nullable();
            $table->string('performed_from', 40)->nullable();
            $table->string('ip', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('notification_log')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->unsignedBigInteger('validated_by_id')->nullable();
            $table->string('validated_by_name')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('reversed_by_id')->nullable();
            $table->string('reversed_by_name')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->index(['affiliation_corporate_id', 'status'], 'aff_corp_freq_changes_affiliation_status_idx');
            $table->index(['status', 'created_at'], 'aff_corp_freq_changes_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliation_corporate_payment_frequency_changes');
    }
};
