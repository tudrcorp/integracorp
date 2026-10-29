<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voucher ILS de cada upgrade asignado (individual y corporativo): número,
 * vigencia y comprobante. Es del upgrade, no del afiliado: no pisa el voucher
 * del plan. El número se valida único en todo el sistema al asignar.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = ['affiliate_upgrades', 'affiliate_corporate_upgrades'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) use ($name): void {
                if (! Schema::hasColumn($name, 'ils_voucher_code')) {
                    $table->string('ils_voucher_code', 80)->nullable()->after('amount')->index($name === 'affiliate_upgrades' ? 'aff_upgrades_ils_code_idx' : 'aff_corp_upgrades_ils_code_idx');
                }

                if (! Schema::hasColumn($name, 'ils_date_init')) {
                    $table->date('ils_date_init')->nullable()->after('ils_voucher_code');
                }

                if (! Schema::hasColumn($name, 'ils_date_end')) {
                    $table->date('ils_date_end')->nullable()->after('ils_date_init');
                }

                if (! Schema::hasColumn($name, 'ils_document_path')) {
                    $table->string('ils_document_path')->nullable()->after('ils_date_end');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) use ($name): void {
                if (Schema::hasColumn($name, 'ils_voucher_code')) {
                    $table->dropIndex($name === 'affiliate_upgrades' ? 'aff_upgrades_ils_code_idx' : 'aff_corp_upgrades_ils_code_idx');
                }

                foreach (['ils_voucher_code', 'ils_date_init', 'ils_date_end', 'ils_document_path'] as $column) {
                    if (Schema::hasColumn($name, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
