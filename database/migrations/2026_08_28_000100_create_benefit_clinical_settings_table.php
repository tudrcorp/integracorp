<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('benefit_clinical_settings')) {
            return;
        }

        Schema::create('benefit_clinical_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('benefit_id')->unique();
            $table->boolean('applies_clinically')->default(true);
            $table->string('channel', 32)->nullable();
            $table->unsignedBigInteger('telemedicine_service_list_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->string('quota_scope', 32)->nullable();
            $table->unsignedSmallInteger('quota')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->index('channel');
            $table->index('telemedicine_service_list_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benefit_clinical_settings');
    }
};
