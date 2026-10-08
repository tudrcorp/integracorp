<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Models\CrmHandoffEnvelope;
use App\Models\CrmPushSubscription;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Arma el aviso y elige los navegadores del área. No llama a la red.
 */
final class CrmInboxAlerts
{
    /**
     * @return Collection<int, CrmPushSubscription>
     */
    /**
     * @return Collection<int, User>
     */
    public static function recipients(CrmHandoffEnvelope $envelope, ?int $onlyUserId = null): Collection
    {
        if (! Schema::hasTable('users')) {
            return new Collection;
        }

        $department = CrmInboxAreas::departmentFor($envelope->area);
        $query = User::query()
            ->where('status', 'ACTIVO')
            ->where('email', 'like', '%@tudrencasa.com');

        if ($onlyUserId !== null) {
            $query->whereKey($onlyUserId);
        }

        return $query
            ->get(['id', 'email', 'status', 'departament'])
            ->filter(fn (User $user): bool => self::canReceive($user, $department))
            ->values();
    }

    /**
     * @return Collection<int, CrmPushSubscription>
     */
    public static function subscriptionsFor(CrmHandoffEnvelope $envelope, ?int $onlyUserId = null): Collection
    {
        if (! Schema::hasTable('crm_push_subscriptions')) {
            return new Collection;
        }

        $ids = self::recipients($envelope, $onlyUserId)->pluck('id');

        if ($ids->isEmpty()) {
            return new Collection;
        }

        return CrmPushSubscription::query()
            ->whereIn('user_id', $ids)
            ->orderBy('id')
            ->get();
    }

    public static function remember(CrmHandoffEnvelope $envelope, string $notice = 'new', ?int $onlyUserId = null): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        $payload = self::payload($envelope, $notice, $onlyUserId);
        $body = str_replace("\n", ' · ', $payload['body']);

        foreach (self::recipients($envelope, $onlyUserId) as $user) {
            $key = 'crm-bell:'.$notice.':'.$envelope->handoff_id.':'.$user->id;

            if (self::cache()->has($key)) {
                continue;
            }

            $user->notifyNow(
                Notification::make()
                    ->title($payload['title'])
                    ->body($body)
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->viewData([
                        'handoff_id' => (string) $envelope->handoff_id,
                        'notice' => $notice,
                    ])
                    ->actions([
                        Action::make('abrir')
                            ->label('Abrir conversación')
                            ->url($payload['url']),
                    ])
                    ->toDatabase(),
            );

            self::cache()->put($key, 1, now()->addDays(max(1, (int) config('crm-inbox.idempotency_ttl_days', 7))));
        }
    }

    /**
     * @return array{title: string, body: string, tag: string, url: string}
     */
    public static function payload(CrmHandoffEnvelope $envelope, string $notice = 'new', ?int $onlyUserId = null): array
    {
        $data = is_array($envelope->payload) ? $envelope->payload : [];
        $name = trim((string) ($data['name'] ?? ''));
        $name = $name !== '' ? $name : 'Sin nombre';
        $line = trim((string) ($data['ultimo_mensaje'] ?? ''));

        if ($line === '') {
            $line = trim((string) ($envelope->last_customer_text ?? ''));
        }

        if ($line === '') {
            $line = trim((string) ($envelope->motivo ?? ''));
        }

        if ($line === '') {
            $line = 'Hay una persona esperando.';
        }

        if (mb_strlen($line) > 140) {
            $line = rtrim(mb_substr($line, 0, 137)).'…';
        }

        $panel = CrmInboxAreas::panelFor($envelope->area);
        $url = rtrim((string) config('app.url'), '/').'/'.$panel.'/atencion-whatsapp?caso='.rawurlencode((string) $envelope->handoff_id);

        $title = match ($notice) {
            'assign' => 'Te pasaron una conversación',
            'move' => 'Llegó una conversación',
            default => 'Nueva conversación',
        };
        $tag = 'crm-handoff-'.$envelope->handoff_id;

        if ($notice === 'assign' && $onlyUserId !== null) {
            $tag .= '-para-'.$onlyUserId;
        }

        if ($notice === 'move') {
            $tag .= '-'.($envelope->area ?? 'area');
        }

        return [
            'title' => $title,
            'body' => $name.' · '.CrmInboxAreas::label($envelope->area)."\n".$line,
            'tag' => $tag,
            'url' => $url,
        ];
    }

    private static function canReceive(User $user, string $department): bool
    {
        if (strtoupper((string) $user->status) !== 'ACTIVO') {
            return false;
        }

        if (! str_ends_with(strtolower((string) $user->email), '@tudrencasa.com')) {
            return false;
        }

        $departments = self::departmentsOf($user);

        return in_array($department, $departments, true) || in_array('SUPERADMIN', $departments, true);
    }

    /**
     * Lee el departamento en crudo. Hay filas viejas que no son JSON y no deben tumbar el aviso.
     *
     * @return list<string>
     */
    private static function departmentsOf(User $user): array
    {
        $raw = $user->getRawOriginal('departament');

        if (is_array($raw)) {
            $decoded = $raw;
        } else {
            $decoded = json_decode((string) $raw, true);
        }

        if (! is_array($decoded)) {
            return [];
        }

        $departments = [];

        foreach ($decoded as $item) {
            if (is_string($item) && trim($item) !== '') {
                $departments[] = strtoupper(trim($item));
            }
        }

        return $departments;
    }

    private static function cache(): \Illuminate\Contracts\Cache\Repository
    {
        $name = CrmHandoffIngress::resolveCacheStore(
            config('crm-inbox.cache_store'),
            app()->runningUnitTests() || app()->environment('local'),
        );

        return Cache::store($name);
    }
}
