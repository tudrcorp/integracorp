<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Models\CrmAnalystPresence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Disponible, ocupado o ausente. Lo elige el analista; «desconectado» lo deduce el sistema
 * cuando su bandeja deja de dar señales. Un analista sin fila cuenta como disponible.
 */
final class CrmInboxPresence
{
    public const AVAILABLE = 'available';

    public const BUSY = 'busy';

    public const AWAY = 'away';

    public const OFFLINE = 'offline';

    /**
     * @var array<string, array{label: string, hint: string}>
     */
    public const STATUSES = [
        self::AVAILABLE => ['label' => 'Disponible', 'hint' => 'Recibes avisos y casos nuevos.'],
        self::BUSY => ['label' => 'Ocupado', 'hint' => 'Sigues con tus casos. «Tomar el siguiente» no te da casos nuevos.'],
        self::AWAY => ['label' => 'Ausente', 'hint' => 'Sin avisos de casos nuevos. Tus casos se pueden relevar.'],
    ];

    /**
     * Segundos entre dos marcas de «sigo aquí». El sondeo corre cada 3 a 10 s; escribir en cada uno sobra.
     */
    public const TOUCH_EVERY = 60;

    public static function set(int $userId, string $status): bool
    {
        if (! isset(self::STATUSES[$status]) || ! self::ready()) {
            return false;
        }

        CrmAnalystPresence::query()->updateOrCreate(
            ['user_id' => $userId],
            ['status' => $status, 'status_at' => now(), 'seen_at' => now()],
        );

        CrmInboxPulse::bump();

        return true;
    }

    public static function touch(int $userId): void
    {
        if (! self::ready()) {
            return;
        }

        $updated = CrmAnalystPresence::query()->where('user_id', $userId)->update(['seen_at' => now()]);

        if ($updated === 0) {
            CrmAnalystPresence::query()->firstOrCreate(
                ['user_id' => $userId],
                ['status' => self::AVAILABLE, 'status_at' => now(), 'seen_at' => now()],
            );
        }
    }

    public static function chosen(?int $userId): string
    {
        if ($userId === null || ! self::ready()) {
            return self::AVAILABLE;
        }

        $status = CrmAnalystPresence::query()->where('user_id', $userId)->value('status');

        return is_string($status) && isset(self::STATUSES[$status]) ? $status : self::AVAILABLE;
    }

    /**
     * Estado efectivo de cada usuario: el elegido, o desconectado si su bandeja calló.
     *
     * @param  list<int>  $userIds
     * @return array<int, string>
     */
    public static function effective(array $userIds, ?Carbon $now = null): array
    {
        $userIds = array_values(array_unique(array_filter($userIds)));

        if ($userIds === []) {
            return [];
        }

        $now ??= Carbon::now();
        $result = array_fill_keys($userIds, self::OFFLINE);

        if (! self::ready()) {
            return array_fill_keys($userIds, self::AVAILABLE);
        }

        $cutoff = $now->copy()->subMinutes(max(1, (int) config('crm-inbox.presence_offline_minutes', 5)));

        foreach (CrmAnalystPresence::query()->whereIn('user_id', $userIds)->get(['user_id', 'status', 'seen_at']) as $row) {
            $status = isset(self::STATUSES[$row->status]) ? (string) $row->status : self::AVAILABLE;

            if ($status !== self::AWAY && ($row->seen_at === null || $row->seen_at->lessThan($cutoff))) {
                $status = self::OFFLINE;
            }

            $result[(int) $row->user_id] = $status;
        }

        return $result;
    }

    public static function label(string $status): string
    {
        return self::STATUSES[$status]['label'] ?? 'Desconectado';
    }

    private static function ready(): bool
    {
        return self::$ready ??= Schema::hasTable('crm_analyst_presence');
    }

    public static function forgetSchema(): void
    {
        self::$ready = null;
    }

    private static ?bool $ready = null;
}
