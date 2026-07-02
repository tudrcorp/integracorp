<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Filament\Business\Pages\Schemas\CompanyAssociateNotificationSettingsForm;
use App\Models\CompanyAssociateNotificationSetting;
use App\Support\Filament\FilamentIosButton;
use App\Support\SecurityAudit;
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
    protected static ?string $navigationLabel = 'Notificaciones de asociados';

    protected static ?string $title = 'Notificaciones de asociados';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

    protected static ?int $navigationSort = 16;

    protected string $view = 'filament.business.pages.manage-company-associate-notifications';

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
            '<span class="text-sm text-slate-500 dark:text-slate-400">Configure quién recibe las alertas cuando un asociado se registra desde el enlace público.</span>'
        );
    }

    #[Computed]
    public function settingsRecord(): CompanyAssociateNotificationSetting
    {
        return CompanyAssociateNotificationSetting::instance();
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

    protected function fillForm(): void
    {
        $settings = $this->settingsRecord;

        $this->form->fill([
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
        return CompanyAssociateNotificationSettingsForm::configure($schema);
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
            ->id('manage-company-associate-notifications-form')
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
                ->label('Guardar destinatarios')
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

        $settings = CompanyAssociateNotificationSetting::instance();
        $settings->update([
            'notification_emails' => $emails,
            'notification_phones' => $phones,
            'updated_by' => Auth::user()?->name,
        ]);

        SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_NOTIFICATION_SETTINGS_UPDATED', 'business.company-associate-notifications.settings', [
            'emails_count' => count($emails),
            'phones_count' => count($phones),
            'updated_by' => Auth::user()?->name,
        ]);

        unset($this->settingsRecord);

        $this->fillForm();

        Notification::make()
            ->title('Destinatarios guardados')
            ->body(match (true) {
                $emails === [] && $phones === [] => 'No hay destinatarios activos. Las notificaciones quedarán en pausa hasta que agregue contactos.',
                default => 'Se notificará por correo y WhatsApp a los contactos configurados cuando se registre un asociado.',
            })
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
