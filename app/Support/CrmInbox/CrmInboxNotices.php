<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Muestra en el panel el aviso que quedó guardado, aunque el navegador no lo haya mostrado.
 */
final class CrmInboxNotices
{
    /**
     * @var list<string>
     */
    private const PANELS = ['business', 'operations', 'administration'];

    /**
     * @var list<string>
     */
    private const TITLES = [
        'Nueva conversación',
        'Te pasaron una conversación',
        'Llegó una conversación',
    ];

    public static function flashUnread(): void
    {
        $panel = Filament::getCurrentPanel();

        if ($panel === null || ! in_array($panel->getId(), self::PANELS, true)) {
            return;
        }

        $user = Auth::user();

        if (! $user instanceof User || ! Schema::hasTable('notifications')) {
            return;
        }

        $pending = $user->unreadNotifications()->latest()->limit(5)->get();

        foreach ($pending as $notification) {
            $data = is_array($notification->data) ? $notification->data : [];
            $title = (string) ($data['title'] ?? '');
            $viewData = is_array($data['viewData'] ?? null) ? $data['viewData'] : [];
            $handoffId = (string) ($viewData['handoff_id'] ?? '');

            if ($handoffId === '' || ! in_array($title, self::TITLES, true)) {
                continue;
            }

            $url = null;
            $actions = is_array($data['actions'] ?? null) ? $data['actions'] : [];
            $first = $actions[0] ?? null;

            if (is_array($first) && is_string($first['url'] ?? null)) {
                $url = $first['url'];
            }

            self::toast($title, (string) ($data['body'] ?? ''), $handoffId, $url);
        }
    }

    public static function toast(string $title, string $body, string $handoffId, ?string $url = null): void
    {
        if (! in_array($title, self::TITLES, true) || ! preg_match('/^\d{1,18}$/', $handoffId)) {
            return;
        }

        $key = 'crm-notice-shown.'.$handoffId.'.'.$title;

        if (session()->get($key)) {
            return;
        }

        session()->put($key, 1);

        $notice = Notification::make()
            ->title($title)
            ->body($body)
            ->persistent();

        if (is_string($url) && $url !== '') {
            $notice->actions([
                Action::make('abrir')
                    ->label('Abrir conversación')
                    ->url($url),
            ]);
        }

        $notice->send();
    }
}
