<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de observaciones de cobranza («Cobranza Por Mes»).
 *
 * Se asocia a la afiliación por su código, no a la cuota: la fila de la tabla es
 * la próxima cuota pendiente y cambia cada vez que se paga una, pero la bitácora
 * debe seguir visible. La cuota, su vencimiento y su monto se copian como
 * referencia de a qué se refería cada nota.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('collection_observations')) {
            return;
        }

        Schema::create('collection_observations', function (Blueprint $table) {
            $table->id();
            $table->string('affiliation_code');
            $table->string('affiliation_type', 20);
            $table->foreignId('collection_id')->nullable()->constrained('collections')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->text('observation');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->index(['affiliation_code', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_observations');
    }
};
