<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicinePatients\Pages;

use App\Filament\Operations\Resources\TelemedicinePatients\Actions\RegisterTpaRetailServicesAction;
use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\Filament\FilamentIosButton;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Filament\TelemedicinePatientPageHeader;
use App\Support\Operations\RetailServiceProviderCatalog;
use App\Support\Operations\RetailServiceRegistration;
use App\Support\Operations\RetailServiceRegistrationException;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Registro de servicios TPA/RETAIL de un paciente: servicios principales,
 * laboratorios, estudios, especialistas y medicamentos, cada uno con su
 * proveedor. Toda la regla vive en {@see RetailServiceRegistration}; esta
 * página solo arma el formulario y el resumen en vivo.
 */
class RegisterRetailServices extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TelemedicinePatientResource::class;

    protected static ?string $title = 'Registrar servicios RETAIL';

    protected static ?string $breadcrumb = 'Servicios RETAIL';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.operations.resources.telemedicine-patients.pages.register-retail-services';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Memo por request: el select se vuelve a pintar en cada actualización en vivo.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private array $suggestionsCache = [];

    /**
     * @var array<string, string|null>
     */
    private array $providerLabelCache = [];

    public static function getRoutePath(Panel $panel): string
    {
        return '/{record}/retail';
    }

    public function mount(int|string $record): void
    {
        abort_unless(TelemedicinePatientResource::canAccess(), 403);

        $this->record = $this->resolveRecord($record);

        abort_unless(
            OperationsSupplierScope::applyToQuery(TelemedicinePatient::query(), 'supplier_id')->whereKey($this->record->getKey())->exists(),
            404,
        );

        $this->form->fill([
            'services' => [],
            'service_providers' => [],
            'labs' => [],
            'studies' => [],
            'specialists' => [],
            'medications' => [],
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Registrar servicios RETAIL';
    }

    public function getHeading(): string|Htmlable
    {
        return TelemedicinePatientPageHeader::forPatient($this->getPatient(), 'retail');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->statePath('data')
            ->components([
                $this->mainServicesSection(),
                Grid::make(['default' => 1, 'xl' => 3])
                    ->columnSpanFull()
                    ->schema([
                        $this->catalogSection('labs', Heroicon::OutlinedBeaker),
                        $this->catalogSection('studies', Heroicon::OutlinedPhoto),
                        $this->catalogSection('specialists', Heroicon::OutlinedUserGroup),
                    ]),
                $this->medicationsSection(),
                Section::make('Motivo de la atención')
                    ->description('Lo lee el médico de guardia al abrir el caso. Obligatorio si Telemedicina o AMD van a un equipo médico.')
                    ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('reason')
                            ->hiddenLabel()
                            ->placeholder('Ej.: fiebre de 39 °C desde ayer, solicita evaluación domiciliaria.')
                            ->rows(2)
                            ->maxLength(2000)
                            ->required(fn (Get $get): bool => $this->teamLabelFor($get) !== null)
                            ->minLength(fn (Get $get): ?int => $this->teamLabelFor($get) !== null ? 5 : null)
                            ->validationMessages([
                                'required' => 'Escriba el motivo de la atención: es lo primero que lee el médico de guardia.',
                                'min' => 'Describa el motivo con al menos 5 caracteres.',
                            ]),
                    ]),
                Section::make('Resumen')
                    ->description('Revise antes de registrar.')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->columnSpanFull()
                    ->schema([
                        Placeholder::make('summary')
                            ->hiddenLabel()
                            ->content(fn (Get $get): HtmlString => $this->summary($get)),
                    ]),
            ]);
    }

    private function mainServicesSection(): Section
    {
        $providerFields = [];

        foreach (RetailServiceRegistration::mainServices() as $service) {
            $field = 'service_providers.'.RetailServiceRegistration::serviceFieldKey($service);
            $isSelected = fn (Get $get): bool => in_array($service, (array) $get('services'), true);

            $providerFields[] = $this->providerSelect($field, $service)
                ->label(fn (): string => 'Proveedor · '.$this->serviceTitle($service))
                ->visible($isSelected)
                ->required($isSelected)
                ->helperText(RetailServiceProviderCatalog::isTeamService($service)
                    ? 'Elija un equipo médico para que el caso llegue al médico de guardia en Telemedicina.'
                    : null);
        }

        return Section::make('Servicios principales')
            ->description('Marque uno o varios. Debajo aparece el selector de proveedor de cada servicio marcado.')
            ->icon(Heroicon::OutlinedSquares2x2)
            ->columnSpanFull()
            ->schema([
                CheckboxList::make('services')
                    ->hiddenLabel()
                    ->options(collect(RetailServiceRegistration::mainServices())->mapWithKeys(fn (string $service): array => [$service => $this->serviceTitle($service)])->all())
                    ->descriptions([
                        'TELEMEDICINA' => 'Llega al médico de guardia si asigna un equipo médico.',
                        'AMD (ASISTENCIA MEDICA DOMICILIARIA)' => 'Llega al médico de guardia si asigna un equipo médico.',
                    ])
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                    ->gridDirection('row')
                    ->live(),
                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema($providerFields),
            ]);
    }

    private function catalogSection(string $category, Heroicon $icon): Section
    {
        $config = RetailServiceRegistration::categories()[$category];
        $hasItems = fn (Get $get): bool => filled($get($category));

        return Section::make($config['label'])
            ->icon($icon)
            ->compact()
            ->schema([
                Select::make($category)
                    ->label('Ítems')
                    ->placeholder('Busque en el catálogo')
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => RetailServiceRegistration::catalogOptions($config['catalog']))
                    ->maxItems(RetailServiceRegistration::MAX_ITEMS_PER_CATEGORY)
                    ->live(),
                $this->providerSelect($category.'_provider', $config['specific_service'])
                    ->label('Proveedor')
                    ->visible($hasItems)
                    ->required($hasItems),
            ]);
    }

    private function medicationsSection(): Section
    {
        $hasItems = fn (Get $get): bool => collect((array) $get('medications'))->contains(fn (mixed $row): bool => filled(data_get($row, 'name')));

        return Section::make('Medicamentos')
            ->description('Sin inventario Diagnomóvil: retail no descuenta stock. Los indica el equipo médico del caso.')
            ->icon(Heroicon::OutlinedEyeDropper)
            ->columnSpanFull()
            ->schema([
                Repeater::make('medications')
                    ->hiddenLabel()
                    ->defaultItems(0)
                    ->addActionLabel('Agregar medicamento')
                    ->maxItems(RetailServiceRegistration::MAX_ITEMS_PER_CATEGORY)
                    ->reorderable(false)
                    ->table([
                        TableColumn::make('Medicamento')->width('30%'),
                        TableColumn::make('Cantidad')->width('12%'),
                        TableColumn::make('Duración (días)')->width('14%'),
                        TableColumn::make('Indicaciones'),
                    ])
                    ->schema([
                        TextInput::make('name')->required()->maxLength(250)->live(onBlur: true),
                        TextInput::make('quantity')->numeric()->integer()->minValue(1)->maxValue(10000)->default(1)->required(),
                        TextInput::make('duration')->numeric()->integer()->minValue(1)->maxValue(3650),
                        TextInput::make('indications')->required()->minLength(3)->maxLength(2000)->placeholder('Ej.: 1 tableta cada 8 horas'),
                    ])
                    ->live(),
                $this->providerSelect('medications_provider', 'MEDICAMENTOS')
                    ->label('Proveedor de los medicamentos')
                    ->visible($hasItems)
                    ->required($hasItems),
            ]);
    }

    private function providerSelect(string $field, string $specificService): Select
    {
        return Select::make($field)
            ->placeholder('Busque por nombre o RIF')
            ->searchable()
            ->searchDebounce(350)
            ->searchPrompt('Escriba el nombre o el RIF del proveedor natural, jurídico o aliado corporativo')
            ->searchingMessage('Buscando proveedores…')
            ->noSearchResultsMessage('Ningún proveedor coincide con la búsqueda.')
            ->options(fn (): array => $this->suggestionsCache[$specificService] ??= RetailServiceProviderCatalog::suggestions($specificService, $this->getPatient()->state_id !== null ? (int) $this->getPatient()->state_id : null))
            ->getSearchResultsUsing(fn (string $search): array => RetailServiceProviderCatalog::search($search, $specificService))
            ->getOptionLabelUsing(fn (mixed $value): ?string => $this->providerLabel($value))
            ->prefixIcon(Heroicon::OutlinedBuildingStorefront)
            ->native(false)
            ->live()
            ->validationMessages(['required' => 'Seleccione el proveedor.']);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('register-retail-services-form')
                ->livewireSubmitHandler('register')
                ->footer([
                    Actions::make([$this->registerAction(), $this->cancelAction()])
                        ->fullWidth(false)
                        ->sticky(),
                ]),
        ]);
    }

    protected function registerAction(): Action
    {
        return Action::make('register')
            ->label('Registrar servicios')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->submit('register')
            ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor('success')]);
    }

    protected function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Volver a la ficha')
            ->color('gray')
            ->url(fn (): string => TelemedicinePatientResource::getUrl('view', ['record' => $this->getRecord()]))
            ->extraAttributes(['class' => FilamentIosButton::extraClassForFilamentColor('gray')]);
    }

    public function register(): void
    {
        abort_unless(TelemedicinePatientResource::canAccess(), 403);

        $data = $this->form->getState();
        $user = Auth::user();
        $patient = $this->getPatient();

        if (! $user instanceof User) {
            abort(403);
        }

        try {
            $result = RetailServiceRegistration::register($patient, $data, $user);
        } catch (RetailServiceRegistrationException $exception) {
            foreach ($exception->errors as $field => $message) {
                $this->addError('data.'.$field, $message);
            }

            Notification::make()
                ->title('No se registraron los servicios')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        } catch (Throwable $exception) {
            Log::error('RegisterRetailServices: falló el registro de servicios TPA/RETAIL', [
                'user_id' => $user->id,
                'telemedicine_patient_id' => $patient->id,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            SecurityAudit::log('AUDIT_OPERATIONS_TPA_RETAIL_SERVICES_FAILED', 'operations.telemedicine-patients.register-tpa-retail-services', [
                'telemedicine_patient_id' => $patient->id,
                'patient_name' => $patient->full_name,
                'error' => $exception->getMessage(),
                'error_class' => $exception::class,
            ], $user);

            Notification::make()
                ->title('No se registraron los servicios')
                ->body('Ocurrió un error inesperado y no se guardó nada. Intente de nuevo; si persiste, avise a Sistemas.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $case = $result['case'];
        $count = count($result['coordination_ids']);

        SecurityAudit::log('AUDIT_OPERATIONS_TPA_RETAIL_SERVICES_REGISTERED', 'operations.telemedicine-patients.register-tpa-retail-services', [
            'telemedicine_patient_id' => $patient->id,
            'patient_name' => $patient->full_name,
            'telemedicine_case_id' => $case->id,
            'telemedicine_case_code' => $case->code,
            'coordination_ids' => $result['coordination_ids'],
            'medical_team' => $result['team']['key'] ?? null,
            'medical_team_supplier_id' => $result['team']['supplier_id'] ?? null,
        ], $user);

        Notification::make()
            ->title('Servicios registrados')
            ->body("Caso {$case->code}: {$count} ".($count === 1 ? 'solicitud enviada' : 'solicitudes enviadas').' a Coordinación de Servicios.'
                .($result['team'] !== null ? ' El caso quedó en la bandeja del '.$result['team']['label'].'.' : ''))
            ->success()
            ->send();

        $this->redirect(RegisterTpaRetailServicesAction::medicalServicesIndexUrl($case));
    }

    private function getPatient(): TelemedicinePatient
    {
        /** @var TelemedicinePatient */
        return $this->getRecord();
    }

    private function serviceTitle(string $service): string
    {
        return match ($service) {
            'AMD (ASISTENCIA MEDICA DOMICILIARIA)' => 'AMD · Asistencia médica domiciliaria',
            'CONSULTA ONLINE CON MEDICO ESPECIALISTA' => 'Consulta online con especialista',
            'LECTURA DE RESULTADOS (LABORATORIO(S))' => 'Lectura de resultados · Laboratorio',
            'LECTURA DE RESULTADOS (IMAGENOLOGIA)' => 'Lectura de resultados · Imagenología',
            'URGEN CARE' => 'Urgent care',
            'APS' => 'APS · Atención primaria',
            default => mb_convert_case(mb_strtolower($service), MB_CASE_TITLE),
        };
    }

    private function providerLabel(mixed $key): ?string
    {
        if (! filled($key)) {
            return null;
        }

        return $this->providerLabelCache[(string) $key] ??= RetailServiceProviderCatalog::label($key);
    }

    /**
     * Equipo médico al que llegará el caso según lo elegido en Telemedicina y AMD.
     */
    private function teamLabelFor(Get $get): ?string
    {
        $teams = RetailServiceProviderCatalog::teamOptions();
        $services = (array) $get('services');

        foreach (RetailServiceProviderCatalog::TEAM_SERVICES as $service) {
            if (! in_array($service, $services, true)) {
                continue;
            }

            $key = $get('service_providers.'.RetailServiceRegistration::serviceFieldKey($service));

            if (is_string($key) && isset($teams[$key])) {
                return $teams[$key];
            }
        }

        return null;
    }

    private function summary(Get $get): HtmlString
    {
        $rows = [];

        foreach ((array) $get('services') as $service) {
            if (! in_array($service, RetailServiceRegistration::mainServices(), true)) {
                continue;
            }

            $rows[] = [
                'service' => $this->serviceTitle($service),
                'items' => null,
                'provider' => $this->providerLabel($get('service_providers.'.RetailServiceRegistration::serviceFieldKey($service))),
            ];
        }

        foreach (RetailServiceRegistration::categories() as $category => $config) {
            $names = $category === 'medications'
                ? collect((array) $get('medications'))->map(fn (mixed $row): string => trim((string) data_get($row, 'name')))->filter()->values()->all()
                : array_values(array_filter((array) $get($category)));

            if ($names === []) {
                continue;
            }

            $rows[] = [
                'service' => $config['label'],
                'items' => $names,
                'provider' => $this->providerLabel($get($category.'_provider')),
            ];
        }

        return new HtmlString(view('filament.operations.resources.telemedicine-patients.partials.retail-services-summary', [
            'rows' => $rows,
            'teamLabel' => $this->teamLabelFor($get),
        ])->render());
    }
}
