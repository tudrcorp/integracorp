<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Models\CrmHandoffEnvelope;
use Illuminate\Support\Carbon;

/**
 * Recordatorio de seguimiento de un caso en atención. Al vencer, el caso sube a «Te toca responder»
 * de quien lo programó y le llega un aviso. Se borra solo cuando el analista vuelve a escribir.
 */
final class CrmHandoffFollowUp
{
    private const ZONE = 'America/Caracas';

    /**
     * @var array<string, string>
     */
    public const PRESETS = [
        '1h' => 'En 1 hora',
        '3h' => 'En 3 horas',
        'tomorrow' => 'Mañana 9:00',
        '2d' => 'En 2 días, 9:00',
        '1w' => 'En una semana, 9:00',
    ];

    public const MAX_DAYS = 60;

    /**
     * @return array{ok: true, at: Carbon}|array{ok: false, reason: string}
     */
    public function set(CrmHandoffEnvelope $envelope, ?int $userId, string $when, string $note, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();

        if ($envelope->taken_at === null || $envelope->released_at !== null || $envelope->closed_at !== null) {
            return ['ok' => false, 'reason' => 'not_taken'];
        }

        if ($userId === null || (int) $envelope->taken_by !== $userId) {
            return ['ok' => false, 'reason' => 'not_mine'];
        }

        $at = self::resolve($when, $now);

        if ($at === null) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        $note = trim(preg_replace('/\s+/u', ' ', $note) ?? '');

        $envelope->forceFill([
            'follow_up_at' => $at,
            'follow_up_note' => $note !== '' ? mb_substr($note, 0, 200) : null,
            'follow_up_by' => $userId,
            'follow_up_notified_at' => null,
        ])->save();

        return ['ok' => true, 'at' => $at];
    }

    public function clear(CrmHandoffEnvelope $envelope): void
    {
        if ($envelope->follow_up_at === null) {
            return;
        }

        $envelope->forceFill([
            'follow_up_at' => null,
            'follow_up_note' => null,
            'follow_up_notified_at' => null,
        ])->save();
    }

    /**
     * Un atajo o una fecha y hora local (Caracas). Siempre en el futuro y a lo sumo a 60 días.
     */
    public static function resolve(string $when, Carbon $now): ?Carbon
    {
        $local = $now->copy()->timezone(self::ZONE);
        $when = trim($when);

        $at = match ($when) {
            '1h' => $local->copy()->addHour(),
            '3h' => $local->copy()->addHours(3),
            'tomorrow' => $local->copy()->addDay()->setTime(9, 0),
            '2d' => $local->copy()->addDays(2)->setTime(9, 0),
            '1w' => $local->copy()->addWeek()->setTime(9, 0),
            default => self::custom($when),
        };

        if ($at === null || $at->lessThanOrEqualTo($local) || $at->greaterThan($local->copy()->addDays(self::MAX_DAYS))) {
            return null;
        }

        return $at->timezone(config('app.timezone', 'UTC'));
    }

    private static function custom(string $value): ?Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $at = Carbon::createFromFormat('Y-m-d\TH:i', $value, self::ZONE);
        } catch (\Throwable) {
            return null;
        }

        return $at instanceof Carbon && $at->format('Y-m-d\TH:i') === $value ? $at : null;
    }

    public static function label(?Carbon $at, ?Carbon $now = null): ?string
    {
        if ($at === null) {
            return null;
        }

        $now ??= Carbon::now();
        $local = $at->copy()->timezone(self::ZONE);
        $today = $now->copy()->timezone(self::ZONE);

        if ($local->isSameDay($today)) {
            return 'hoy '.$local->format('H:i');
        }

        if ($local->isSameDay($today->copy()->addDay())) {
            return 'mañana '.$local->format('H:i');
        }

        return $local->locale('es')->isoFormat('ddd D/M H:mm');
    }
}
