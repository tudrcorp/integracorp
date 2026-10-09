<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Pages;

use App\Filament\Operations\Resources\OperationCoordinationServices\OperationCoordinationServiceResource;
use App\Filament\Operations\Resources\TelemedicinePatients\Actions\RegisterTpaRetailServicesAction;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Operations\DirectServiceRegistration;
use App\Support\Operations\DirectServiceRegistrationException;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * «Registrar servicio directo»: camino de Operaciones para registrar servicios
 * médicos sin pasar por la consulta. Toda la regla vive en
 * {@see DirectServiceRegistration}; esta página solo arma el formulario.
 */
class RegisterDirectService extends Page
{
    protected static string $resource = OperationCoordinationServiceResource::class;

    protected static ?string $title = 'Registrar servicio directo';

    protected static ?string $breadcrumb = 'Registro directo';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.operations.resources.operation-coordination-services.pages.register-direct-service';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getRoutePath(Panel $panel): string
    {
        return '/registro-directo';
    }

    /**
     * Acceso al recurso Servicios médicos más el permiso propio, que asigna el administrador.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function canAccess(array $parameters = []): bool
    {
        return OperationCoordinationServiceResource::canAccess()
            && BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::REGISTER_DIRECT_MEDICAL_SERVICE);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'case_mode' => DirectServiceRegistration::CASE_MODE_NEW,
            'request_date' => now()->toDateString(),
            'service_date' => now()->toDateString(),
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Registrar servicio directo';
    }

    /**
     * Mismo encabezado que los listados de Operaciones.
     */
    public function getHeading(): string|Htmlable
    {
        return new HtmlString(view('filament.operations.partials.list-header', [
            'icon' => 'heroicon-o-document-plus',
            'eyebrow' => 'Operaciones · Servicios médicos',
            'title' => 'Registrar servicio directo',
            'description' => 'Registre servicios sin pasar por la consulta médica. Lo cubierto descuenta cupo clínico.',
        ])->render());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->statePath('data')
            ->components([
                Section::make('Paciente')
                    ->description('Busque por nombre, cédula o código del paciente.')
                    ->icon(Heroicon::OutlinedUser)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('telemedicine_patient_id')
                            ->label('Paciente')
                            ->placeholder('Escriba al menos 2 caracteres')
                            ->searchable()
                            ->searchDebounce(350)
                            ->searchPrompt('Escriba al menos 2 caracteres del nombre, la cédula o el código')
                            ->noSearchResultsMessage('No hay pacientes que coincidan.')
                            ->getSearchResultsUsing(fn (string $search): array => DirectServiceRegistration::searchPatients($search))
                            ->getOptionLabelUsing(fn (mixed $value): ?string => ($patient = DirectServiceRegistration::visiblePatientsQuery()->find((int) $value)) ? DirectServiceRegistration::patientLabel($patient) : null)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('case_mode', DirectServiceRegistration::CASE_MODE_NEW);
                                $set('telemedicine_case_id', null);
                            })
                            ->validationMessages(['required' => 'Seleccione el paciente.']),
                        Placeholder::make('patient_summary')
                            ->hiddenLabel()
                            ->visible(fn (Get $get): bool => filled($get('telemedicine_patient_id')))
                            ->content(fn (Get $get): HtmlString => self::patientSummary(
                                (int) $get('telemedicine_patient_id'),
                                $get('case_mode') === DirectServiceRegistration::CASE_MODE_EXISTING ? (int) $get('telemedicine_case_id') : null,
                            )),
                    ]),
                Section::make('Caso')
                    ->icon(Heroicon::OutlinedFolderOpen)
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => filled($get('telemedicine_patient_id')))
                    ->schema([
                        Radio::make('case_mode')
                            ->label('¿Dónde se registra el servicio?')
                            ->options([
                                DirectServiceRegistration::CASE_MODE_NEW => 'Crear un caso nuevo',
                                DirectServiceRegistration::CASE_MODE_EXISTING => 'Sumarlo a un caso abierto del paciente',
                            ])
                            ->inline()
                            ->required()
                            ->live(),
                        Select::make('telemedicine_case_id')
                            ->label('Caso abierto')
                            ->options(fn (Get $get): array => DirectServiceRegistration::openCaseOptions((int) $get('telemedicine_patient_id') ?: null))
                            ->visible(fn (Get $get): bool => $get('case_mode') === DirectServiceRegistration::CASE_MODE_EXISTING)
                            ->required(fn (Get $get): bool => $get('case_mode') === DirectServiceRegistration::CASE_MODE_EXISTING)
                            ->helperText('Si el caso ya consumió el cupo de una categoría, no se descuenta otra vez.')
                            ->live()
                            ->validationMessages(['required' => 'Seleccione el caso abierto.']),
                    ]),
                Section::make('Datos del servicio')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->columnSpanFull()
                    ->columns(2)
                    ->visible(fn (Get $get): bool => filled($get('telemedicine_patient_id')))
                    ->schema([
                        Select::make('service_line')
                            ->label('Línea de servicio')
                            ->options(fn (): array => DirectServiceRegistration::serviceLineOptions())
                            ->searchable()
                            ->required()
                            ->validationMessages(['required' => 'Seleccione la línea de servicio.']),
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('request_date')
                                    ->label('Fecha de solicitud')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->required()
                                    ->maxDate(now()->addDays(30))
                                    ->minDate(now()->subYears(2)),
                                DatePicker::make('service_date')
                                    ->label('Fecha de servicio')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->required()
                                    ->afterOrEqual('request_date')
                                    ->maxDate(now()->addDays(30))
                                    ->validationMessages(['after_or_equal' => 'No puede ser anterior a la fecha de solicitud.']),
                            ]),
                        Textarea::make('diagnosis')
                            ->label('Diagnóstico o motivo')
                            ->required()
                            ->minLength(5)
                            ->maxLength(2000)
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('observations')
                            ->label('Observaciones')
                            ->maxLength(2000)
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                ...$this->catalogSections(),
                Section::make('Medicamentos')
                    ->description('Sin inventario de Diagnomóvil: el registro directo no descuenta stock.')
                    ->icon(Heroicon::OutlinedBeaker)
                    ->columnSpanFull()
                    ->collapsible()
                    ->visible(fn (Get $get): bool => filled($get('telemedicine_patient_id')))
                    ->schema([
                        Select::make('prescribing_doctor_id')
                            ->label('Médico que indicó los medicamentos')
                            ->options(fn (): array => DirectServiceRegistration::doctorOptions())
                            ->searchable()
                            ->required(fn (Get $get): bool => filled($get('medications')))
                            ->validationMessages(['required' => 'Seleccione el médico que indicó los medicamentos.']),
                        Repeater::make('medications')
                            ->hiddenLabel()
                            ->defaultItems(0)
                            ->addActionLabel('Agregar medicamento')
                            ->maxItems(DirectServiceRegistration::MAX_ITEMS_PER_CATEGORY)
                            ->columns(4)
                            ->schema([
                                TextInput::make('name')->label('Medicamento')->required()->maxLength(250)->columnSpan(2),
                                Select::make('coverage')->label('Cobertura')->options(self::coverageOptions())->default(DirectServiceRegistration::COVERED)->required(),
                                TextInput::make('quantity')->label('Cantidad')->numeric()->integer()->minValue(1)->maxValue(10000)->required(),
                                TextInput::make('duration')->label('Duración (días)')->numeric()->integer()->minValue(1)->maxValue(3650),
                                Textarea::make('indications')->label('Indicaciones')->required()->minLength(3)->maxLength(2000)->rows(2)->columnSpan(3),
                            ]),
                    ]),
                Section::make('Servicios')
                    ->description('Traslado en ambulancia o ingreso a clínica: cada uno queda como un servicio para gestionar en Coordinación.')
                    ->icon(Heroicon::OutlinedTruck)
                    ->columnSpanFull()
                    ->collapsible()
                    ->visible(fn (Get $get): bool => filled($get('telemedicine_patient_id')))
                    ->schema([
                        Repeater::make('standalone')
                            ->hiddenLabel()
                            ->defaultItems(0)
                            ->addActionLabel('Agregar servicio')
                            ->maxItems(count(DirectServiceRegistration::STANDALONE_SERVICES))
                            ->columns(2)
                            ->schema([
                                Select::make('name')
                                    ->label('Servicio')
                                    ->options(array_combine(DirectServiceRegistration::STANDALONE_SERVICES, DirectServiceRegistration::STANDALONE_SERVICES))
                                    ->required()
                                    ->distinct(),
                                Select::make('coverage')->label('Cobertura')->options(self::coverageOptions())->default(DirectServiceRegistration::NOT_COVERED)->required(),
                            ]),
                    ]),
            ]);
    }

    /**
     * @return list<Section>
     */
    private function catalogSections(): array
    {
        $sections = [];

        foreach (['labs' => Heroicon::OutlinedBeaker, 'studies' => Heroicon::OutlinedPhoto, 'specialists' => Heroicon::OutlinedUserGroup] as $category => $icon) {
            $config = DirectServiceRegistration::categories()[$category];

            $sections[] = Section::make($config['label'])
                ->description('La cobertura se propone desde el catálogo; puede cambiarla. Solo lo cubierto descuenta cupo clínico.')
                ->icon($icon)
                ->columnSpanFull()
                ->collapsible()
                ->visible(fn (Get $get): bool => filled($get('telemedicine_patient_id')))
                ->schema([
                    Repeater::make($category)
                        ->hiddenLabel()
                        ->defaultItems(0)
                        ->addActionLabel('Agregar '.mb_strtolower($config['label']))
                        ->maxItems(DirectServiceRegistration::MAX_ITEMS_PER_CATEGORY)
                        ->columns(3)
                        ->schema([
                            Select::make('name')
                                ->label('Ítem del catálogo')
                                ->options(fn (): array => DirectServiceRegistration::catalogOptions($config['catalog']))
                                ->searchable()
                                ->required()
                                ->distinct()
                                ->live()
                                ->afterStateUpdated(fn (mixed $state, Set $set) => $set('coverage', DirectServiceRegistration::suggestedCoverage($category, (string) $state)))
                                ->columnSpan(2),
                            Select::make('coverage')
                                ->label('Cobertura')
                                ->options(self::coverageOptions())
                                ->default(DirectServiceRegistration::COVERED)
                                ->required(),
                        ]),
                ]);
        }

        return $sections;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('register-direct-service-form')
                ->livewireSubmitHandler('register')
                ->footer([
                    Actions::make([$this->registerAction(), $this->cancelAction()])->fullWidth(false),
                ]),
        ]);
    }

    protected function registerAction(): Action
    {
        return Action::make('register')
            ->label('Registrar servicio')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->submit('register');
    }

    protected function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancelar')
            ->color('gray')
            ->url(OperationCoordinationServiceResource::getUrl('index'));
    }

    public function register(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        try {
            $registration = DirectServiceRegistration::register($data, $user);
        } catch (DirectServiceRegistrationException $exception) {
            foreach ($exception->errors as $field => $message) {
                $this->addError('data.'.$field, $message);
            }

            Notification::make()
                ->title('No se registró el servicio')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        } catch (Throwable $exception) {
            Log::error('RegisterDirectService: falló el registro directo de servicios', [
                'user_id' => $user->id,
                'telemedicine_patient_id' => $data['telemedicine_patient_id'] ?? null,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            SecurityAudit::log('AUDIT_OPERATIONS_DIRECT_SERVICE_FAILED', 'operations.coordination-services.direct-registration', [
                'telemedicine_patient_id' => $data['telemedicine_patient_id'] ?? null,
                'error' => $exception->getMessage(),
                'error_class' => $exception::class,
            ], $user);

            Notification::make()
                ->title('No se registró el servicio')
                ->body('Ocurrió un error inesperado y no se guardó nada. Intente de nuevo; si persiste, avise a Sistemas.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $count = count($registration->coordination_ids);

        Notification::make()
            ->title('Servicio registrado')
            ->body("Se creó el registro directo #{$registration->id} con {$count} ".($count === 1 ? 'servicio' : 'servicios').' en Coordinación de Servicios.')
            ->success()
            ->send();

        $case = TelemedicineCase::query()->withoutGlobalScopes()->find($registration->telemedicine_case_id);

        $this->redirect(RegisterTpaRetailServicesAction::medicalServicesIndexUrl($case));
    }

    /**
     * @return array<string, string>
     */
    private static function coverageOptions(): array
    {
        return [
            DirectServiceRegistration::COVERED => 'Cubierto',
            DirectServiceRegistration::NOT_COVERED => 'No cubierto',
        ];
    }

    private static function patientSummary(int $patientId, ?int $caseId): HtmlString
    {
        $patient = DirectServiceRegistration::visiblePatientsQuery()
            ->with(['plan:id,description', 'businessUnit:id,definition'])
            ->find($patientId);

        if (! $patient instanceof TelemedicinePatient) {
            return new HtmlString('<p class="text-sm text-danger-600">El paciente no está disponible.</p>');
        }

        return new HtmlString(view('filament.operations.resources.operation-coordination-services.partials.direct-service-patient-summary', [
            'patient' => $patient,
            'quota' => DirectServiceRegistration::quotaPreview($patient, $caseId ?: null),
        ])->render());
    }
}
