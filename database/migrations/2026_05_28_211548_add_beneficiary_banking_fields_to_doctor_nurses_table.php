<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const BANKING_COLUMNS = [
        'local_beneficiary_name',
        'local_beneficiary_rif',
        'local_beneficiary_account_number',
        'local_beneficiary_account_bank',
        'local_beneficiary_account_type',
        'local_beneficiary_phone_pm',
        'local_beneficiary_account_number_mon_inter',
        'local_beneficiary_account_bank_mon_inter',
        'local_beneficiary_account_type_mon_inter',
        'extra_beneficiary_name',
        'extra_beneficiary_ci_rif',
        'extra_beneficiary_account_number',
        'extra_beneficiary_account_bank',
        'extra_beneficiary_account_type',
        'extra_beneficiary_route',
        'extra_beneficiary_swift',
        'extra_beneficiary_zelle',
        'extra_beneficiary_address',
    ];

    public function up(): void
    {
        Schema::table('doctor_nurses', function (Blueprint $table): void {
            foreach (self::BANKING_COLUMNS as $column) {
                if (! Schema::hasColumn('doctor_nurses', $column)) {
                    $table->string($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('doctor_nurses', function (Blueprint $table): void {
            foreach (self::BANKING_COLUMNS as $column) {
                if (Schema::hasColumn('doctor_nurses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
