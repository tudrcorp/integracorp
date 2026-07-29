<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\SendNotificationMasive;

final class MassNotificationWhatsAppJobScheduler
{
    public static function throttleSeconds(): int
    {
        return max(1, (int) config('mass-notifications.whatsapp_throttle_seconds', 20));
    }

    /**
     * @param  list<SendNotificationMasive>  $jobs
     * @return list<SendNotificationMasive>
     */
    public static function withStaggeredDelays(array $jobs): array
    {
        $throttle = self::throttleSeconds();
        $staggered = [];

        foreach (array_values($jobs) as $index => $job) {
            $staggered[] = $job->delay(now()->addSeconds($index * $throttle));
        }

        return $staggered;
    }

    public static function successMessage(int $queuedJobs, int $whatsappCount): string
    {
        if ($whatsappCount <= 0) {
            return 'Envío encolado exitosamente. Integracorp te notificará cuando el proceso finalice.';
        }

        $throttle = self::throttleSeconds();
        $etaSeconds = max(0, ($whatsappCount - 1) * $throttle);
        $etaLabel = self::formatEta($etaSeconds);

        return sprintf(
            'Envío encolado exitosamente (%d job(s) en total). Se encolaron %d WhatsApp a ~1 cada %d s (ETA ~%s). Al finalizar, se reintentarán automáticamente los WhatsApp pendientes o fallidos. Integracorp te notificará cuando el proceso finalice.',
            $queuedJobs,
            $whatsappCount,
            $throttle,
            $etaLabel,
        );
    }

    private static function formatEta(int $etaSeconds): string
    {
        if ($etaSeconds < 60) {
            return $etaSeconds.' s';
        }

        $minutes = (int) ceil($etaSeconds / 60);

        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return $hours.' h';
        }

        return $hours.' h '.$remainingMinutes.' min';
    }
}
