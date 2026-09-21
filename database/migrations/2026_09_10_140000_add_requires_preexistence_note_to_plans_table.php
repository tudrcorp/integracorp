<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('plans', 'requires_preexistence_note')) {
                $table->boolean('requires_preexistence_note')->default(false)->after('pricing_mode');
            }
        });

        if (Schema::hasColumn('plans', 'requires_preexistence_note')) {
            // El certificado imprimía la nota solo si plan_id == 3; se conserva ese comportamiento.
            DB::table('plans')->where('id', 3)->update(['requires_preexistence_note' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            if (Schema::hasColumn('plans', 'requires_preexistence_note')) {
                $table->dropColumn('requires_preexistence_note');
            }
        });
    }
};
