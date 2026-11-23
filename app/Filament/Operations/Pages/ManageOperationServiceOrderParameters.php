<?php

declare(strict_types=1);

namespace App\Filament\Operations\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\OperationServiceOrderSetting;
use App\Support\Filament\FilamentIosButton;
use App\Support\Operations\OperationServiceOrderValidity;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnitEnum;

/**
 * Decide si las órdenes de servicio caducan y en cuántos días. Reemplaza la
 * regla fija de 10 días que vivía en OperationServiceOrderValidity.
 */
class ManageOperationServiceOrderParameters extends Page
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $navigationLabel = 'Parámetros de órdenes';

    protected static ?string $title = 'Parámetros de órdenes de servicio';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'COORDINACIÓN DE SERVICIOS';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'parametros-ordenes-servicio';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var array<int, int>
     */
    private array $overdueCountByDays = [];

    public function mount(): void
    {
        $this->fillFromSettings(OperationServiceOrderSetting::current());
    }

    public function getSubheading(): ?string
    {
        return 'Defina si las órdenes de servicio abiertas caducan solas y cuántos días tienen de vigencia desde su aprobación.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Caducidad de órdenes de servicio')
                    ->description('Una orden caducada pasa a CADUCADA y ya no puede finalizarse. Las órdenes finalizadas o canceladas nunca caducan.')
                    ->icon(Heroicon::OutlinedClock)
                    ->schema([
                        Toggle::make('expiration_enabled')
                            ->label('Las órdenes caducan')
                            ->helperText(fn (Get $get): string => $get('expiration_enabled')
                                ? 'Activado: cada día a las 7:30 se caducan las órdenes abiertas que superaron su vigencia.'
                                : 'Desactivado: ninguna orden abierta caduca ni muestra cuenta regresiva. Las que ya están CADUCADAS se mantienen así.')
                            ->onColor('success')
                            ->live(),
                        TextInput::make('validity_days')
                            ->label('Días de vigencia')
                            ->helperText('Se cuentan desde la aprobación de la orden (o desde su creación si no tiene fecha de aprobación). La orden vence al terminar el último día.')
                            ->numeric()
                            ->integer()
                            ->minValue(OperationServiceOrderValidity::MIN_VALIDITY_DAYS)
                            ->maxValue(OperationServiceOrderValidity::MAX_VALIDITY_DAYS)
                            ->suffix('días')
                            ->prefixIcon(Heroicon::OutlinedCalendarDays)
                            ->required(fn (Get $get): bool => (bool) $get('expiration_enabled'))
                            ->visible(fn (Get $get): bool => (bool) $get('expiration_enabled'))
                            ->live(onBlur: true)
                            ->validationMessages([
                                'min' => 'La vigencia debe ser de al menos :min día.',
                                'max' => 'La vigencia no puede pasar de :max días.',
                            ]),
                        Text::make(fn (): string => $this->overduePreviewText())
                            ->color('warning')
                            ->icon(Heroicon::OutlinedExclamationTriangle)
                            ->visible(fn (): bool => $this->pendingExpirationCount() > 0),
                    ]),
                Text::make(fn (): string => $this->lastChangeText())
                    ->color('gray'),
            ]);
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
            ->id('manage-operation-service-order-parameters-form')
            ->livewireSubmitHandler("mountAction('save')")
            ->footer([
                $this->getFormActionsContentComponent(),
            ]);
    }

    public function getFormActionsContentComponent(): Actions
    {
        return Actions::make($this->getFormActions())
            ->fullWidth(false)
            ->sticky();
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [$this->saveAction()];
    }

    /**
     * Registrada como acción de la página para que Enter en el formulario
     * (`mountAction('save')`) pase por la misma confirmación que el botón.
     */
    public function saveAction(): Action
    {
        return Action::make('save')
            ->label('Guardar parámetros')
            ->icon('heroicon-o-check')
            ->color('success')
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('success'),
            ])
            ->requiresConfirmation(fn (): bool => $this->pendingExpirationCount() > 0)
            ->modalHidden(fn (): bool => $this->pendingExpirationCount() === 0)
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('danger')
            ->modalHeading('¿Caducar órdenes abiertas?')
            ->modalDescription(fn (): string => $this->overduePreviewText().' Esta acción no se puede deshacer desde aquí.')
            ->modalSubmitActionLabel('Guardar y caducar')
            ->action(fn () => $this->save());
    }

    public function save(): void
    {
        $user = Auth::user();

        abort_unless($user !== null && static::canAccess(), 403);

        $data = $this->form->getState();
        $enabled = (bool) ($data['expiration_enabled'] ?? false);
        $previous = OperationServiceOrderSetting::current();
        $days = array_key_exists('validity_days', $data) && $data['validity_days'] !== null
            ? OperationServiceOrderValidity::clampDays((int) $data['validity_days'])
            : $previous->validityDays();

        $settings = DB::transaction(function () use ($days, $enabled, $user): OperationServiceOrderSetting {
            $settings = OperationServiceOrderSetting::query()->orderBy('id')->lockForUpdate()->first()
                ?? OperationServiceOrderSetting::current();

            $settings->update([
                'expiration_enabled' => $enabled,
                'validity_days' => $days,
                'updated_by' => $user->name,
            ]);

            return $settings;
        });

        OperationServiceOrderValidity::flushSettings();

        try {
            Cache::forget('operation-service-orders:expiry-sweep');
        } catch (Throwable) {
        }

        $expired = 0;

        if ($enabled) {
            try {
                $expired = OperationServiceOrderValidity::expireEligibleOrders((string) $user->name);
            } catch (Throwable $exception) {
                Log::error('ManageOperationServiceOrderParameters: no se pudo aplicar la caducidad al guardar', ['error' => $exception->getMessage()]);

                Notification::make()
                    ->title('Parámetros guardados, caducidad pendiente')
                    ->body('La configuración quedó guardada, pero no se pudieron caducar las órdenes vencidas ahora. Se aplicará en el barrido diario de las 7:30.')
                    ->warning()
                    ->send();

                $this->fillFromSettings($settings->refresh());

                return;
            }
        }

        $this->fillFromSettings($settings->refresh());

        Notification::make()
            ->title('Parámetros guardados')
            ->body(match (true) {
                ! $enabled => 'La caducidad quedó desactivada: ninguna orden abierta caducará.',
                $expired > 0 => "Las órdenes caducan a los {$days} día(s). Se caducaron {$expired} orden(es) que ya superaban ese plazo.",
                default => "Las órdenes caducan a los {$days} día(s) desde su aprobación.",
            })
            ->success()
            ->send();
    }

    /**
     * Órdenes abiertas que caducarían al guardar lo que hay en pantalla.
     */
    public function pendingExpirationCount(): int
    {
        if (! (bool) ($this->data['expiration_enabled'] ?? false)) {
            return 0;
        }

        $days = $this->data['validity_days'] ?? null;

        if (! is_numeric($days)) {
            return 0;
        }

        $days = OperationServiceOrderValidity::clampDays((int) $days);

        return $this->overdueCountByDays[$days] ??= OperationServiceOrderValidity::countOverdueWith($days);
    }

    private function overduePreviewText(): string
    {
        $count = $this->pendingExpirationCount();

        return $count === 1
            ? 'Al guardar, 1 orden abierta quedará CADUCADA porque ya superó ese plazo.'
            : "Al guardar, {$count} órdenes abiertas quedarán CADUCADAS porque ya superaron ese plazo.";
    }

    private function lastChangeText(): string
    {
        $settings = OperationServiceOrderSetting::current();

        if (blank($settings->updated_by)) {
            return 'Configuración inicial: activada con '.OperationServiceOrderValidity::DEFAULT_VALIDITY_DAYS.' días, la regla que el sistema usaba antes.';
        }

        return 'Último cambio: '.$settings->updated_by.' · '.$settings->updated_at?->format('d/m/Y h:i A');
    }

    private function fillFromSettings(OperationServiceOrderSetting $settings): void
    {
        $this->form->fill([
            'expiration_enabled' => (bool) $settings->expiration_enabled,
            'validity_days' => $settings->validityDays(),
        ]);
    }
}
