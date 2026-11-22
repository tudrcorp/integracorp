<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Enums\OutboundMessageStatus;
use App\Enums\SystemNotificationKey;
use App\Enums\TelegramDeliveryMode;
use App\Filament\Business\Pages\Schemas\SystemNotificationRecipientSettingsForm;
use App\Models\NotificationCatalogEntry;
use App\Models\NotificationChannelSetting;
use App\Models\OutboundMessage;
use App\Models\SystemNotificationRecipientSetting;
use App\Support\Filament\FilamentIosButton;
use App\Support\InternalNotifications\InternalNotification;
use App\Support\InternalNotifications\InternalNotificationDispatcher;
use App\Support\NotificationCenter\InternalDirectory;
use App\Support\NotificationCenter\NotificationCatalog;
use App\Support\NotificationCenter\NotificationCenterAccess;
use App\Support\SecurityAudit;
use App\Support\SystemNotificationRecipients;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Throwable;
use UnitEnum;

class ManageCompanyAssociateNotifications extends Page
{
    protected static ?string $navigationLabel = 'Centro de notificaciones';

    protected static ?string $title = 'Centro de notificaciones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

    protected static ?int $navigationSort = 16;

    protected string $view = 'filament.business.pages.manage-company-associate-notifications';

    public string $activeNotificationKey = 'company_associate_registration';

    /**
     * Pestaña visible: catalog (todas las notificaciones), log (bitácora), channels
     * (interruptores generales) o internal (destinatarios de los avisos internos).
     */
    #[Url(as: 'vista', except: 'catalog')]
    public string $section = 'catalog';

    /** Tipo con el que se abre filtrada la bitácora (desde «Ver envíos» del catálogo). */
    public ?string $logCatalogKey = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        if (! in_array($this->section, self::SECTIONS, true)) {
            $this->section = 'catalog';
        }

        /** La primera visita tras la migración carga el catálogo de tipos (aditivo, una vez). */
        NotificationCatalog::snapshot();

