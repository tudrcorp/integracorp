<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sistema de gestión de proyectos (PDF: migraciones_sistema_proyectos).
 * Tablas: departments, groups, projects, subprojects, project_assignments,
 * activities, notes_logs, documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('groups')) {
            Schema::create('groups', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();

                $table->index('name');
            });
        }

        if (! Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status')->default('active');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index(['status', 'start_date']);
            });
        }

        if (! Schema::hasTable('subprojects')) {
            Schema::create('subprojects', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status')->default('pending');
                $table->timestamps();

                $table->index(['project_id', 'status']);
            });
        }

        if (! Schema::hasTable('project_assignments')) {
            Schema::create('project_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->numericMorphs('assignable');
                $table->timestamps();

                $table->unique(
                    ['project_id', 'assignable_type', 'assignable_id'],
                    'project_assignable_unique',
                );
            });
        }

        if (! Schema::hasTable('activities')) {
            Schema::create('activities', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->foreignId('subproject_id')->nullable()->constrained('subprojects')->cascadeOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('status')->default('todo');
                $table->string('priority')->default('medium');
                $table->numericMorphs('executor');
                $table->date('due_date')->nullable();
                $table->timestamps();

                $table->index(['project_id', 'status']);
                $table->index(['project_id', 'subproject_id']);
                $table->index(['status', 'due_date']);
            });
        }

        if (! Schema::hasTable('notes_logs')) {
            Schema::create('notes_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('content');
                $table->numericMorphs('notable');
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('documents')) {
            Schema::create('documents', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('file_path');
                $table->string('file_type')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
                $table->numericMorphs('documentable');
                $table->timestamps();

                $table->index('uploaded_by');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('notes_logs');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('project_assignments');
        Schema::dropIfExists('subprojects');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('groups');
        Schema::dropIfExists('departments');
    }
};
