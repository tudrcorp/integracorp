<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MassNotification;
use Carbon\Carbon;

final class MassNotificationReschedule
{
    public static function shouldReschedule(MassNotification $record, mixed $newDateProgramed): bool
    {
        if (! $record->is_sent) {
            return false;
        }

        if (! filled($newDateProgramed)) {
            return false;
        }

        try {
            $scheduledAt = Carbon::parse($newDateProgramed);
        } catch (\Throwable) {
            return false;
        }

        return $scheduledAt->isFuture();
    }

    public static function confirmationMessage(mixed $newDateProgramed): string
    {
        $formatted = Carbon::parse($newDateProgramed)->format('d/m/Y H:i');

        return "Esta notificación ya fue enviada. Se programará un nuevo envío automático para el {$formatted}. ¿Deseas continuar?";
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function applyRescheduleToFormData(MassNotification $record, array $data): array
    {
        if (! self::shouldReschedule($record, $data['date_programed'] ?? null)) {
            return $data;
        }

        $data['is_sent'] = false;

        return $data;
    }
}
