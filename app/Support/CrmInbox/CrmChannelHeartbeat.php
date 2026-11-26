<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Mail\InternalNotificationMail;
use App\Models\User;
use App\Support\Filament\UserNavigationAccess;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Historia de la salud del canal: una medición por minuto (24 h), incidentes (cuándo empezó y
 * terminó cada caída) y avisos a los SUPERADMIN cuando WhatsApp, n8n o el bot pasan más de
 * `health_alert_minutes` caídos, y cuando se recuperan. Lo llama el vigilante del CRM cada minuto.
 *
 * Vive en Redis (caché del CRM): sin tablas nuevas. Si Redis no responde, no se guarda historia,
 * pero la página sigue mostrando la salud del momento.
 */
final class CrmChannelHeartbeat
{
    public const BEATS_KEY = 'crm-channel:beats';

    public const STATE_KEY = 'crm-channel:state';

    public const INCIDENTS_KEY = 'crm-channel:incidents';

    /** 24 horas de mediciones, una por minuto. */
    public const MAX_BEATS = 1440;

    public const MAX_INCIDENTS = 40;

    /** Lo que amerita despertar a un SUPERADMIN: sin esto, los clientes no reciben respuesta. */
    public const ALERTABLE = ['whatsapp', 'n8n', 'bot'];

    /**
     * @param  array{at: int, checks: array<string, array{key: string, label: string, status: string, detail: string}>}  $probe
     * @return array{alerts: int, recoveries: int}
     */
    public static function record(array $probe, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $minute = intdiv($now->getTimestamp(), 60) * 60;
        $statuses = array_map(static fn (array $check): string => $check['status'], $probe['checks']);

        $saved = CrmInboxCache::attempt(static function (Repository $store) use ($minute, $statuses): bool {
            $beats = $store->get(self::BEATS_KEY);
            $beats = is_array($beats) ? $beats : [];

            if ($beats !== [] && (int) ($beats[0]['t'] ?? 0) === $minute) {
                $beats[0] = ['t' => $minute, 's' => $statuses];
            } else {
                array_unshift($beats, ['t' => $minute, 's' => $statuses]);
            }

            return $store->forever(self::BEATS_KEY, array_slice($beats, 0, self::MAX_BEATS));
        }, false);

        if ($saved !== true) {
            return ['alerts' => 0, 'recoveries' => 0];
        }

        return self::transitions($probe, $now);
    }

    /**
     * Abre y cierra incidentes, y avisa una sola vez por caída (y otra al recuperarse).
     *
     * @param  array{at: int, checks: array<string, array{key: string, label: string, status: string, detail: string}>}  $probe
     * @return array{alerts: int, recoveries: int}
     */
    private static function transitions(array $probe, Carbon $now): array
    {
        $state = CrmInboxCache::attempt(static fn (Repository $store): mixed => $store->get(self::STATE_KEY), []);
        $state = is_array($state) ? $state : [];
        $alertAfter = max(1, (int) config('crm-inbox.health_alert_minutes', 2)) * 60;
        $alerts = 0;
        $recoveries = 0;

        foreach ($probe['checks'] as $key => $check) {
            $previous = is_array($state[$key] ?? null) ? $state[$key] : ['status' => CrmChannelHealth::OK, 'since' => $now->getTimestamp(), 'alerted' => false];
            $status = $check['status'];

            if ($status === CrmChannelHealth::UNKNOWN) {
                continue;
            }

            if ($status !== $previous['status']) {
                if ($previous['status'] !== CrmChannelHealth::OK) {
                    self::closeIncident($key, $check['label'], $previous, $now);

                    if (($previous['alerted'] ?? false) === true && $status === CrmChannelHealth::OK) {
                        self::alert($check, false, $now->getTimestamp() - (int) $previous['since']);
                        $recoveries++;
                    }
                }

                $state[$key] = ['status' => $status, 'since' => $now->getTimestamp(), 'alerted' => false, 'detail' => $check['detail']];

                continue;
            }

            $state[$key] = [...$previous, 'detail' => $check['detail']];

            if ($status === CrmChannelHealth::DOWN && in_array($key, self::ALERTABLE, true) && ($previous['alerted'] ?? false) !== true
                && $now->getTimestamp() - (int) $previous['since'] >= $alertAfter) {
                self::alert($check, true, $now->getTimestamp() - (int) $previous['since']);
                $state[$key]['alerted'] = true;
                $alerts++;
            }
        }

        CrmInboxCache::attempt(static fn (Repository $store): bool => $store->forever(self::STATE_KEY, $state));

        return ['alerts' => $alerts, 'recoveries' => $recoveries];
    }

    /**
     * @param  array{status: string, since: int|string, detail?: string}  $previous
     */
    private static function closeIncident(string $key, string $label, array $previous, Carbon $now): void
    {
        $incident = [
            'key' => $key,
            'label' => $label,
            'status' => $previous['status'],
            'from' => (int) $previous['since'],
            'to' => $now->getTimestamp(),
            'detail' => mb_substr((string) ($previous['detail'] ?? ''), 0, 240),
        ];

        CrmInboxCache::attempt(static function (Repository $store) use ($incident): bool {
            $list = $store->get(self::INCIDENTS_KEY);
            $list = is_array($list) ? $list : [];
            array_unshift($list, $incident);

            return $store->forever(self::INCIDENTS_KEY, array_slice($list, 0, self::MAX_INCIDENTS));
        });
    }

