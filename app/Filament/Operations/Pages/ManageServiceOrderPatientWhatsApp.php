<?php

declare(strict_types=1);

namespace App\Filament\Operations\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\OperationServiceOrder;
use App\Models\ServiceOrderPatientNotification as PatientNotification;
use App\Models\ServiceOrderPatientWhatsAppSetting;
use App\Support\Filament\FilamentIosButton;
use App\Support\Operations\PatientWhatsApp\ServiceOrderPatientNotifier;
use App\Support\Operations\PatientWhatsApp\ServiceOrderPatientWhatsAppSettings;
use App\Support\Operations\PatientWhatsApp\UltraMsgConnectionStatus;
use App\Support\SecurityAudit;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use UnitEnum;

/**
 * Operaciones → «WhatsApp al paciente»: interruptor del aviso al paciente de las órdenes de
 * servicio no cubiertas (apagado por defecto), horas para responder y envío de pruebas a un
 * número cualquiera antes de activarlo. Permiso propio: `whatsapp-paciente-ordenes`.
 */
class ManageServiceOrderPatientWhatsApp extends Page
{
    use AuthorizesDepartmentNavigation;

    /** Pruebas por analista cada {@see self::TEST_WINDOW_SECONDS} segundos. */
    public const TEST_MAX_ATTEMPTS = 5;

    public const TEST_WINDOW_SECONDS = 600;

    protected static ?string $navigationLabel = 'WhatsApp al paciente';

    protected static ?string $title = 'WhatsApp al paciente';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'COORDINACIÓN DE SERVICIOS';

    protected static ?int $navigationSort = 41;

