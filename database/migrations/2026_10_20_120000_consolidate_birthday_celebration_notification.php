<?php

use App\Support\Birthday\BirthdayCelebrationCard;
use App\Support\BirthdayNotificationAudience;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('birthday_notifications') || ! Schema::hasColumn('birthday_notifications', 'sends_celebration_card')) {
            return;
        }

        $allTypes = json_encode(BirthdayNotificationAudience::allRecipientDataTypeKeys());
        $now = now();

        $celebrationIds = DB::table('birthday_notifications')
            ->where('sends_celebration_card', true)
            ->orderBy('id')
            ->pluck('id');

        if ($celebrationIds->isEmpty()) {
            DB::table('birthday_notifications')->insert([
                'title' => 'CUMPLEAÑOS ANIMADO',
                'header_title' => 'Apreciado/a',
                'content' => 'Hoy celebramos tu día. Tu Doctor Group te desea un feliz cumpleaños.',
                'file' => BirthdayCelebrationCard::PUBLIC_RELATIVE_PATH,
                'is_sent' => false,
                'status' => 'POR-APROBAR',
                'channels' => json_encode(['email', 'whatsapp']),
                'type' => 'image',
                'data_type' => $allTypes,
                'sends_celebration_card' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $primaryId = (int) $celebrationIds->first();

            DB::table('birthday_notifications')
                ->where('id', $primaryId)
                ->update([
                    'data_type' => $allTypes,
                    'sends_celebration_card' => true,
                    'updated_at' => $now,
                ]);

            if ($celebrationIds->count() > 1) {
                DB::table('birthday_notifications')
                    ->whereIn('id', $celebrationIds->slice(1)->all())
                    ->update([
                        'sends_celebration_card' => false,
                        'status' => 'POR-APROBAR',
                        'updated_at' => $now,
                    ]);
            }
        }

        DB::table('birthday_notifications')
            ->where('sends_celebration_card', false)
            ->where('status', 'APROBADA')
            ->update([
                'status' => 'POR-APROBAR',
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        // Sin reversión: no reactivar notificaciones legadas ni duplicar tarjetas animadas.
    }
};