    /**
     * Mediciones de los últimos minutos, de la más antigua a la más reciente, con huecos marcados.
     *
     * @return list<array{t: int, s: array<string, string>|null}>
     */
    public static function history(int $minutes = 60, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();
        $beats = CrmInboxCache::attempt(static fn (Repository $store): mixed => $store->get(self::BEATS_KEY), []);
        $byMinute = [];

        foreach (is_array($beats) ? $beats : [] as $beat) {
            if (is_array($beat) && isset($beat['t'], $beat['s']) && is_array($beat['s'])) {
                $byMinute[(int) $beat['t']] = $beat['s'];
            }
        }

        $last = intdiv($now->getTimestamp(), 60) * 60;
        $rows = [];

        for ($t = $last - ($minutes - 1) * 60; $t <= $last; $t += 60) {
            $rows[] = ['t' => $t, 's' => $byMinute[$t] ?? null];
        }

        return $rows;
    }

    /**
     * Porcentaje de minutos en verde de cada sonda en las últimas 24 horas (solo minutos medidos).
     *
     * @return array<string, ?float>
     */
    public static function uptime(?Carbon $now = null): array
    {
        $rows = self::history(self::MAX_BEATS, $now);
        $result = [];

        foreach (array_keys(CrmChannelHealth::CHECKS) as $key) {
            $measured = 0;
            $ok = 0;

            foreach ($rows as $row) {
                $status = $row['s'][$key] ?? null;

                if ($status === null || $status === CrmChannelHealth::UNKNOWN) {
                    continue;
                }

                $measured++;
                $ok += $status === CrmChannelHealth::DOWN ? 0 : 1;
            }

            $result[$key] = $measured > 0 ? round($ok / $measured * 100, 1) : null;
        }

        return $result;
    }

    /**
     * Incidentes: los que siguen abiertos primero, luego los cerrados (más recientes primero).
     *
     * @return list<array{key: string, label: string, status: string, from: int, to: ?int, detail: string}>
     */
    public static function incidents(): array
    {
        $state = CrmInboxCache::attempt(static fn (Repository $store): mixed => $store->get(self::STATE_KEY), []);
        $closed = CrmInboxCache::attempt(static fn (Repository $store): mixed => $store->get(self::INCIDENTS_KEY), []);
        $open = [];

        foreach (is_array($state) ? $state : [] as $key => $row) {
            if (is_array($row) && ($row['status'] ?? CrmChannelHealth::OK) !== CrmChannelHealth::OK && isset(CrmChannelHealth::CHECKS[$key])) {
                $open[] = ['key' => (string) $key, 'label' => CrmChannelHealth::CHECKS[$key], 'status' => (string) $row['status'], 'from' => (int) ($row['since'] ?? 0), 'to' => null, 'detail' => (string) ($row['detail'] ?? '')];
            }
        }

        $closed = array_values(array_filter(is_array($closed) ? $closed : [], static fn (mixed $row): bool => is_array($row) && isset($row['key'], $row['from'])));

        return array_slice([...$open, ...$closed], 0, 15);
    }

    /**
     * @param  array{key: string, label: string, status: string, detail: string}  $check
     */
    private static function alert(array $check, bool $down, int $seconds): void
    {
        $minutes = max(1, intdiv($seconds, 60));
        $title = $down
            ? 'Atención WhatsApp: '.$check['label'].' caído hace '.$minutes.' min'
            : 'Atención WhatsApp: '.$check['label'].' se recuperó';
        $body = $down
            ? $check['detail'].' Mientras siga así, los clientes de WhatsApp pueden quedarse sin respuesta. Revisa Supervisión WhatsApp → Salud.'
            : 'Volvió a funcionar después de '.$minutes.' min caído.';

        $recipients = self::recipients();

        if ($recipients->isEmpty()) {
            Log::warning('CRM salud: no hay SUPERADMIN activos para avisar', ['check' => $check['key']]);

            return;
        }

        try {
            Notification::make()
                ->title($title)
                ->body($body)
                ->icon($down ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                ->iconColor($down ? 'danger' : 'success')
                ->sendToDatabase($recipients);
        } catch (Throwable $exception) {
            report($exception);
        }

        foreach ($recipients as $user) {
            $email = mb_strtolower(trim((string) $user->email));

            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            try {
                Mail::to($email)->queue(new InternalNotificationMail($title, $body, false));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        Log::warning('CRM salud: '.$title, ['check' => $check['key'], 'detail' => $check['detail']]);
    }

    /**
     * @return Collection<int, User>
     */
    public static function recipients(): Collection
    {
        return User::query()
            ->where('status', 'ACTIVO')
            ->where('departament', 'like', '%SUPERADMIN%')
            ->orderBy('id')
            ->get()
            ->filter(static fn (User $user): bool => UserNavigationAccess::isSuperAdmin($user))
            ->values();
    }
}
