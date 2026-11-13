<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Libros fiscales de Administración: Libro de Ventas y Libro de Compras.
 *
 * - `fiscal_book_entries`: una fila por documento (factura, nota de débito o de crédito).
 *   Montos en bolívares con DECIMAL(18,2), con signo: las notas de crédito restan.
 * - `fiscal_book_periods`: estado de cada libro en cada mes (abierto o cerrado).
 * - `fiscal_book_period_events`: bitácora de cierres y reaperturas con su razón.
 *
 * Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fiscal_book_entries')) {
            Schema::create('fiscal_book_entries', function (Blueprint $table): void {
                $table->id();
                $table->string('book', 10);
                $table->date('period');
                $table->unsignedInteger('position')->nullable();
                $table->date('document_date');
                $table->string('document_type', 20)->default('FACTURA');
                $table->string('document_number', 40);
                $table->string('document_number_key', 40);
                $table->string('control_number', 40)->nullable();
                $table->string('series', 20)->nullable();
                $table->string('affected_document_number', 40)->nullable();
                $table->string('counterparty_name', 255);
                $table->string('counterparty_rif', 30)->nullable();
                $table->string('taxpayer_type', 2)->default('C');
                $table->string('status', 10)->default('VIGENTE');
                $table->decimal('total_amount', 18, 2)->default(0);
                $table->decimal('exempt_amount', 18, 2)->default(0);
                $table->decimal('export_amount', 18, 2)->default(0);
                $table->decimal('taxable_base', 18, 2)->default(0);
                $table->decimal('tax_rate', 5, 2)->default(16);
                $table->decimal('tax_amount', 18, 2)->default(0);
                $table->date('withholding_date')->nullable();
                $table->string('withholding_number', 40)->nullable();
                $table->decimal('withholding_amount', 18, 2)->default(0);
                $table->string('source', 10)->default('MANUAL');
                $table->unsignedBigInteger('source_id')->nullable();
                $table->decimal('source_amount_usd', 18, 4)->nullable();
                $table->decimal('exchange_rate', 20, 8)->nullable();
                $table->boolean('manually_adjusted')->default(false);
                $table->string('annulment_reason', 500)->nullable();
                $table->string('notes', 500)->nullable();
                $table->unsignedBigInteger('created_by_id')->nullable();
                $table->string('created_by_name', 150)->nullable();
                $table->unsignedBigInteger('updated_by_id')->nullable();
                $table->string('updated_by_name', 150)->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unsignedBigInteger('deleted_by_id')->nullable();
                $table->string('deleted_by_name', 150)->nullable();

                $table->index(['book', 'period', 'deleted_at'], 'fbe_book_period_idx');
                $table->index(['book', 'document_number_key'], 'fbe_book_number_idx');
                $table->unique(['book', 'source', 'source_id'], 'fbe_book_source_unique');
            });
        }

        if (! Schema::hasTable('fiscal_book_periods')) {
            Schema::create('fiscal_book_periods', function (Blueprint $table): void {
                $table->id();
                $table->string('book', 10);
                $table->date('period');
                $table->string('status', 10)->default('ABIERTO');
                $table->json('totals')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->unsignedBigInteger('closed_by_id')->nullable();
                $table->string('closed_by_name', 150)->nullable();
                $table->timestamp('reopened_at')->nullable();
                $table->unsignedBigInteger('reopened_by_id')->nullable();
                $table->string('reopened_by_name', 150)->nullable();
                $table->string('reopen_reason', 1000)->nullable();
                $table->timestamps();

                $table->unique(['book', 'period'], 'fbp_book_period_unique');
            });
        }

        if (! Schema::hasTable('fiscal_book_period_events')) {
            Schema::create('fiscal_book_period_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('fiscal_book_period_id');
                $table->string('action', 10);
                $table->string('reason', 1000)->nullable();
                $table->json('totals')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_name', 150)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index('fiscal_book_period_id', 'fbpe_period_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_book_period_events');
        Schema::dropIfExists('fiscal_book_periods');
        Schema::dropIfExists('fiscal_book_entries');
    }
};
