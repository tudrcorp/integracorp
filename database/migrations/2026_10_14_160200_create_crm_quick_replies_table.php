<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Respuestas rápidas del CRM, administradas por el supervisor. Sin área: sirven en todas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_quick_replies')) {
            return;
        }

        Schema::create('crm_quick_replies', function (Blueprint $table): void {
            $table->id();
            $table->string('area', 40)->nullable();
            $table->string('label', 60);
            $table->text('body');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('uses')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['active', 'area'], 'crm_qr_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_quick_replies');
    }
};
