<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return list<string>
     */
    private function equipmentBooleanColumns(): array
    {
        return [
            'equip_diag_vital_signs',
            'equip_diag_oximeter',
            'equip_diag_thermometer',
            'equip_diag_exam_kit',
            'equip_diag_glucometer',
            'equip_diag_flashlight_hammer',
            'equip_care_gloves',
            'equip_care_antiseptics',
            'equip_care_supplies',
            'equip_care_sharps_container',
            'equip_support_hygiene',
            'equip_support_scissors_forceps',
            'equip_support_prescriptions_stamps',
            'equip_adv_basic_medicines',
            'equip_adv_catheters_aspiration',
            'equip_adv_emergency_bag',
        ];
    }

    /**
     * @return list<string>
     */
    private function equipmentDescriptionColumns(): array
    {
        return [
            'equip_desc_diag_vital_signs',
            'equip_desc_diag_oximeter',
            'equip_desc_diag_thermometer',
            'equip_desc_diag_exam_kit',
            'equip_desc_diag_glucometer',
            'equip_desc_diag_flashlight_hammer',
            'equip_desc_care_gloves',
            'equip_desc_care_antiseptics',
            'equip_desc_care_supplies',
            'equip_desc_care_sharps_container',
            'equip_desc_support_hygiene',
            'equip_desc_support_scissors_forceps',
            'equip_desc_support_prescriptions_stamps',
            'equip_desc_adv_basic_medicines',
            'equip_desc_adv_catheters_aspiration',
            'equip_desc_adv_emergency_bag',
        ];
    }

    public function up(): void
    {
        Schema::table('doctor_nurses', function (Blueprint $table) {
            foreach ($this->equipmentBooleanColumns() as $column) {
                if (! Schema::hasColumn('doctor_nurses', $column)) {
                    $table->boolean($column)->nullable();
                }
            }

            foreach ($this->equipmentDescriptionColumns() as $column) {
                if (! Schema::hasColumn('doctor_nurses', $column)) {
                    $table->text($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('doctor_nurses', function (Blueprint $table) {
            $columns = array_merge(
                $this->equipmentBooleanColumns(),
                $this->equipmentDescriptionColumns(),
            );

            $toDrop = array_values(array_filter(
                $columns,
                fn (string $column): bool => Schema::hasColumn('doctor_nurses', $column),
            ));

            if ($toDrop !== []) {
                $table->dropColumn($toDrop);
            }
        });
    }
};
