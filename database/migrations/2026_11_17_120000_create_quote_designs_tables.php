<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diseño de cotización por plan: colores, tipografía, logo y textos de la página de cálculos,
 * más las imágenes de las páginas fijas (antes y después de los cálculos).
 *
 * - `quote_designs`: el diseño editable.
 * - `quote_design_plan`: a qué planes aplica; un plan tiene a lo sumo un diseño (`plan_id` único).
 * - `quote_design_versions`: copia inmutable de cada versión guardada.
 * - `quote_design_issuances`: con qué versión se emitió cada plan de cada cotización, para que
 *   regenerar una propuesta ya enviada no cambie su aspecto aunque el diseño se edite después.
 *
 * Nada de esto se borra en la operación normal: un diseño se inactiva.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quote_designs')) {
            Schema::create('quote_designs', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->string('status', 20)->default('INACTIVO')->index();
                $table->json('theme')->nullable();
                $table->json('pages_before')->nullable();
                $table->json('pages_after')->nullable();
                $table->string('logo_path')->nullable();
                $table->string('font_regular_path')->nullable();
                $table->string('font_bold_path')->nullable();
                $table->string('font_mono_path')->nullable();
                $table->string('source_html_path')->nullable();
                $table->unsignedInteger('version')->default(0);
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('quote_design_plan')) {
            Schema::create('quote_design_plan', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quote_design_id')->constrained('quote_designs', 'id', 'qdp_design_fk')->restrictOnDelete();
                $table->unsignedBigInteger('plan_id')->unique('qdp_plan_unique');
                $table->timestamps();

                $table->index('quote_design_id', 'qdp_design_idx');
            });
        }

        if (! Schema::hasTable('quote_design_versions')) {
            Schema::create('quote_design_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quote_design_id')->constrained('quote_designs', 'id', 'qdv_design_fk')->restrictOnDelete();
                $table->unsignedInteger('version');
                $table->json('snapshot');
                $table->string('fingerprint', 40);
                $table->string('created_by')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->unique(['quote_design_id', 'version'], 'qdv_design_version_unique');
            });
        }

        if (! Schema::hasTable('quote_design_issuances')) {
            Schema::create('quote_design_issuances', function (Blueprint $table): void {
                $table->id();
                $table->string('scope', 20);
                $table->unsignedBigInteger('quote_id');
                $table->unsignedBigInteger('plan_id');
                $table->foreignId('quote_design_version_id')->constrained('quote_design_versions', 'id', 'qdi_version_fk')->restrictOnDelete();
                $table->timestamp('created_at')->nullable();

                $table->unique(['scope', 'quote_id', 'plan_id'], 'qdi_quote_plan_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_design_issuances');
        Schema::dropIfExists('quote_design_versions');
        Schema::dropIfExists('quote_design_plan');
        Schema::dropIfExists('quote_designs');
    }
};
