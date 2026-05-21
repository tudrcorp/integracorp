<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('project_task_activities');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('project_board_columns');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('project_types');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
