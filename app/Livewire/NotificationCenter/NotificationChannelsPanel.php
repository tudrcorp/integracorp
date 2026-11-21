<?php

declare(strict_types=1);

namespace App\Livewire\NotificationCenter;

use App\Enums\OutboundChannel;
use App\Enums\OutboundMessageStatus;
use App\Http\Controllers\UtilsController;
use App\Models\NotificationCatalogEntry;
use App\Models\NotificationChannelSetting;
use App\Models\OutboundMessage;
use App\Support\NotificationCenter\NotificationCenterAccess;
use App\Support\NotificationCenter\NotificationCenterSettings;
use App\Support\Telegram\TelegramBotClient;
use App\Support\Telegram\TelegramHealth;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Throwable;

/**
 * Centro de notificaciones → Canales: interruptor general de WhatsApp, correo y
 * Telegram, su estado de conexión y los números de las últimas 24 horas.
 */
class NotificationChannelsPanel extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** @var array<string, array{ok: bool, message: string, at: string}> */
    public array $health = [];

    public function mount(): void
    {
        abort_unless(NotificationCenterAccess::canAccess(), 403);
    }

    /**
     * @return array<string, array{channel: OutboundChannel, enabled: bool, updated_by: ?string, updated_at: ?string, sent: int, failed: int, blocked: int, internal: int, external: int}>
     */
    #[Computed]
    public function channels(): array
    {
        $settings = NotificationChannelSetting::query()->get()->keyBy('channel');
        $stats = OutboundMessage::query()
            ->where('created_at', '>=', now()->subDay())
            ->selectRaw('channel, status, audience, count(*) as total')
            ->groupBy('channel', 'status', 'audience')
            ->toBase()
            ->get();

        $channels = [];

        foreach (OutboundChannel::cases() as $channel) {
            $setting = $settings->get($channel->value);
            $rows = $stats->where('channel', $channel->value);
            $delivered = $rows->where('status', '!=', OutboundMessageStatus::Blocked->value);

            $channels[$channel->value] = [
                'channel' => $channel,
                'enabled' => $setting === null ? true : (bool) $setting->is_enabled,
                'updated_by' => $setting?->updated_by,
                'updated_at' => $setting?->updated_at?->timezone(config('app.timezone'))->format('d/m/Y h:i a'),
                'sent' => (int) $rows->where('status', OutboundMessageStatus::Sent->value)->sum('total'),
                'failed' => (int) $rows->where('status', OutboundMessageStatus::Failed->value)->sum('total'),
                'blocked' => (int) $rows->where('status', OutboundMessageStatus::Blocked->value)->sum('total'),
                'internal' => (int) $delivered->where('audience', 'internal')->sum('total'),
                'external' => (int) $delivered->where('audience', 'external')->sum('total'),
            ];
        }

        return $channels;
    }

    #[Computed]
    public function criticalCount(): int
    {
        return NotificationCatalogEntry::query()->where('is_critical', true)->count();
    }

    public function canManage(): bool
    {
        return NotificationCenterAccess::canManageChannels();
    }

    public function toggleChannelAction(): Action
    {
        return Action::make('toggleChannel')
            ->label(fn (array $arguments): string => ($this->channels[$arguments['channel'] ?? 'whatsapp']['enabled'] ?? true) ? 'Apagar' : 'Encender')
            ->icon(fn (array $arguments): string => ($this->channels[$arguments['channel'] ?? 'whatsapp']['enabled'] ?? true) ? 'heroicon-m-power' : 'heroicon-m-play')
            ->size('sm')
            ->requiresConfirmation()
            ->visible(fn (): bool => $this->canManage())
            ->modalHeading(function (array $arguments): string {
                $channel = OutboundChannel::from($arguments['channel']);

                return ($this->channels[$channel->value]['enabled'] ? 'Apagar ' : 'Encender ').$channel->label().' en todo el sistema';
            })
            ->modalDescription(function (array $arguments): string {
                $channel = OutboundChannel::from($arguments['channel']);

                if (! $this->channels[$channel->value]['enabled']) {
                    return 'Todo lo que estaba bloqueado por este interruptor vuelve a salir desde ahora. Lo que se bloqueó mientras estuvo apagado no se reenvía.';
                }

                return match ($channel) {
                    OutboundChannel::Telegram => 'Los avisos internos dejan de salir por Telegram y pasan a su respaldo (WhatsApp o correo). Escriba APAGAR para confirmar.',
                    default => 'Ningún mensaje saldrá por '.$channel->label().' (internos ni externos), salvo los '.$this->criticalCount.' tipos críticos: claves OTP, alertas de seguridad y de colas, y acceso al sistema. Cada intento queda en la bitácora como «Bloqueado» y no se reenvía después. Escriba APAGAR para confirmar.',
                };
            })
            ->schema(fn (array $arguments): array => $this->channels[$arguments['channel']]['enabled'] ? [
                TextInput::make('confirmation')->label('Confirmación')->placeholder('APAGAR')->required()->in(['APAGAR'])
                    ->validationMessages(['in' => 'Escriba APAGAR en mayúsculas.']),
            ] : [])
            ->color(fn (array $arguments): string => ($this->channels[$arguments['channel'] ?? 'whatsapp']['enabled'] ?? true) ? 'danger' : 'success')
            ->modalSubmitActionLabel(fn (array $arguments): string => ($this->channels[$arguments['channel'] ?? 'whatsapp']['enabled'] ?? true) ? 'Apagar' : 'Encender')
            ->action(function (array $arguments): void {
                $channel = OutboundChannel::from($arguments['channel']);
                $enable = ! $this->channels[$channel->value]['enabled'];

                try {
                    NotificationCenterSettings::setChannel($channel, $enable);
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                unset($this->channels);

                Notification::make()
                    ->title($channel->label().($enable ? ' encendido' : ' apagado').' en todo el sistema')
                    ->body($enable ? 'Los mensajes vuelven a salir.' : 'Los workers lo aplican en menos de 30 segundos.')
                    ->color($enable ? 'success' : 'warning')
                    ->send();
            });
    }

    public function checkHealth(string $channel): void
    {
        abort_unless(NotificationCenterAccess::canAccess(), 403);

        $at = now()->timezone(config('app.timezone'))->format('h:i a');

        try {
            $this->health[$channel] = match ($channel) {
                'whatsapp' => UtilsController::apiWhatsAppStatus()
                    ? ['ok' => true, 'message' => 'UltraMsg responde y la instancia está conectada.', 'at' => $at]
                    : ['ok' => false, 'message' => 'UltraMsg no respondió o la instancia no está conectada. Revise el teléfono vinculado.', 'at' => $at],
                'telegram' => self::telegramHealth($at),
                'mail' => ['ok' => filled(config('mail.default')) && filled(config('mail.from.address')), 'message' => 'Servidor: '.config('mail.default').' · Remitente: '.config('mail.from.address').'. La entrega real se confirma en la bitácora.', 'at' => $at],
                default => ['ok' => false, 'message' => 'Canal desconocido.', 'at' => $at],
            };
        } catch (Throwable $exception) {
            $this->health[$channel] = ['ok' => false, 'message' => 'No se pudo comprobar: '.mb_substr($exception->getMessage(), 0, 160), 'at' => $at];
        }
    }

    public function render(): View
    {
        return view('livewire.notification-center.channels-panel');
    }

    /**
     * @return array{ok: bool, message: string, at: string}
     */
    private static function telegramHealth(string $at): array
    {
        if (! TelegramBotClient::make()->isConfigured()) {
            return ['ok' => false, 'message' => 'El bot no tiene token en este servidor.', 'at' => $at];
        }

        $health = TelegramHealth::check(true);

        return [
            'ok' => (bool) ($health['ok'] ?? false),
            'message' => ($health['ok'] ?? false)
                ? 'Bot @'.($health['bot_username'] ?? '').' respondiendo'.((int) ($health['pending_update_count'] ?? 0) > 0 ? ' · '.$health['pending_update_count'].' mensajes en espera.' : '.')
                : 'El bot no responde: '.($health['error'] ?? 'sin detalle'),
            'at' => $at,
        ];
    }
}
