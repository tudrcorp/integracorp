<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('corporate_ally_contact_principals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporate_ally_id')->constrained('corporate_allies')->cascadeOnDelete();
            $table->string('departament')->nullable();
            $table->string('position')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('personal_phone')->nullable();
            $table->string('local_phone')->nullable();
            $table->string('extensions')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_ally_contact_principals');
    }
};
