<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('epics')) {
            Schema::create('epics', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status')->default('open');
                $table->unsignedInteger('order')->default(0);
                $table->timestamps();

                $table->index(['project_id', 'status']);
                $table->index(['project_id', 'order']);
            });
        }

        if (! Schema::hasTable('sprints')) {
            Schema::create('sprints', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
                $table->string('name');
                $table->text('goal')->nullable();
                $table->date('starts_at');
                $table->date('ends_at');
                $table->string('status')->default('planned');
                $table->timestamps();

                $table->index(['project_id', 'status']);
                $table->index(['project_id', 'starts_at']);
            });
        }

        if (! Schema::hasTable('project_scrum_roles')) {
            Schema::create('project_scrum_roles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('project_id')->unique()->constrained('projects')->cascadeOnDelete();
                $table->foreignId('product_owner_id')->nullable()->constrained('rrhh_colaboradors')->nullOnDelete();
                $table->foreignId('scrum_master_id')->nullable()->constrained('rrhh_colaboradors')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sprint_ceremonies')) {
            Schema::create('sprint_ceremonies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('sprint_id')->constrained('sprints')->cascadeOnDelete();
                $table->string('type');
                $table->dateTime('scheduled_at');
                $table->dateTime('ended_at')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('facilitator_id')->nullable()->constrained('rrhh_colaboradors')->nullOnDelete();
                $table->timestamps();

                $table->index(['sprint_id', 'type']);
                $table->index(['sprint_id', 'scheduled_at']);
            });
        }

        if (! Schema::hasTable('sprint_daily_metrics')) {
            Schema::create('sprint_daily_metrics', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('sprint_id')->constrained('sprints')->cascadeOnDelete();
                $table->date('date');
                $table->unsignedInteger('committed_points')->default(0);
                $table->unsignedInteger('remaining_points')->default(0);
                $table->unsignedInteger('completed_points')->default(0);
                $table->timestamps();

                $table->unique(['sprint_id', 'date']);
            });
        }

        if (Schema::hasTable('activities')) {
            Schema::table('activities', function (Blueprint $table): void {
                if (! Schema::hasColumn('activities', 'epic_id')) {
                    $table->foreignId('epic_id')->nullable()->after('subproject_id')->constrained('epics')->nullOnDelete();
                }

                if (! Schema::hasColumn('activities', 'sprint_id')) {
                    $table->foreignId('sprint_id')->nullable()->after('epic_id')->constrained('sprints')->nullOnDelete();
                }

                if (! Schema::hasColumn('activities', 'story_points')) {
                    $table->unsignedTinyInteger('story_points')->nullable()->after('priority');
                }

                if (! Schema::hasColumn('activities', 'backlog_order')) {
                    $table->unsignedInteger('backlog_order')->nullable()->after('story_points');
                }

                if (! Schema::hasColumn('activities', 'acceptance_criteria')) {
                    $table->text('acceptance_criteria')->nullable()->after('description');
                }

                if (! Schema::hasColumn('activities', 'completed_at')) {
                    $table->timestamp('completed_at')->nullable()->after('kanban_archived_at');
                }
            });

            Schema::table('activities', function (Blueprint $table): void {
                $table->index(['project_id', 'sprint_id', 'status'], 'activities_project_sprint_status_index');
                $table->index(['project_id', 'backlog_order'], 'activities_project_backlog_order_index');
                $table->index(['sprint_id', 'status'], 'activities_sprint_status_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('activities')) {
            Schema::table('activities', function (Blueprint $table): void {
                if (Schema::hasColumn('activities', 'epic_id')) {
                    $table->dropConstrainedForeignId('epic_id');
                }

                if (Schema::hasColumn('activities', 'sprint_id')) {
                    $table->dropConstrainedForeignId('sprint_id');
                }

                foreach (['story_points', 'backlog_order', 'acceptance_criteria', 'completed_at'] as $column) {
                    if (Schema::hasColumn('activities', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('sprint_daily_metrics');
        Schema::dropIfExists('sprint_ceremonies');
        Schema::dropIfExists('project_scrum_roles');
        Schema::dropIfExists('sprints');
        Schema::dropIfExists('epics');
    }
};
