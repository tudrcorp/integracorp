<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TelegramRecipients\Pages;

use App\Enums\TelegramRecipientStatus;
use App\Enums\TelegramRecipientType;
use App\Filament\Business\Resources\NotificationDeliveries\NotificationDeliveryResource;
use App\Filament\Business\Resources\TelegramRecipients\TelegramRecipientResource;
use App\Models\TelegramRecipient;
use App\Models\User;
use App\Support\SecurityAudit;
use App\Support\Telegram\TelegramHealth;
use App\Support\Telegram\TelegramLinkService;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

class ListTelegramRecipients extends ListRecords
{
    protected static string $resource = TelegramRecipientResource::class;

    public function getSubheading(): ?string
    {
        $internal = User::query()
            ->where('status', 'ACTIVO')
            ->where('email', 'like', '%@'.config('telegram.internal_email_domain'))
            ->count();

        $linked = TelegramRecipient::query()
            ->where('type', TelegramRecipientType::User->value)
            ->where('status', TelegramRecipientStatus::Active->value)
            ->count();

        return 'Personal vinculado: '.$linked.' de '.$internal.'. Cada persona se vincula desde su menú de usuario → «Conectar Telegram». Los grupos aparecen aquí al agregar el bot y no reciben nada hasta que usted los aprueba.';
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $pendingGroups = TelegramRecipient::query()->groups()->where('status', TelegramRecipientStatus::PendingApproval->value)->count();

        return [
            'todos' => Tab::make('Todos'),
            'personal' => Tab::make('Personal')
                ->icon('heroicon-o-user')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('type', TelegramRecipientType::User->value)),
            'externos' => Tab::make('Contactos externos')
                ->icon('heroicon-o-user-plus')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('type', TelegramRecipientType::External->value)),
            'grupos' => Tab::make('Grupos')
                ->icon('heroicon-o-user-group')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('type', TelegramRecipientType::Group->value)),
            'pendientes' => Tab::make('Grupos por aprobar')
                ->icon('heroicon-o-clock')
                ->badge($pendingGroups > 0 ? $pendingGroups : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('type', TelegramRecipientType::Group->value)
                    ->where('status', TelegramRecipientStatus::PendingApproval->value)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('botStatus')
                ->label('Estado del bot')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->modalHeading('Estado del bot de Telegram')
                ->modalWidth(Width::Large)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalContent(fn (): View => view('filament.business.telegram.health-modal', [
                    'health' => TelegramHealth::check(fresh: true),
                    'queue' => (string) config('telegram.queue'),
                ])),
            Action::make('deliveries')
                ->label('Bitácora de envíos')
                ->icon('heroicon-o-queue-list')
                ->color('gray')
                ->url(fn (): string => NotificationDeliveryResource::getUrl()),
            Action::make('addExternal')
                ->label('Agregar contacto externo')
                ->icon('heroicon-o-user-plus')
                ->color('primary')
                ->modalHeading('Agregar contacto externo')
                ->modalDescription('Para alguien que no tiene correo @'.config('telegram.internal_email_domain').' y necesita recibir avisos internos. Al guardar se genera su enlace de vinculación.')
                ->schema(self::externalContactSchema())
                ->modalSubmitActionLabel('Guardar y generar enlace')
                ->successNotification(null)
                ->action(function (array $data): void {
                    $actor = (string) (Auth::user()?->name ?? 'SUPERADMIN');

                    $recipient = TelegramRecipient::query()->create([
                        'type' => TelegramRecipientType::External,
                        'status' => TelegramRecipientStatus::PendingLink,
                        'name' => trim((string) $data['name']),
                        'email' => filled($data['email'] ?? null) ? mb_strtolower(trim((string) $data['email'])) : null,
                        'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
                        'reason' => trim((string) $data['reason']),
                        'expires_at' => $data['expires_at'] ?? null,
                        'created_by' => $actor,
                    ]);

                    try {
                        SecurityAudit::log('AUDIT_TELEGRAM_EXTERNAL_CREATED', 'business.telegram', ['telegram_recipient_id' => $recipient->getKey(), 'name' => $recipient->name, 'reason' => $recipient->reason]);
                    } catch (Throwable) {
                    }

                    try {
                        $link = TelegramLinkService::issueForExternal($recipient, $actor);
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()->title('Contacto guardado, pero sin enlace')->body($exception->getMessage())->warning()->send();

                        return;
                    }

                    $this->replaceMountedAction('showTelegramLink', [
                        'name' => $recipient->name,
                        'url' => $link['url'],
                        'code' => $link['code'],
                        'expires_at' => $link['expires_at']->timezone(config('app.timezone'))->format('h:i A'),
                    ]);
                }),
        ];
    }

    /**
     * Modal que muestra el enlace recién generado con su QR. Se abre con
     * replaceMountedAction desde «Agregar contacto externo» y «Generar enlace».
     */
    public function showTelegramLinkAction(): Action
    {
        return Action::make('showTelegramLink')
            ->modalHeading(fn (array $arguments): string => 'Enlace para '.($arguments['name'] ?? 'el contacto'))
            ->modalWidth(Width::Medium)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Listo')
            ->modalContent(fn (array $arguments): View => view('filament.business.telegram.link-modal', [
                'url' => (string) ($arguments['url'] ?? ''),
                'code' => (string) ($arguments['code'] ?? ''),
                'bot' => (string) config('telegram.bot_username'),
                'expiresAt' => (string) ($arguments['expires_at'] ?? ''),
                'qr' => self::qrSvg((string) ($arguments['url'] ?? '')),
            ]));
    }

    /**
     * @return array<int, mixed>
     */
    public static function externalContactSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('Nombre completo')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label('Correo')
                ->email()
                ->maxLength(255)
                ->helperText('Con el correo o el teléfono puede agregarlo a un aviso del Centro de notificaciones.'),
            TextInput::make('phone')
                ->label('Teléfono')
                ->tel()
                ->maxLength(30)
                ->placeholder('04141234567')
                ->helperText('Se usa como respaldo por WhatsApp si Telegram falla.'),
            Textarea::make('reason')
                ->label('Motivo')
                ->required()
                ->maxLength(500)
                ->rows(2)
                ->placeholder('Ej.: auditor externo que debe recibir las alertas de seguridad'),
            DateTimePicker::make('expires_at')
                ->label('Vence el (opcional)')
                ->native(false)
                ->minDate(now())
                ->helperText('Al vencer deja de recibir avisos sin que nadie tenga que acordarse de quitarlo.'),
        ];
    }

    private static function qrSvg(string $url): string
    {
        if ($url === '') {
            return '';
        }

        try {
            return (string) QrCode::format('svg')->size(260)->margin(1)->errorCorrection('M')->generate($url);
        } catch (Throwable) {
            return '';
        }
    }
}
