<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mass_notification_folders')) {
            Schema::create('mass_notification_folders', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        $defaultFolderId = DB::table('mass_notification_folders')
            ->where('is_default', true)
            ->value('id');

        if ($defaultFolderId === null) {
            $now = now();
            $defaultFolderId = DB::table('mass_notification_folders')->insertGetId([
                'name' => 'Sin organizar',
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! Schema::hasColumn('mass_notifications', 'mass_notification_folder_id')) {
            Schema::table('mass_notifications', function (Blueprint $table) {
                $table->foreignId('mass_notification_folder_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('mass_notification_folders')
                    ->nullOnDelete();
            });

            DB::table('mass_notifications')
                ->whereNull('mass_notification_folder_id')
                ->update(['mass_notification_folder_id' => $defaultFolderId]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mass_notifications', 'mass_notification_folder_id')) {
            Schema::table('mass_notifications', function (Blueprint $table) {
                $table->dropForeign(['mass_notification_folder_id']);
                $table->dropColumn('mass_notification_folder_id');
            });
        }

        Schema::dropIfExists('mass_notification_folders');
    }
};