        $this->fillForm();
    }

    public const SECTIONS = ['catalog', 'log', 'channels', 'internal'];

    private const OVERVIEW_CACHE_KEY = 'notification-center:overview';

    public function showSection(string $section): void
    {
        if (in_array($section, self::SECTIONS, true)) {
            $this->section = $section;
        }

        if ($section !== 'log') {
            $this->logCatalogKey = null;
        }
    }

    #[On('notification-center-open-internal')]
    public function openInternalNotice(string $key): void
    {
        $this->section = 'internal';
        $this->selectNotificationKey($key);
    }

    /** Un hijo (catálogo o canales) cambió algo: se recalculan las insignias de las pestañas. */
    #[On('notification-center-changed')]
    public function refreshOverview(): void
    {
        Cache::forget(self::OVERVIEW_CACHE_KEY);
        unset($this->overview);
    }

    #[On('notification-center-open-log')]
    public function openLog(?string $catalogKey = null): void
    {
        $this->logCatalogKey = $catalogKey;
        $this->section = 'log';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString(
            '<span class="text-sm text-slate-500 dark:text-slate-400">Todo lo que el sistema envía por WhatsApp, correo y Telegram, a quién y cómo: actívelo, páuselo o cámbielo de canal desde aquí.</span>'
        );
    }

    public function getActiveNotificationKeyEnum(): SystemNotificationKey
    {
        return SystemNotificationKey::from($this->activeNotificationKey);
    }

    #[Computed]
    public function settingsRecord(): SystemNotificationRecipientSetting
    {
        return SystemNotificationRecipients::setting($this->getActiveNotificationKeyEnum());
    }

    #[Computed]
    public function configuredEmailCount(): int
    {
        return count($this->data['notification_emails'] ?? []);
    }

    #[Computed]
    public function configuredPhoneCount(): int
    {
        return count($this->data['notification_phones'] ?? []);
    }

    #[Computed]
    public function hasRecipients(): bool
    {
        return $this->configuredEmailCount > 0 || $this->configuredPhoneCount > 0;
    }

    /**
     * Estado de canal de cada aviso para el selector: agrupado por área, con su
     * estado (activa / pausada) y por dónde sale hoy (Telegram, listo para
     * Telegram o solo WhatsApp). Una sola consulta para los 26 avisos.
     *
     * @return array{
     *     areas: array<string, array{label: string, items: list<array{key: string, label: string, active: bool, channel: string, mode: string, stage: ?string}>}>,
     *     counts: array{telegram: int, ready: int, whatsapp: int, paused: int, total: int}
     * }
     */
    #[Computed]
    public function keyCatalog(): array
    {
        $settings = SystemNotificationRecipientSetting::query()
            ->get(['notification_key', 'is_active', 'telegram_mode'])
            ->keyBy(fn (SystemNotificationRecipientSetting $setting): string => $setting->notification_key->value);

        $areas = [];
        $counts = ['telegram' => 0, 'ready' => 0, 'whatsapp' => 0, 'paused' => 0, 'total' => 0];

        foreach (SystemNotificationKey::AREAS as $area => $label) {
            $areas[$area] = ['label' => $label, 'items' => []];
        }

        foreach (SystemNotificationKey::managed() as $key) {
            $setting = $settings->get($key->value);
            $active = $setting === null || $setting->isActive();
            $mode = $setting?->telegramMode() ?? TelegramDeliveryMode::Off;

            $channel = match (true) {
                ! $key->supportsTelegram() => 'whatsapp',
                $mode !== TelegramDeliveryMode::Off => 'telegram',
                default => 'ready',
            };

            $areas[$key->area()]['items'][] = [
                'key' => $key->value,
                'label' => $key->label(),
                'active' => $active,
                'channel' => $channel,
                'mode' => match ($mode) {
                    TelegramDeliveryMode::Person => 'Persona',
                    TelegramDeliveryMode::Group => 'Grupo',
                    TelegramDeliveryMode::Both => 'Ambos',
                    TelegramDeliveryMode::Off => '',
                },
                'stage' => $key->telegramMigrationStage(),
            ];

            $counts[$channel]++;
            $counts['total']++;

            if (! $active) {
                $counts['paused']++;
            }
        }

        return ['areas' => array_filter($areas, fn (array $area): bool => $area['items'] !== []), 'counts' => $counts];
    }

    /**
     * Insignias de las pestañas: tres consultas pequeñas sobre índices, compartidas 30 s
     * entre analistas para que cambiar de pestaña no cueste nada.
     *
     * @return array{catalog_total: int, catalog_off: int, failed_today: int, channels_off: int}
     */
    #[Computed]
    public function overview(): array
    {
        $empty = ['catalog_total' => 0, 'catalog_off' => 0, 'failed_today' => 0, 'channels_off' => 0];

        if (! NotificationCatalog::tablesExist()) {
            return $empty;
        }

        try {
            return Cache::remember(self::OVERVIEW_CACHE_KEY, 30, function (): array {
                $catalog = NotificationCatalogEntry::query()
                    ->toBase()
                    ->selectRaw('count(*) as total, sum(case when is_active = 0 or whatsapp_enabled = 0 or mail_enabled = 0 then 1 else 0 end) as off_count')
                    ->first();

                return [
                    'catalog_total' => (int) ($catalog->total ?? 0),
                    'catalog_off' => (int) ($catalog->off_count ?? 0),
                    'failed_today' => OutboundMessage::query()
                        ->where('status', OutboundMessageStatus::Failed->value)
                        ->where('created_at', '>=', now()->startOfDay())
                        ->count(),
                    'channels_off' => NotificationChannelSetting::query()->where('is_enabled', false)->count(),
                ];
            });
        } catch (Throwable $exception) {
            report($exception);

            return $empty;
        }
    }

    public function selectNotificationKey(string $key): void
    {
        $notificationKey = SystemNotificationKey::tryFrom($key);

        if ($notificationKey === null) {
            return;
        }

        $this->activeNotificationKey = $notificationKey->value;
        unset($this->settingsRecord);
        $this->fillForm();
    }

    protected function fillForm(): void
    {
        $settings = $this->settingsRecord;

        $this->form->fill([
            'is_active' => $settings->isActive(),
            'notification_emails' => collect($settings->emails())
                ->map(fn (string $email): array => ['email' => $email, 'copy_mode' => $settings->emailMode($email)])
                ->values()
                ->all(),
            'notification_phones' => collect($settings->phones())
                ->map(fn (string $phone): array => ['phone' => $phone])
                ->values()
                ->all(),
            'telegram_mode' => $settings->telegramMode()->value,
            'telegram_group_ids' => $settings->telegramGroupIds(),
            'telegram_recipient_ids' => $settings->telegramRecipientIds(),
        ]);

        /** El navegador toma una nueva foto del formulario para avisar de cambios sin guardar. */
        $this->dispatch('can-form-synced');
    }

    public function form(Schema $schema): Schema
    {
        return SystemNotificationRecipientSettingsForm::configure($schema);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Form
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('manage-system-notification-recipients-form')
            ->livewireSubmitHandler('save')
            ->footer([
                $this->getFormActionsContentComponent(),
            ]);
    }

    public function getFormActionsContentComponent(): Actions
    {
        return Actions::make($this->getFormActions())
            ->fullWidth(false)
            ->sticky()
            ->extraAttributes([
                'class' => 'can-settings-actions',
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar configuración')
                ->icon('heroicon-o-check')
                ->submit('save')
                ->color('success')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                ]),
            Action::make('sendTelegramTest')
                ->label('Enviar prueba')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn (): bool => $this->getActiveNotificationKeyEnum() === SystemNotificationKey::TelegramTest)
                ->requiresConfirmation()
                ->modalHeading('Enviar «Prueba de notificación a Telegram»')
                ->modalDescription('Se envía con la configuración GUARDADA de esta pestaña. Si cambió algo, guárdelo antes. Las personas vinculadas la reciben por Telegram; las demás, por WhatsApp o correo.')
                ->modalSubmitActionLabel('Enviar prueba')
                ->action(fn () => $this->sendTelegramTest())
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('info'),
                ]),
        ];
    }

    public function save(): void
    {
        abort_unless(self::userCanAccessPage(), 403);

        $data = $this->form->getState();
        $notificationKey = $this->getActiveNotificationKeyEnum();
        $isActive = (bool) ($data['is_active'] ?? true);

        $emails = collect($data['notification_emails'] ?? [])
            ->pluck('email')
            ->filter(fn (mixed $email): bool => filled($email))
            ->map(fn (mixed $email): string => strtolower(trim((string) $email)))
            ->unique()
            ->values()
            ->all();

        $emailModes = $notificationKey->supportsEmailCopyModes()
            ? self::emailModesFromState($data['notification_emails'] ?? [], $emails)
            : null;

        $phones = collect($data['notification_phones'] ?? [])
            ->pluck('phone')
            ->filter(fn (mixed $phone): bool => filled($phone))
            ->map(fn (mixed $phone): string => trim((string) $phone))
            ->unique()
            ->values()
            ->all();

        $telegram = self::telegramStateFromForm($notificationKey, $data);

        $settings = SystemNotificationRecipientSetting::for($notificationKey);
        $settings->update([
            'is_active' => $isActive,
            'notification_emails' => $emails,
            'notification_email_modes' => $emailModes,
            'notification_phones' => $phones,
            ...$telegram,
            'updated_by' => Auth::user()?->name,
        ]);

        SecurityAudit::log('AUDIT_BUSINESS_SYSTEM_NOTIFICATION_RECIPIENTS_UPDATED', 'business.system-notifications.settings', [
            'notification_key' => $notificationKey->value,
            'is_active' => $isActive,
            'emails_count' => count($emails),
            'bcc_emails_count' => is_array($emailModes) ? count(array_filter($emailModes, fn (string $mode): bool => $mode === SystemNotificationRecipientSetting::EMAIL_MODE_BCC)) : 0,
            'phones_count' => count($phones),
            'telegram_mode' => $telegram['telegram_mode'] ?? null,
            'telegram_groups_count' => count($telegram['telegram_group_ids'] ?? []),
            'telegram_people_count' => count($telegram['telegram_recipient_ids'] ?? []),
            'updated_by' => Auth::user()?->name,
        ]);

        /** El catálogo del Centro refleja el mismo estado, y el directorio interno ve los teléfonos nuevos. */
        NotificationCatalogEntry::query()->where('key', 'internal:'.$notificationKey->value)->update(['is_active' => $isActive, 'updated_by' => Auth::user()?->name]);
        NotificationCatalog::flush();
        InternalDirectory::flush();

        Cache::forget(self::OVERVIEW_CACHE_KEY);
        unset($this->settingsRecord, $this->keyCatalog, $this->overview);

        $this->fillForm();

        $statusLabel = $isActive ? 'activa' : 'inactiva';

        Notification::make()
            ->title('Configuración guardada')
            ->body("La tarea quedó {$statusLabel}. ".$notificationKey->savedRecipientsMessage($emails, $phones))
            ->success()
            ->send();
    }

    /**
     * Modo de copia por correo guardado. Si el mismo correo aparece dos veces, gana CCO (el más
     * reservado): nunca se expone un correo que el analista marcó como oculto.
     *
     * @param  array<int|string, mixed>  $items
     * @param  list<string>  $emails
     * @return array<string, string>
     */
    private static function emailModesFromState(array $items, array $emails): array
    {
        $modes = array_fill_keys($emails, SystemNotificationRecipientSetting::EMAIL_MODE_CC);

        foreach ($items as $item) {
            $email = strtolower(trim((string) (is_array($item) ? ($item['email'] ?? '') : '')));

            if ($email === '' || ! array_key_exists($email, $modes)) {
                continue;
            }

            if (($item['copy_mode'] ?? null) === SystemNotificationRecipientSetting::EMAIL_MODE_BCC) {
                $modes[$email] = SystemNotificationRecipientSetting::EMAIL_MODE_BCC;
            }
        }

        return $modes;
    }

    /**
     * Solo los avisos que ya usan el repartidor interno guardan destino de Telegram;
     * en los demás no se toca (siguen saliendo como siempre). Un OTP nunca guarda grupos.
     *
     * @param  array<string, mixed>  $data
     * @return array{telegram_mode?: string, telegram_group_ids?: list<int>, telegram_recipient_ids?: list<int>}
     */
    private static function telegramStateFromForm(SystemNotificationKey $key, array $data): array
    {
        if (! $key->supportsTelegram()) {
            return [];
        }

        $mode = TelegramDeliveryMode::tryFrom((string) ($data['telegram_mode'] ?? '')) ?? TelegramDeliveryMode::Off;

        if ($mode->includesGroups() && ! $key->allowsTelegramGroups()) {
            $mode = TelegramDeliveryMode::Person;
        }

        $groupIds = $mode->includesGroups()
            ? array_values(array_unique(array_filter(array_map('intval', (array) ($data['telegram_group_ids'] ?? [])), static fn (int $id): bool => $id > 0)))
            : [];

        $recipientIds = $mode->includesPeople() && ! $key->isPersonTargeted()
            ? array_values(array_unique(array_filter(array_map('intval', (array) ($data['telegram_recipient_ids'] ?? [])), static fn (int $id): bool => $id > 0)))
            : [];

        return ['telegram_mode' => $mode->value, 'telegram_group_ids' => $groupIds, 'telegram_recipient_ids' => $recipientIds];
    }

    public function sendTelegramTest(): void
    {
        if (! self::userCanAccessPage()) {
            abort(403);
        }

        $actor = (string) (Auth::user()?->name ?? 'SUPERADMIN');

        try {
            $summary = InternalNotificationDispatcher::dispatch(new InternalNotification(
                SystemNotificationKey::TelegramTest,
                'Prueba de notificación a Telegram',
                'Si lee este mensaje, el canal funciona.'."\n\n".'Enviado por '.$actor.' el '.now()->timezone(config('app.timezone'))->format('d/m/Y H:i').'.',
                false,
                $actor,
            ));
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('No se pudo enviar la prueba')
                ->body('Ocurrió un error al registrar los envíos. Revise Negocios → Colas y errores. Nada quedó a medias.')
                ->danger()
                ->send();

            return;
        }

        SecurityAudit::log('AUDIT_TELEGRAM_TEST_SENT', 'business.system-notifications.telegram-test', [
            'mode' => $summary['mode']->value,
            'telegram' => $summary['telegram'],
            'groups' => $summary['groups'],
            'whatsapp' => $summary['whatsapp'],
            'mail' => $summary['mail'],
        ]);

        if ($summary['paused']) {
            Notification::make()
                ->title('La prueba está pausada')
                ->body('Active «Tarea activa» en esta pestaña, guarde y vuelva a intentarlo.')
                ->warning()
                ->send();

            return;
        }

        $total = $summary['telegram'] + $summary['groups'] + $summary['whatsapp'] + $summary['mail'];

        if ($total === 0) {
            Notification::make()
                ->title('No hay a quién enviar la prueba')
                ->body('Agregue correos o teléfonos de personas, o elija grupos de Telegram, y guarde.'.($summary['skipped'] > 0 ? ' Hay grupos elegidos que no están activos.' : ''))
                ->warning()
                ->send();

            return;
        }

        $parts = array_filter([
            $summary['telegram'] > 0 ? $summary['telegram'].' por Telegram a personas' : null,
            $summary['groups'] > 0 ? $summary['groups'].' a grupos de Telegram' : null,
            $summary['whatsapp'] > 0 ? $summary['whatsapp'].' por WhatsApp' : null,
            $summary['mail'] > 0 ? $summary['mail'].' por correo' : null,
        ]);

        Notification::make()
            ->title('Prueba en camino')
            ->body(implode(' · ', $parts).'. Llega en segundos si el worker de la cola «'.config('telegram.queue').'» está encendido. Revise el resultado en CONFIGURACIÓN → Telegram.')
            ->success()
            ->send();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::userCanAccessPage();
    }

    public static function canAccess(): bool
    {
        return self::userCanAccessPage();
    }

    private static function userCanAccessPage(): bool
    {
        return NotificationCenterAccess::canAccess();
    }
}
