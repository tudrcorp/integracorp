<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Enums\SystemNotificationKey;
use App\Filament\Business\Pages\Schemas\SystemNotificationRecipientSettingsForm;
use App\Models\SystemNotificationRecipientSetting;
use App\Support\Filament\FilamentIosButton;
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
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;
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
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->fillForm();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString(
            '<span class="text-sm text-slate-500 dark:text-slate-400">Gestione por tipo de alerta los destinatarios y active o pause cada tarea programada desde aquí.</span>'
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
                ->map(fn (string $email): array => ['email' => $email])
                ->values()
                ->all(),
            'notification_phones' => collect($settings->phones())
                ->map(fn (string $phone): array => ['phone' => $phone])
                ->values()
                ->all(),
        ]);
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
        ];
    }

    public function save(): void
    {
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

        $phones = collect($data['notification_phones'] ?? [])
            ->pluck('phone')
            ->filter(fn (mixed $phone): bool => filled($phone))
            ->map(fn (mixed $phone): string => trim((string) $phone))
            ->unique()
            ->values()
            ->all();

        $settings = SystemNotificationRecipientSetting::for($notificationKey);
        $settings->update([
            'is_active' => $isActive,
            'notification_emails' => $emails,
            'notification_phones' => $phones,
            'updated_by' => Auth::user()?->name,
        ]);

        SecurityAudit::log('AUDIT_BUSINESS_SYSTEM_NOTIFICATION_RECIPIENTS_UPDATED', 'business.system-notifications.settings', [
            'notification_key' => $notificationKey->value,
            'is_active' => $isActive,
            'emails_count' => count($emails),
            'phones_count' => count($phones),
            'updated_by' => Auth::user()?->name,
        ]);

        unset($this->settingsRecord);

        $this->fillForm();

        $statusLabel = $isActive ? 'activa' : 'inactiva';

        Notification::make()
            ->title('Configuración guardada')
            ->body("La tarea quedó {$statusLabel}. ".$notificationKey->savedRecipientsMessage($emails, $phones))
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
        $departments = (array) (Auth::user()?->departament ?? []);

        return in_array('SUPERADMIN', $departments, true);
    }
}