    protected static ?string $slug = 'whatsapp-paciente-ordenes';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->fillFromSettings(ServiceOrderPatientWhatsAppSetting::current());
    }

    public function getSubheading(): ?string
    {
        return 'Cuando nace la orden de servicio de un medicamento, laboratorio, estudio o especialista no cubierto, el paciente (o su representante si es menor) recibe por WhatsApp el documento de la orden y responde SI o NO.';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [$this->sendTestAction()];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Conexión con WhatsApp')
                    ->description('Si la instancia de WhatsApp está arriba y si nos llegan sus avisos (respuestas y confirmaciones de entrega). Se actualiza sola cada minuto.')
                    ->icon(Heroicon::OutlinedWifi)
                    ->headerActions([$this->checkConnectionAction()])
                    ->schema([
                        View::make('filament.operations.partials.patient-whatsapp-connection')
                            ->viewData(fn (): array => [
                                'instance' => UltraMsgConnectionStatus::current(),
                                'webhook' => UltraMsgConnectionStatus::webhook(),
                            ]),
                    ]),
                Section::make('Estado del envío')
                    ->icon(Heroicon::OutlinedSignal)
                    ->schema([
                        Text::make(fn (): string => $this->stateText())
                            ->color(fn (): string => ServiceOrderPatientWhatsAppSettings::enabled() ? 'success' : 'gray')
                            ->weight('semibold'),
                        Text::make('El freno de emergencia del servidor está puesto (SERVICE_ORDER_PATIENT_WHATSAPP_ENABLED=false): no sale ningún mensaje, ni siquiera las pruebas, aunque se active aquí.')
                            ->color('danger')
                            ->icon(Heroicon::OutlinedExclamationTriangle)
                            ->visible(fn (): bool => ! ServiceOrderPatientWhatsAppSettings::serverAllows()),
                    ]),
                Section::make('Configuración')
                    ->description('Se recomienda enviar una prueba a su propio número, responderla y ver aquí la respuesta antes de activar.')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Enviar el WhatsApp a los pacientes')
                            ->helperText(fn (Get $get): string => $get('enabled')
                                ? 'Activado: cada orden de servicio de no cubiertos que nazca al aprobar su cotización se le envía al paciente.'
                                : 'Desactivado: no se le envía nada a los pacientes. Las pruebas sí salen.')
                            ->onColor('success')
                            ->live(),
                        TextInput::make('response_hours')
                            ->label('Horas para responder')
                            ->helperText('Pasado este plazo la orden queda «Sin respuesta», se avisa al analista y sale la siguiente orden del mismo teléfono.')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->minValue(ServiceOrderPatientWhatsAppSetting::MIN_RESPONSE_HOURS)
                            ->maxValue(ServiceOrderPatientWhatsAppSetting::MAX_RESPONSE_HOURS)
                            ->suffix('horas')
                            ->prefixIcon(Heroicon::OutlinedClock)
                            ->validationMessages([
                                'min' => 'El plazo debe ser de al menos :min hora.',
                                'max' => 'El plazo no puede pasar de :max horas.',
                            ]),
                    ]),
                Section::make('Pruebas recientes')
                    ->description('Las últimas pruebas enviadas, si llegaron al teléfono y lo que respondieron. Se actualiza solo cada 10 segundos.')
                    ->icon(Heroicon::OutlinedBeaker)
                    ->schema([
                        View::make('filament.operations.partials.patient-whatsapp-tests')
                            ->viewData(fn (): array => ['tests' => $this->recentTests()]),
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
            ->id('manage-service-order-patient-whatsapp-form')
            ->livewireSubmitHandler("mountAction('save')")
            ->footer([
                Actions::make([$this->saveAction()])
                    ->fullWidth(false)
                    ->sticky(),
            ]);
    }

    public function saveAction(): Action
    {
        return Action::make('save')
            ->label('Guardar')
            ->icon('heroicon-o-check')
            ->color('success')
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('success'),
            ])
            ->requiresConfirmation(fn (): bool => $this->isTurningOn())
            ->modalHidden(fn (): bool => ! $this->isTurningOn())
            ->modalIcon(Heroicon::OutlinedChatBubbleLeftRight)
            ->modalIconColor('success')
            ->modalHeading('¿Activar el WhatsApp a los pacientes?')
            ->modalDescription(fn (): string => 'Desde ahora, cada orden de servicio de no cubiertos que nazca al aprobar su cotización se le enviará al paciente por WhatsApp.'
                .($this->hasAnsweredTest() ? '' : ' Aún no hay ninguna prueba respondida: se recomienda enviar una y responderla antes de activar.'))
            ->modalSubmitActionLabel('Activar')
            ->action(fn () => $this->save());
    }

    public function save(): void
    {
        $user = Auth::user();

        abort_unless($user !== null && static::canAccess(), 403);

        $data = $this->form->getState();
        $enabled = (bool) ($data['enabled'] ?? false);
        $hours = ServiceOrderPatientWhatsAppSetting::clampHours((int) ($data['response_hours'] ?? ServiceOrderPatientWhatsAppSetting::DEFAULT_RESPONSE_HOURS));

        [$settings, $wasEnabled] = DB::transaction(function () use ($enabled, $hours, $user): array {
            $settings = ServiceOrderPatientWhatsAppSetting::query()->orderBy('id')->lockForUpdate()->first()
                ?? ServiceOrderPatientWhatsAppSetting::current();
            $wasEnabled = (bool) $settings->enabled;

            $settings->update([
                'enabled' => $enabled,
                'response_hours' => $hours,
                'updated_by' => $user->name,
            ]);

            SecurityAudit::log('AUDIT_SERVICE_ORDER_PATIENT_SETTINGS', 'operations.service-order.patient-whatsapp.settings', [
                'enabled_from' => $wasEnabled,
                'enabled_to' => $enabled,
                'response_hours' => $hours,
            ], $user);

            return [$settings, $wasEnabled];
        });

        ServiceOrderPatientWhatsAppSettings::flush();
        $this->fillFromSettings($settings->refresh());

        Notification::make()
            ->title(match (true) {
                $enabled && ! $wasEnabled => 'WhatsApp al paciente activado',
                ! $enabled && $wasEnabled => 'WhatsApp al paciente desactivado',
                default => 'Configuración guardada',
            })
            ->body($enabled
                ? 'Las órdenes de servicio de no cubiertos se le envían al paciente. Plazo para responder: '.$hours.' horas.'
                : 'No se le envía nada a los pacientes. Los mensajes que ya estaban en espera no saldrán mientras siga desactivado.')
            ->success()
            ->send();
    }

    public function sendTestAction(): Action
    {
        return Action::make('sendTest')
            ->label('Enviar prueba')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
            ])
            ->modalHeading('Enviar un WhatsApp de prueba')
            ->modalDescription('Sale el mismo mensaje que recibiría un paciente, con el documento de la orden de muestra y la marca «MENSAJE DE PRUEBA». Funciona aunque el envío esté desactivado. Responda SI o NO desde ese teléfono y verá la respuesta en «Pruebas recientes».')
            ->modalIcon(Heroicon::OutlinedPaperAirplane)
            ->modalSubmitActionLabel('Enviar prueba')
            ->schema([
                TextInput::make('phone')
                    ->label('Número de WhatsApp')
                    ->placeholder('0412-1234567')
                    ->helperText('Con código de área. Use un número propio o de su equipo, nunca el de un paciente.')
                    ->tel()
                    ->required()
                    ->maxLength(20)
                    ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                        if (ServiceOrderPatientNotifier::normalizePhone(is_scalar($value) ? (string) $value : null) === null) {
                            $fail('El número no es válido para WhatsApp. Escríbalo con código de área, por ejemplo 0412-1234567.');
                        }
                    }),
                Select::make('sample_order_id')
                    ->label('Orden de muestra')
                    ->helperText('Se envía su documento y su detalle. Si no elige, se usa la más reciente.')
                    ->searchable()
                    ->native(false)
                    ->getSearchResultsUsing(fn (string $search): array => self::sampleOrderOptions($search))
                    ->options(fn (): array => self::sampleOrderOptions())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => self::sampleOrderLabel(OperationServiceOrder::query()->find($value))),
            ])
            ->action(function (array $data): void {
                $user = Auth::user();

                abort_unless($user !== null && static::canAccess(), 403);

                $key = 'service-order-patient-whatsapp-test:'.$user->getKey();

                if (RateLimiter::tooManyAttempts($key, self::TEST_MAX_ATTEMPTS)) {
                    Notification::make()
                        ->title('Demasiadas pruebas seguidas')
                        ->body('Espere '.ceil(RateLimiter::availableIn($key) / 60).' minuto(s) para enviar otra.')
                        ->warning()
                        ->send();

                    return;
                }

                try {
                    $test = ServiceOrderPatientNotifier::sendTest(
                        (string) $data['phone'],
                        filled($data['sample_order_id'] ?? null) ? (int) $data['sample_order_id'] : null,
                        $user,
                    );
                } catch (RuntimeException $exception) {
                    Notification::make()->title('No se envió la prueba')->body($exception->getMessage())->danger()->send();

                    return;
                }

                RateLimiter::hit($key, self::TEST_WINDOW_SECONDS);

                Notification::make()
                    ->title('Prueba en camino')
                    ->body($test->status === PatientNotification::STATUS_WAITING_TURN
                        ? 'Ese número tiene otra pregunta pendiente: la prueba sale en cuanto la responda o venza.'
                        : 'Se está enviando a +'.$test->phone.'. Responda SI o NO desde ese teléfono y mire «Pruebas recientes».')
                    ->success()
                    ->send();
            });
    }

    public function checkConnectionAction(): Action
    {
        return Action::make('checkConnection')
            ->label('Verificar ahora')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->size('sm')
            ->action(function (): void {
                abort_unless(Auth::user() !== null && static::canAccess(), 403);

                $key = 'ultramsg-connection-check:'.Auth::id();

                if (RateLimiter::tooManyAttempts($key, 6)) {
                    Notification::make()->title('Espere unos segundos para volver a verificar.')->warning()->send();

                    return;
                }

                RateLimiter::hit($key, 60);
                $status = UltraMsgConnectionStatus::refresh();

                $notification = Notification::make()
                    ->title('WhatsApp: '.$status['label'])
                    ->body($status['detail']);

                match ($status['state']) {
                    UltraMsgConnectionStatus::STATE_CONNECTED => $notification->success(),
                    UltraMsgConnectionStatus::STATE_NOT_CONFIGURED => $notification->info(),
                    default => $notification->danger(),
                };

                $notification->send();
            });
    }

    /**
     * @return array<int, string>
     */
    public static function sampleOrderOptions(string $search = ''): array
    {
        return ServiceOrderPatientNotifier::sampleOrdersQuery()
            ->when(trim($search) !== '', fn ($query) => $query->where('order_number', 'like', '%'.trim($search).'%'))
            ->limit(20)
            ->get(['id', 'order_number', 'service_type'])
            ->mapWithKeys(fn (OperationServiceOrder $order): array => [(int) $order->id => (string) self::sampleOrderLabel($order)])
            ->all();
    }

    public static function sampleOrderLabel(?OperationServiceOrder $order): ?string
    {
        if (! $order instanceof OperationServiceOrder) {
            return null;
        }

        return $order->order_number.' · '.ucfirst(mb_strtolower((string) $order->service_type));
    }

    /**
     * @return Collection<int, PatientNotification>
     */
    public function recentTests(): Collection
    {
        return PatientNotification::query()
            ->where('is_test', true)
            ->latest('id')
            ->limit(10)
            ->get();
    }

    private function isTurningOn(): bool
    {
        return (bool) ($this->data['enabled'] ?? false) && ! ServiceOrderPatientWhatsAppSetting::current()->enabled;
    }

    private function hasAnsweredTest(): bool
    {
        return PatientNotification::query()->where('is_test', true)->where('status', PatientNotification::STATUS_ANSWERED)->exists();
    }

    private function stateText(): string
    {
        $settings = ServiceOrderPatientWhatsAppSetting::current();

        if (! $settings->enabled) {
            return 'Desactivado: no se le envía nada a los pacientes.';
        }

        return ServiceOrderPatientWhatsAppSettings::serverAllows()
            ? 'Activado: los pacientes reciben su orden de servicio por WhatsApp. Plazo para responder: '.$settings->response_hours.' horas.'
            : 'Activado aquí, pero frenado en el servidor: no sale nada.';
    }

    private function lastChangeText(): string
    {
        $settings = ServiceOrderPatientWhatsAppSetting::current();

        return blank($settings->updated_by)
            ? 'Configuración inicial: desactivado.'
            : 'Último cambio: '.$settings->updated_by.' · '.$settings->updated_at?->format('d/m/Y h:i A');
    }

    private function fillFromSettings(ServiceOrderPatientWhatsAppSetting $settings): void
    {
        $this->form->fill([
            'enabled' => (bool) $settings->enabled,
            'response_hours' => ServiceOrderPatientWhatsAppSetting::clampHours((int) $settings->response_hours),
        ]);
    }
}
