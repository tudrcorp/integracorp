<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plan_benefit_clinical_settings')) {
            Schema::create('plan_benefit_clinical_settings', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('plan_id');
                $table->unsignedBigInteger('benefit_id');
                $table->boolean('applies_clinically')->default(true);
                $table->string('channel', 32)->nullable();
                $table->unsignedBigInteger('telemedicine_service_list_id')->nullable();
                $table->unsignedBigInteger('service_id')->nullable();
                $table->string('quota_scope', 32)->nullable();
                $table->unsignedSmallInteger('quota')->nullable();
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['plan_id', 'benefit_id'], 'plan_benefit_clinical_unique');
                $table->index(['plan_id', 'applies_clinically'], 'pbcs_plan_applies');
                $table->index(['plan_id', 'channel'], 'pbcs_plan_channel');
                $table->index('telemedicine_service_list_id', 'pbcs_service_list');
            });

            return;
        }

        Schema::table('plan_benefit_clinical_settings', function (Blueprint $table): void {
            $indexes = collect(Schema::getIndexes('plan_benefit_clinical_settings'))
                ->pluck('name')
                ->all();

            if (! in_array('pbcs_service_list', $indexes, true)
                && ! in_array('plan_benefit_clinical_settings_telemedicine_service_list_id_index', $indexes, true)) {
                $table->index('telemedicine_service_list_id', 'pbcs_service_list');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_benefit_clinical_settings');
    }
};
