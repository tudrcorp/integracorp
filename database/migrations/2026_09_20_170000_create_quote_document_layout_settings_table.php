<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composición del PDF de la Propuesta Económica.
 *
 * Una fila por ámbito (`individual` / `corporate`): cuántas hojas tiene el
 * documento y en qué página van los cálculos que devuelve el microservicio.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quote_document_layout_settings')) {
            return;
        }

        Schema::create('quote_document_layout_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 20)->unique();
            $table->unsignedTinyInteger('total_pages')->default(4);
            $table->unsignedTinyInteger('calculations_page')->default(3);
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_document_layout_settings');
    }
};
