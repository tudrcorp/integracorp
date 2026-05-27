<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('helpdesk_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('ACTIVO');
            $table->unsignedInteger('total_tickets_assigned')->default(0);
            $table->json('team_members')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('helpdesk_groups');
    }
};
