<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notas de seguimiento de las encuestas de Quality Control: bitácora sin edición ni borrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quality_control_survey_notes')) {
            return;
        }

        Schema::create('quality_control_survey_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quality_control_survey_id')->constrained('quality_control_surveys')->cascadeOnDelete();
            $table->text('note');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->index(['quality_control_survey_id', 'created_at'], 'qcsn_survey_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_control_survey_notes');
    }
};
