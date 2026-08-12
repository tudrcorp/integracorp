<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tdev_agencies', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(2)->after('id');
            $table->foreignId('parent_id')
                ->nullable()
                ->after('level')
                ->constrained('tdev_agencies')
                ->nullOnDelete();
            $table->uuid('agency_registration_token')->nullable()->unique()->after('registration_token');
            $table->index(['level', 'parent_id']);
        });

        DB::table('tdev_agencies')->update(['level' => 2]);

        $agencies = DB::table('tdev_agencies')
            ->whereNull('agency_registration_token')
            ->get(['id']);

        foreach ($agencies as $agency) {
            DB::table('tdev_agencies')
                ->where('id', $agency->id)
                ->update(['agency_registration_token' => (string) Str::uuid()]);
        }
    }

    public function down(): void
    {
        Schema::table('tdev_agencies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropUnique(['agency_registration_token']);
            $table->dropIndex(['level', 'parent_id']);
            $table->dropColumn(['level', 'agency_registration_token']);
        });
    }
};
