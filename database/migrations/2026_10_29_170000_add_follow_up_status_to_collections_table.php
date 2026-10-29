<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de gestión de cobranza por cuota («Cobranza Por Mes»): SEGUIMIENTO, AVISO ENVIADO,
 * AVISO REMITIDO o AVISO EN REVISION.
 *
 * - `collections.follow_up_status`: el estado actual, en la misma fila para leerlo sin consultas
 *   extra en la tabla.
 * - `collection_follow_up_events`: cada marcado y desmarcado, con quién, cuándo e IP.
 *
 * Idempotente: se aplica con --path y puede correrse dos veces sin daño.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('collections') && ! Schema::hasColumn('collections', 'follow_up_status')) {
            Schema::table('collections', function (Blueprint $table): void {
                $table->string('follow_up_status', 30)->nullable()->after('status');
                $table->index('follow_up_status', 'collections_follow_up_status_idx');
            });
        }

        if (! Schema::hasTable('collection_follow_up_events')) {
            Schema::create('collection_follow_up_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('collection_id');
                $table->string('affiliation_code')->nullable();
                $table->string('collection_invoice_number')->nullable();
                $table->string('previous_status', 30)->nullable();
                $table->string('new_status', 30)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_name')->nullable();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 1000)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['collection_id', 'id'], 'cfue_collection_idx');
                $table->index('affiliation_code', 'cfue_affiliation_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_follow_up_events');

        if (Schema::hasTable('collections') && Schema::hasColumn('collections', 'follow_up_status')) {
            Schema::table('collections', function (Blueprint $table): void {
                $table->dropIndex('collections_follow_up_status_idx');
                $table->dropColumn('follow_up_status');
            });
        }
    }
};
