<?php

declare(strict_types=1);

use App\Support\MedicalQuestionnaires\MedicalQuestionnaireLegacy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuestionario médico dinámico (Negocios → Cuestionarios médicos).
 *
 * - `medical_questionnaires`: un cuestionario por plan (hoy el Plan Especial).
 * - `medical_questionnaire_questions`: sus preguntas, en orden. Nunca se borra una pregunta
 *   respondida: se desactiva. `legacy_column` liga cada una de las 16 originales con su columna
 *   `cuestion_N` de `affiliations`.
 * - `medical_questionnaire_answers`: la respuesta Sí/No de cada afiliación (individual o
 *   corporativa) con una copia del texto de la pregunta tal como se preguntó.
 *
 * Al final carga las 16 preguntas del Plan Especial y pasa las respuestas que ya existen.
 * Aditiva e idempotente: se puede aplicar con `--path` aunque ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('medical_questionnaires')) {
            Schema::create('medical_questionnaires', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->string('name', 255);
                $table->boolean('is_active')->default(true);
                $table->string('created_by', 255)->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->timestamps();

                $table->unique('plan_id', 'mq_plan_unique');
            });
        }

        if (! Schema::hasTable('medical_questionnaire_questions')) {
            Schema::create('medical_questionnaire_questions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('medical_questionnaire_id');
                $table->unsignedInteger('sort_order')->default(0);
                $table->text('text');
                $table->boolean('is_active')->default(true);
                $table->string('legacy_column', 30)->nullable();
                $table->string('created_by', 255)->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->timestamps();

                $table->index(['medical_questionnaire_id', 'sort_order'], 'mqq_order_idx');
            });
        }

        if (! Schema::hasTable('medical_questionnaire_answers')) {
            Schema::create('medical_questionnaire_answers', function (Blueprint $table): void {
                $table->id();
                $table->string('answerable_type', 20);
                $table->unsignedBigInteger('answerable_id');
                $table->unsignedBigInteger('medical_questionnaire_question_id');
                $table->text('question_text');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('answer')->nullable();
                $table->string('answered_by', 255)->nullable();
                $table->timestamps();

                $table->unique(['answerable_type', 'answerable_id', 'medical_questionnaire_question_id'], 'mqa_answer_unique');
                $table->index('medical_questionnaire_question_id', 'mqa_question_idx');
            });
        }

        MedicalQuestionnaireLegacy::seed();
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_questionnaire_answers');
        Schema::dropIfExists('medical_questionnaire_questions');
        Schema::dropIfExists('medical_questionnaires');
    }
};
