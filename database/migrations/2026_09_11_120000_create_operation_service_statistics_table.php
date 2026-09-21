<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private const INDEXES = [
        'oss_case_id_idx' => ['telemedicine_case_id'],
        'oss_consultation_id_idx' => ['telemedicine_consultation_patient_id'],
        'oss_coordination_id_idx' => ['operation_coordination_service_id'],
        'oss_order_id_idx' => ['operation_service_order_id'],
        'oss_started_on_idx' => ['started_on'],
        'oss_service_on_idx' => ['service_on'],
        'oss_business_line_idx' => ['business_line'],
        'oss_case_code_idx' => ['case_code'],
        'oss_case_status_idx' => ['case_status'],
        'oss_agency_name_idx' => ['agency_name'],
        'oss_region_idx' => ['region'],
        'oss_service_type_idx' => ['service_type'],
        'oss_coverage_idx' => ['coverage'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('operation_service_statistics')) {
            Schema::create('operation_service_statistics', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('telemedicine_case_id')->nullable();
                $table->unsignedBigInteger('telemedicine_consultation_patient_id')->nullable();
                $table->unsignedBigInteger('operation_coordination_service_id')->nullable();
                $table->unsignedBigInteger('operation_service_order_id')->nullable();
                $table->string('source_type', 32);
                $table->unsignedBigInteger('source_id');

                $table->date('started_on')->nullable();
                $table->time('started_at_time')->nullable();
                $table->date('service_on')->nullable();
                $table->string('business_line')->nullable();
                $table->string('case_code')->nullable();
                $table->string('case_status')->nullable();
                $table->string('case_created_by')->nullable();
                $table->string('case_last_touched_by')->nullable();

                $table->string('plan_holder_name')->nullable();
                $table->string('plan_holder_document')->nullable();
                $table->string('patient_name')->nullable();
                $table->string('patient_document')->nullable();
                $table->date('patient_birth_date')->nullable();
                $table->string('patient_relationship')->nullable();
                $table->unsignedInteger('patient_age')->nullable();
                $table->string('contractor')->nullable();
                $table->string('agency_name')->nullable();
                $table->string('agent_name')->nullable();
                $table->string('region')->nullable();
                $table->string('state')->nullable();
                $table->string('city')->nullable();
                $table->text('address')->nullable();
                $table->string('patient_phone')->nullable();
                $table->string('patient_email')->nullable();

                $table->text('consultation_reason')->nullable();
                $table->text('initial_diagnosis')->nullable();
                $table->text('final_diagnosis')->nullable();
                $table->string('service')->nullable();
                $table->string('specific_service')->nullable();
                $table->string('service_type')->nullable();
                $table->string('coverage')->nullable();

                $table->string('management_provider')->nullable();
                $table->string('service_provider')->nullable();
                $table->string('medical_provider')->nullable();
                $table->string('farmadoc_derived')->nullable();
                $table->string('farmadoc_detail')->nullable();

                $table->string('negotiation_type')->nullable();
                $table->string('negotiation_status')->nullable();
                $table->decimal('net_price', 12, 2)->nullable();
                $table->decimal('tdec_profit_percent', 10, 2)->nullable();
                $table->decimal('quoted_amount', 12, 2)->nullable();
                $table->string('discount_negotiation')->nullable();
                $table->decimal('discount_percent', 10, 2)->nullable();
                $table->decimal('discount_amount', 12, 2)->nullable();
                $table->string('quote_number')->nullable();
                $table->string('approval_number')->nullable();
                $table->string('service_order_number')->nullable();
                $table->string('invoice_number')->nullable();
                $table->decimal('invoiced_amount', 12, 2)->nullable();
                $table->date('invoice_issued_on')->nullable();
                $table->string('incidence')->nullable();
                $table->string('case_denied')->nullable();
                $table->string('qc_received')->nullable();
                $table->text('observations')->nullable();

                $table->timestamps();
            });
        }

        $this->ensureUniqueSourceIndex();

        foreach (self::INDEXES as $name => $columns) {
            $this->ensureIndex($name, $columns);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_service_statistics');
    }

    private function ensureUniqueSourceIndex(): void
    {
        if ($this->indexExistsOnColumns(['source_type', 'source_id'])) {
            return;
        }

        Schema::table('operation_service_statistics', function (Blueprint $table): void {
            $table->unique(['source_type', 'source_id'], 'operation_service_statistics_source_unique');
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function ensureIndex(string $name, array $columns): void
    {
        if ($this->indexExistsOnColumns($columns) || Schema::hasIndex('operation_service_statistics', $name)) {
            return;
        }

        Schema::table('operation_service_statistics', function (Blueprint $table) use ($name, $columns): void {
            $table->index($columns, $name);
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function indexExistsOnColumns(array $columns): bool
    {
        foreach (Schema::getIndexes('operation_service_statistics') as $index) {
            if (($index['columns'] ?? []) === $columns) {
                return true;
            }
        }

        return false;
    }
};
