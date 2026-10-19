<?php

use App\Support\Birthday\BirthdayCelebrationCard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('birthday_notifications') && ! Schema::hasColumn('birthday_notifications', 'sends_celebration_card')) {
            Schema::table('birthday_notifications', function (Blueprint $table): void {
                $table->boolean('sends_celebration_card')->default(false)->after('type');
            });
        }

        if (! Schema::hasTable('birthday_notification_exclusions')) {
            Schema::create('birthday_notification_exclusions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('birthday_notification_id');
                $table->string('data_type', 100);
                $table->unsignedBigInteger('recipient_id');
                $table->timestamps();

                $table->unique(
                    ['birthday_notification_id', 'data_type', 'recipient_id'],
                    'birthday_notification_exclusions_unique',
                );
                $table->index(
                    ['birthday_notification_id', 'data_type'],
                    'birthday_notification_exclusions_lookup',
                );
            });
        }

        if (! Schema::hasTable('birthday_notifications') || ! Schema::hasColumn('birthday_notifications', 'sends_celebration_card')) {
            return;
        }

        $alreadyExists = DB::table('birthday_notifications')
            ->where('sends_celebration_card', true)
            ->exists();

        if ($alreadyExists) {
            return;
        }

        $now = now();

        DB::table('birthday_notifications')->insert([
            'title' => 'CUMPLEAÑOS ANIMADO',
            'header_title' => 'Apreciado/a',
            'content' => 'Hoy celebramos tu día. Tu Doctor Group te desea un feliz cumpleaños.',
            'file' => BirthdayCelebrationCard::PUBLIC_RELATIVE_PATH,
            'is_sent' => false,
            'status' => 'POR-APROBAR',
            'channels' => json_encode(['email', 'whatsapp']),
            'type' => 'image',
            'data_type' => 'affiliates',
            'sends_celebration_card' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('birthday_notifications') && Schema::hasColumn('birthday_notifications', 'sends_celebration_card')) {
            DB::table('birthday_notifications')
                ->where('sends_celebration_card', true)
                ->where('title', 'CUMPLEAÑOS ANIMADO')
                ->delete();

            Schema::table('birthday_notifications', function (Blueprint $table): void {
                $table->dropColumn('sends_celebration_card');
            });
        }

        Schema::dropIfExists('birthday_notification_exclusions');
    }
};
