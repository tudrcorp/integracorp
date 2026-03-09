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
        Schema::create('operation_on_call_users', function (Blueprint $table) {
            $table->id();
            $table->integer('rrhh_colaborador_id');
            $table->string('name');
            $table->string('email');
            $table->string('hrs_init')->default('00:00');
            $table->string('hrs_end')->default('00:00');
            $table->string('phone');
            $table->string('status');
            $table->string('created_by');
            $table->string('updated_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_on_call_users');
    }
};
