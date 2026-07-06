<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_desks', function (Blueprint $table): void {
            $table->timestamp('latest_note_at')->nullable()->after('observation');
            $table->string('latest_note_by')->nullable()->after('latest_note_at');
        });
    }

    public function down(): void
    {
        Schema::table('help_desks', function (Blueprint $table): void {
            $table->dropColumn(['latest_note_at', 'latest_note_by']);
        });
    }
};
