<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tdev_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tdev_agency_id')->constrained('tdev_agencies')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('position')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->date('birth_date')->nullable();
            $table->timestamp('registered_at')->nullable()->index();
            $table->string('registration_source')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->index(['tdev_agency_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tdev_agents');
    }
};
