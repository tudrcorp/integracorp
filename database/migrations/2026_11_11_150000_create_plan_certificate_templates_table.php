<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diseños HTML de la hoja del certificado por plan (Generador de Certificado).
 * Aditiva e idempotente: un plan sin diseño sigue con el certificado predeterminado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plan_certificate_templates')) {
            Schema::create('plan_certificate_templates', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('plan_id');
                $table->unsignedInteger('version');
                $table->string('status', 20)->default('INACTIVO');
                $table->longText('html');
                $table->string('original_name', 255)->nullable();
                $table->char('original_sha1', 40)->nullable();
                $table->json('removed')->nullable();
                $table->unsignedSmallInteger('page_count')->default(1);
                $table->string('notes', 1000)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('created_by_name', 150)->nullable();
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('deactivated_at')->nullable();
                $table->string('deactivated_by_name', 150)->nullable();
                $table->string('deactivation_reason', 1000)->nullable();
                $table->timestamps();

                $table->unique(['plan_id', 'version'], 'pct_plan_version_unique');
                $table->index(['plan_id', 'status'], 'pct_plan_status_idx');
            });
        }

        if (Schema::hasTable('affiliation_certificate_issues') && ! Schema::hasColumn('affiliation_certificate_issues', 'plan_certificate_template_id')) {
            Schema::table('affiliation_certificate_issues', function (Blueprint $table): void {
                $table->unsignedBigInteger('plan_certificate_template_id')->nullable()->after('plan_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('affiliation_certificate_issues', 'plan_certificate_template_id')) {
            Schema::table('affiliation_certificate_issues', function (Blueprint $table): void {
                $table->dropColumn('plan_certificate_template_id');
            });
        }

        Schema::dropIfExists('plan_certificate_templates');
    }
};
