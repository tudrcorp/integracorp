<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decisiones sobre las cuotas pendientes de afiliaciones dadas de baja (excluidas,
 * inactivas o anuladas): anulación automática al darlas de baja, anulación o «mantener
 * como deuda» del analista, y su reversión. Nada se borra: revertir cierra la fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('collection_dropout_reviews')) {
            return;
        }

        Schema::create('collection_dropout_reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('collection_id');
            $table->string('affiliation_code', 60)->nullable();
            $table->string('affiliation_type', 20)->nullable();
            $table->string('affiliation_status', 40)->nullable();
            $table->string('decision', 30);
            $table->string('status_before', 30)->nullable();
            $table->string('status_after', 30)->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->unsignedBigInteger('adjustment_id')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('decided_by_id')->nullable();
            $table->string('decided_by_name')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->unsignedBigInteger('reverted_by_id')->nullable();
            $table->string('reverted_by_name')->nullable();
            $table->text('revert_note')->nullable();
            $table->timestamps();

            $table->index(['collection_id', 'reverted_at'], 'cdr_collection_active_index');
            $table->index(['affiliation_code', 'decision', 'reverted_at'], 'cdr_code_decision_index');
            $table->index('batch_uuid', 'cdr_batch_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_dropout_reviews');
    }
};
