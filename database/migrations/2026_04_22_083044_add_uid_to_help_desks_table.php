<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $uidColumnMissing = ! Schema::hasColumn('help_desks', 'uid');

        Schema::table('help_desks', function (Blueprint $table) use ($uidColumnMissing): void {
            if ($uidColumnMissing) {
                $table->string('uid', 40)->nullable()->after('id');
                $table->unique('uid');
            }
        });

        DB::table('help_desks')
            ->whereNull('uid')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $ticket): void {
                DB::table('help_desks')
                    ->where('id', $ticket->id)
                    ->update([
                        'uid' => 'TK-'.Str::upper((string) Str::ulid()),
                    ]);
            });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('help_desks', function (Blueprint $table): void {
            if (Schema::hasColumn('help_desks', 'uid')) {
                $table->dropUnique('help_desks_uid_unique');
                $table->dropColumn('uid');
            }
        });
    }
};
