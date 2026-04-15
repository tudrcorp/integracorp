<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tdev_reports', function (Blueprint $table) {
            $table->string('comprobante_pago_path')->nullable()->after('forma_pago');
        });
    }

    public function down(): void
    {
        Schema::table('tdev_reports', function (Blueprint $table) {
            $table->dropColumn('comprobante_pago_path');
        });
    }
};
