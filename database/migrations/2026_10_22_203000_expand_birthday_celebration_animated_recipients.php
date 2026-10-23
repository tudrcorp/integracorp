<?php

use App\Support\BirthdayNotificationAudience;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('birthday_notifications')) {
            return;
        }

        $types = json_encode(BirthdayNotificationAudience::celebrationRecipientDataTypeKeys());
        $now = now();

        $query = DB::table('birthday_notifications');

        if (Schema::hasColumn('birthday_notifications', 'sends_celebration_card')) {
            $query->where('sends_celebration_card', true);
        } else {
            $query->where('title', 'CUMPLEAÑOS ANIMADO');
        }

        $ids = $query->orderBy('id')->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('birthday_notifications')
            ->where('id', (int) $ids->first())
            ->update([
                'data_type' => $types,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        // Sin reversión: no reducir audiencia de la tarjeta animada.
    }
};
