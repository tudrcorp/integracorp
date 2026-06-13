<?php

namespace App\Filament\Operations\Resources\TelemedicinePatients\Tables;

use App\Filament\Operations\Resources\Suppliers\SupplierResource;
use App\Http\Controllers\UtilsController;
use App\Jobs\AssignedCase;
use App\Models\AnotherAddress;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatient;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\SecurityAudit;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TelemedicinePatientsTable
{
    public static function configure(Table $table): Table
    {
        // dd(Auth::user()?->departament ?? []);
        return $table
            ->heading('Listado de pacientes')
            ->description('Pacientes afiliados y externos. Use columnas ocultas para ver domicilio y datos de afiliación.')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(function (Builder $query): Builder {
                $query->with([
                    'businessUnit',
                    'businessLine',
                    'plan',
                    'coverage',
                    'country',
                    'city',
                    'state',
                    'supplier',
                    'createdBy',
                ]);

                if (in_array('ATENMEDI', Auth::user()?->departament ?? [], true)) {
                    $query->where('managed_by', 'ATENMEDI');
                }

                OperationsSupplierScope::applyToQuery($query);

                return $query;
            })
            ->columns([
                // TextColumn::make('managed_by')
                //     ->label('Gestionado por')
                //     ->icon('heroicon-o-user')
                //     ->formatStateUsing(fn (?string $state): string => $state ? mb_strtoupper($state) : '—')
                //     ->badge()
                //     ->color(fn (?string $state): string => match (mb_strtoupper((string) $state)) {
                //         'ATENMEDI' => 'success',
                //         'TDG' => 'info',
                //         default => 'gray',
                //     })
                //     ->searchable()
                //     ->weight('medium')
                //     ->description(fn (TelemedicinePatient $record): string => $record->managed_by ?? ''),
                TextColumn::make('supplier_id')
                    ->label('Proveedor')
                    ->icon('heroicon-o-building-storefront')
                    ->iconColor('gray')
                    ->badge()
                    ->color(fn (mixed $state): string => filled($state) ? 'primary' : 'gray')
                    ->formatStateUsing(fn (mixed $state): string => filled($state) ? '#'.(int) $state : 'Sin proveedor')
                    ->description(fn (TelemedicinePatient $record): ?string => self::supplierSummaryDescription($record))
                    ->tooltip(fn (TelemedicinePatient $record): ?string => filled($record->supplier_id)
                        ? 'Abrir ficha del proveedor #'.$record->supplier_id
                        : 'Paciente sin proveedor asignado')
                    ->weight(FontWeight::SemiBold)
                    ->sortable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $innerQuery) use ($search): void {
                            $innerQuery
                                ->where('supplier_id', 'like', "%{$search}%")
                                ->orWhereHas(
                                    'supplier',
                                    fn (Builder $supplierQuery): Builder => $supplierQuery->where('name', 'like', "%{$search}%"),
                                );
                        });
                    })
                    ->copyable(fn (mixed $state): bool => filled($state))
                    ->copyableState(fn (mixed $state): ?string => filled($state) ? (string) (int) $state : null)
                    ->copyMessage('ID de proveedor copiado')
                    ->url(fn (TelemedicinePatient $record): ?string => filled($record->supplier_id)
                        ? SupplierResource::getUrl('view', ['record' => $record->supplier_id])
                        : null)
                    ->openUrlInNewTab()
                    ->extraCellAttributes([
                        'class' => 'fi-telemedicine-patient-supplier-cell align-top',
                    ])
                    ->extraAttributes([
                        'class' => 'fi-telemedicine-patient-supplier-link',
                    ])
                    ->toggleable()
                    ->visible(fn (): bool => OperationsSupplierScope::currentSupplierId() === null),
                TextColumn::make('full_name')
                    ->label('Paciente')
                    ->icon('heroicon-o-user')
                    ->iconColor('gray')
                    ->formatStateUsing(fn (?string $state): string => $state ? mb_strtoupper($state) : '—')
                    ->searchable()
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (TelemedicinePatient $record): ?string => self::patientIdentificationDescription($record))
                    ->extraCellAttributes([
                        'class' => 'fi-telemedicine-patient-name-cell align-top min-w-[12rem]',
                    ])
                    ->tooltip(fn (TelemedicinePatient $record): ?string => filled($record->full_name)
                        ? $record->full_name.($record->nro_identificacion ? ' · '.$record->nro_identificacion : '')
                        : null),
                TextColumn::make('nro_identificacion')
                    ->label('Identificación')
                    ->searchable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon('heroicon-m-phone')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Copiado')
                    ->placeholder('—'),
                TextColumn::make('email')
                    ->label('Correo')
                    ->icon('heroicon-m-envelope')
                    ->searchable()
                    ->limit(28)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->copyable()
                    ->copyMessage('Copiado')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('businessUnit.definition')
                    ->label('Unidad de negocio')
                    ->badge()
                    ->color(fn (?string $state): string => self::badgeColorFromString($state, [
                        'primary', 'info', 'success', 'warning',
                    ]))
                    ->searchable()
                    ->toggleable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('businessLine.definition')
                    ->label('Línea de servicio')
                    ->badge()
                    ->color(fn (?string $state): string => self::badgeColorFromString($state, [
                        'success', 'warning', 'primary', 'danger',
                    ]))
                    ->searchable()
                    ->toggleable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('type_affiliation')
                    ->label('Tipo de afiliación')
                    ->badge()
                    ->color(fn (?string $state): string => match (mb_strtoupper((string) $state)) {
                        'TITULAR', 'TITULAR ' => 'success',
                        'BENEFICIARIO', 'DEPENDIENTE' => 'info',
                        'EXTERNO', 'PARTICULAR' => 'warning',
                        default => self::badgeColorFromString($state, ['primary', 'gray', 'info']),
                    })
                    ->searchable()
                    ->toggleable()
                    ->visible(fn (): bool => ! in_array('ATENMEDI', Auth::user()?->departament)),
                TextColumn::make('name_corporate')
                    ->label('Corporativo')
                    ->limit(24)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->searchable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('birth_date')
                    ->label('Nacimiento')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                TextColumn::make('sex')
                    ->label('Sexo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ?: '—')
                    ->color(fn (?string $state): string => match (mb_strtoupper((string) $state)) {
                        'M', 'MASCULINO' => 'primary',
                        'F', 'FEMENINO' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                ColumnGroup::make('Domicilio y ubicación')
                    ->columns([
                        TextColumn::make('address')
                            ->label('Dirección')
                            ->limit(36)
                            ->tooltip(fn (?string $state): ?string => $state)
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('country.name')
                            ->label('País')
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('city.definition')
                            ->label('Ciudad')
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('region')
                            ->label('Región')
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('state.definition')
                            ->label('Estado')
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),
                ColumnGroup::make('Afiliación')
                    ->columns([
                        TextColumn::make('plan.description')
                            ->label('Plan')
                            ->badge()
                            ->color(fn (?string $state): string => self::badgeColorFromString($state, [
                                'success', 'info', 'primary', 'warning',
                            ]))
                            ->searchable()
                            ->limit(20)
                            ->tooltip(fn (?string $state): ?string => $state)
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('coverage.price')
                            ->label('Cobertura')
                            ->badge()
                            ->color('warning')
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('code_affiliation')
                            ->label('Código')
                            ->badge()
                            ->color('info')
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        TextColumn::make('status_affiliation')
                            ->label('Estatus')
                            ->badge()
                            ->color(fn (?string $state): string => match (mb_strtoupper((string) $state)) {
                                'ACTIVO', 'ACTIVA', 'VIGENTE' => 'success',
                                'INACTIVO', 'INACTIVA', 'SUSPENDIDO', 'BAJA' => 'danger',
                                'PENDIENTE', 'EN PROCESO' => 'warning',
                                default => 'primary',
                            })
                            ->searchable()
                            ->toggleable(isToggledHiddenByDefault: true),
                    ]),
                TextColumn::make('created_by')
                    ->label('Asociado por')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Fecha de asociación')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (TelemedicinePatient $record): string => $record->created_at->diffForHumans())
                    ->sortable()
                    ->icon('heroicon-m-calendar'),
            ])
            ->filters([
                Filter::make('created_at')
                    ->label('Fecha de asociación')
                    ->form([
                        DatePicker::make('desde')->label('Desde'),
                        DatePicker::make('hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Desde '.Carbon::parse($data['desde'])->toFormattedDateString();
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Hasta '.Carbon::parse($data['hasta'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver Detalle'),
                    EditAction::make()
                        ->label('Editar')
                        ->hidden(fn (TelemedicinePatient $record) => in_array('ATENMEDI', Auth::user()->departament)),
                    Action::make('asigned_doctor')
                        ->label('Asignar doctor')
                        ->icon('healthicons-f-i-exam-qualification')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalWidth(Width::ThreeExtraLarge)
                        ->modalHeading('Asignación de Caso')
                        ->form([

                            // ...Informacion del Doctor
                            Fieldset::make('Asignar Doctor')
                                ->schema([
                                    Select::make('doctor_id')
                                        ->label('Doctor')
                                        ->required()
                                        ->helperText('Entre paréntesis se muestra el grupo (managed_by) de cada médico.')
                                        ->options(function (?TelemedicinePatient $record): array {
                                            $departments = Auth::user()?->departament ?? [];

                                            $doctorQuery = TelemedicineDoctor::query()->orderBy('full_name');
                                            OperationsSupplierScope::applyToQuery($doctorQuery);

                                            if (OperationsSupplierScope::currentSupplierId() === null && filled($record?->supplier_id)) {
                                                $doctorQuery->where('supplier_id', $record->supplier_id);
                                            }

                                            if (in_array('ATENMEDI', $departments, true)) {
                                                $doctorQuery->where('managed_by', 'ATENMEDI');
                                            }

                                            return $doctorQuery
                                                ->get()
                                                ->mapWithKeys(fn (TelemedicineDoctor $doctor): array => [
                                                    $doctor->id => sprintf(
                                                        '%s (%s)',
                                                        $doctor->full_name,
                                                        filled($doctor->managed_by) ? $doctor->managed_by : 'Sin grupo'
                                                    ),
                                                ])
                                                ->all();
                                        }),
                                    Grid::make()
                                        ->schema([
                                            Textarea::make('reason')
                                                ->label('Motivo de la consulta')
                                                ->autosize()
                                                ->required()
                                                ->afterStateUpdatedJs(<<<'JS'
                                            $set('reason', $state.toUpperCase());
                                        JS)
                                                ->helperText('Escriba el motivo de la llamada del paciente. Por favor sea lo más específico posible ya que el médico tomará esta información para determinar el tipo de atención que requiere el paciente.'),
                                        ])->columnSpanFull()->columns(1),
                                    Grid::make(1)
                                        ->schema([
                                            Radio::make('feedback')
                                                ->label('¿La ubicación actual del paciente es la registrada en el sistema?')
                                                ->default(true)
                                                ->live()
                                                ->boolean()
                                                ->inline()
                                                ->inlineLabel(false),
                                            Radio::make('ambulanceParking')
                                                ->label('La dirección posee estacionamiento para ambulancia?')
                                                ->boolean()
                                                ->default(true)
                                                ->inline()
                                                ->live()
                                                ->hidden(fn (Get $get) => ! $get('feedback')),
                                            Textarea::make('directionAmbulance')
                                                ->label('Dirección alternativa del Estacionamiento para Ambulancias')
                                                ->autosize()
                                                ->hidden(fn (Get $get) => $get('ambulanceParking')),
                                        ])->columnSpanFull()->hiddenOn('edit'),
                                ])->columnSpanFull()->columns(1),

                            // ... Lista de ubicaciones ya registradas
                            Fieldset::make('Lista de ubicaciones registradas por el paciente')
                                ->hidden(fn (Get $get) => $get('feedback'))
                                ->schema([
                                    Select::make('address_id')
                                        ->label('Ubicación')
                                        ->live()
                                        ->options(function ($record, Get $get) {
                                            return AnotherAddress::where('telemedicine_patient_id', $record->id)->pluck('address', 'id');
                                        })
                                        ->helperText(function ($record, Get $get, $state) {
                                            if ($state == null) {
                                                return '';
                                            }

                                            return AnotherAddress::where('telemedicine_patient_id', $record->id)->where('id', $get('address_id'))->first()->ambulanceParking == true ? 'La Dirección SI posee estacionamiento para ambulancias' : 'La Dirección NO posee estacionamiento para ambulancias';
                                        }),
                                    Checkbox::make('new_address')
                                        ->inline()
                                        ->live()
                                        ->label('Nueva Ubicación')
                                        ->default(false),
                                ])->columnSpanFull()->columns(1),

                            // ...SECCION NUEVA UBICACION
                            Section::make()
                                ->hidden(fn (Get $get) => ! $get('new_address'))
                                ->heading('Registro  de Nueva Ubicación')
                                ->description('La ubicación actual permite coordinar un servicio IN SITU. No afecta los datos registrados del afiliado.')
                                ->schema([
                                    Select::make('country_id')
                                        ->label('País')
                                        ->live()
                                        ->options(Country::all()->pluck('name', 'id'))
                                        ->searchable()
                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                        ->required()
                                        ->validationMessages([
                                            'required' => 'Campo Requerido',
                                        ])
                                        ->default(189)
                                        ->preload(),
                                    Select::make('state_id')
                                        ->label('Estado')
                                        ->options(function (Get $get) {
                                            return State::where('country_id', $get('country_id'))->pluck('definition', 'id');
                                        })
                                        ->live()
                                        ->searchable()
                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                        ->required()
                                        ->validationMessages([
                                            'required' => 'Campo Requerido',
                                        ])
                                        ->preload(),
                                    Select::make('city_id')
                                        ->label('Ciudad')
                                        ->options(function (Get $get) {
                                            return City::where('country_id', $get('country_id'))->where('state_id', $get('state_id'))->pluck('definition', 'id');
                                        })
                                        ->searchable()
                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                        ->required()
                                        ->validationMessages([
                                            'required' => 'Campo Requerido',
                                        ])
                                        ->preload(),
                                    TextInput::make('phone_1')
                                        ->label('Número de Teléfono Principal')
                                        ->tel()
                                        ->mask(fn (Get $get) => $get('country_id') == 189 ? '99999999999' : '')
                                        ->required()
                                        ->helperText('Ejemplo: 04161234567'),
                                    TextInput::make('phone_2')
                                        ->label('Número de Teléfono Alternativo')
                                        ->tel()
                                        ->mask(fn (Get $get) => $get('country_id') == 189 ? '99999999999' : '')
                                        ->helperText('Ejemplo: 04161234567'),
                                    Select::make('relationship')
                                        ->label('Parentesco')
                                        ->options([
                                            'TITULAR' => 'TITULAR',
                                            'MADRE' => 'MADRE',
                                            'PADRE' => 'PADRE',
                                            'HIJO(A)' => 'HIJO(A)',
                                            'ABUELO(A)' => 'ABUELO(A)',
                                            'AMIGO(A)' => 'AMIGO(A)',
                                            'OTRO' => 'OTRO',
                                        ]),

                                    Grid::make()
                                        ->schema([
                                            Textarea::make('address')
                                                ->label('Dirección Exacta')
                                                ->autosize()
                                                ->required()
                                                ->afterStateUpdatedJs(<<<'JS'
                                            $set('address', $state.toUpperCase());
                                            JS)
                                                ->helperText('Redacte la ubicación exacta del paciente, avenida, calle, nombre del edificio/casa, piso, apto y puntos de referencia. Por favor sea lo más específico posible.'),
                                            Radio::make('ambulanceParking')
                                                ->label('La dirección posee estacionamiento para ambulancia?')
                                                ->boolean()
                                                ->live()
                                                ->default(true)
                                                ->inline(),
                                            Textarea::make('directionAmbulance')
                                                ->label('Dirección alternativa del Estacionamiento para Ambulancias')
                                                ->autosize()
                                                ->hidden(fn (Get $get) => $get('ambulanceParking')),
                                        ])->columnSpanFull()->columns(1),
                                ])->columnSpanFull()->columns(2),

                        ])
                        ->action(function (TelemedicinePatient $record, array $data) {
                            try {
                                $doctor = TelemedicineDoctor::query()->findOrFail($data['doctor_id']);

                                SecurityAudit::log('AUDIT_OPERATIONS_TELEMEDICINE_CASE_ASSIGNMENT_STARTED', 'operations.telemedicine-patients.assign-doctor', [
                                    'telemedicine_patient_id' => $record->id,
                                    'patient_name' => $record->full_name,
                                    'doctor_id' => $data['doctor_id'] ?? null,
                                    'feedback' => $data['feedback'] ?? null,
                                    'address_id' => $data['address_id'] ?? null,
                                ]);
                                /**
                                 * CASO 1: El paciente tiene la misma ubicacion que la registrada en la afiliacion
                                 */
                                if ($data['feedback'] == true) {

                                    $case = TelemedicineCase::create([
                                        'code' => UtilsController::generateCaseCode(),
                                        'telemedicine_patient_id' => $record->id,
                                        'telemedicine_doctor_id' => $data['doctor_id'],
                                        'patient_name' => $record->full_name,
                                        'patient_age' => $record->age,
                                        'patient_sex' => $record->sex,
                                        'patient_phone' => $record->phone,
                                        'patient_address' => $record->address,
                                        'patient_country_id' => $record->country_id,
                                        'patient_state_id' => $record->state_id,
                                        'patient_city_id' => $record->city_id,
                                        'reason' => $data['reason'],
                                        'ambulanceParking' => $data['ambulanceParking'],
                                        'status' => 'ASIGNADO',
                                        'assigned_by' => Auth::user()->name,
                                        'managed_by' => $doctor->managed_by,
                                        'supplier_id' => OperationsSupplierScope::resolveFromPatient($record),
                                    ]);

                                    if ($case) {

                                        $name_patient = $case['patient_name'];
                                        $name = $doctor->full_name;
                                        $phone = $doctor->phone;
                                        $address = $record->address;
                                        $code = $case->code;
                                        $reason = $data['reason'];
                                        $email = $doctor->email;

                                        AssignedCase::dispatch($phone, $name, $code, $reason, $name_patient, $email, $address);

                                        SecurityAudit::log('AUDIT_OPERATIONS_TELEMEDICINE_CASE_ASSIGNED', 'operations.telemedicine-patients.assign-doctor', [
                                            'telemedicine_patient_id' => $record->id,
                                            'telemedicine_case_id' => $case->id,
                                            'telemedicine_case_code' => $case->code,
                                            'doctor_id' => $data['doctor_id'] ?? null,
                                            'flow' => 'same_registered_address',
                                            'job' => AssignedCase::class,
                                        ]);

                                        Notification::make()
                                            ->title('Paciente Asignado')
                                            ->body('El paciente ha sido asignado exitosamente.')
                                            ->success()
                                            ->send();
                                    }
                                }

                                /**
                                 * CASO 2: El paciente selecciono una ubicacion de la lista
                                 */
                                if ($data['feedback'] == false && $data['address_id'] != null) {

                                    /**Tomo la informacion de la tabla de ubicaciones registradas */
                                    $address = AnotherAddress::find($data['address_id']);

                                    $case = TelemedicineCase::create([

                                        'code' => UtilsController::generateCaseCode(),
                                        'telemedicine_patient_id' => $record->id,
                                        'telemedicine_doctor_id' => $data['doctor_id'],
                                        'patient_name' => $record->full_name,
                                        'patient_age' => $record->age,
                                        'patient_sex' => $record->sex,
                                        'patient_phone' => $address['phone_1'],
                                        'patient_phone_2' => $address['phone_2'],
                                        'patient_address' => $address['address'],
                                        'patient_country_id' => $address['country_id'],
                                        'patient_state_id' => $address['state_id'],
                                        'patient_city_id' => $address['city_id'],
                                        'reason' => $data['reason'],
                                        'ambulanceParking' => $data['ambulanceParking'],
                                        'status' => 'ASIGNADO',
                                        'assigned_by' => Auth::user()->name,
                                        'managed_by' => $doctor->managed_by,
                                        'supplier_id' => OperationsSupplierScope::resolveFromPatient($record),
                                    ]);

                                    if ($case) {

                                        $name_patient = $case['patient_name'];
                                        $name = $doctor->full_name;
                                        $phone = $doctor->phone;
                                        $address = $address['address'];
                                        $code = $case->code;
                                        $reason = $data['reason'];
                                        $email = $doctor->email;

                                        AssignedCase::dispatch($phone, $name, $code, $reason, $name_patient, $email, $address);

                                        SecurityAudit::log('AUDIT_OPERATIONS_TELEMEDICINE_CASE_ASSIGNED', 'operations.telemedicine-patients.assign-doctor', [
                                            'telemedicine_patient_id' => $record->id,
                                            'telemedicine_case_id' => $case->id,
                                            'telemedicine_case_code' => $case->code,
                                            'doctor_id' => $data['doctor_id'] ?? null,
                                            'flow' => 'selected_registered_address',
                                            'address_id' => $data['address_id'] ?? null,
                                            'job' => AssignedCase::class,
                                        ]);

                                        Notification::make()
                                            ->title('Paciente Asignado')
                                            ->body('El paciente ha sido asignado exitosamente.')
                                            ->success()
                                            ->send();
                                    }
                                }

                                /**
                                 * CASO 3: El paciente registro una NUEVA ubicacion
                                 */
                                if ($data['feedback'] == false && $data['address_id'] == null) {

                                    // ...Creo la nueva ubicacion en la tabla de ubicaciones
                                    $address = new AnotherAddress;
                                    $address->address = $data['address'];
                                    $address->phone_1 = $data['phone_1'];
                                    $address->phone_2 = $data['phone_2'];
                                    $address->city_id = $data['city_id'];
                                    $address->state_id = $data['state_id'];
                                    $address->country_id = $data['country_id'];
                                    $address->ambulanceParking = $data['ambulanceParking'];
                                    $address->relationship = $data['relationship'];
                                    $address->telemedicine_patient_id = $record->id;
                                    $address->save();

                                    $case = TelemedicineCase::create([

                                        'code' => UtilsController::generateCaseCode(),
                                        'telemedicine_patient_id' => $record->id,
                                        'telemedicine_doctor_id' => $data['doctor_id'],
                                        'patient_name' => $record->full_name,
                                        'patient_age' => $record->age,
                                        'patient_sex' => $record->sex,
                                        'patient_phone' => $address->phone_1,
                                        'patient_phone_2' => $address->phone_2,
                                        'patient_address' => $address->address,
                                        'patient_country_id' => $address->country_id,
                                        'patient_state_id' => $address->state_id,
                                        'patient_city_id' => $address->city_id,
                                        'reason' => $data['reason'],
                                        'ambulanceParking' => $data['ambulanceParking'],
                                        'status' => 'ASIGNADO',
                                        'assigned_by' => Auth::user()->name,
                                        'managed_by' => $doctor->managed_by,
                                        'supplier_id' => OperationsSupplierScope::resolveFromPatient($record),
                                    ]);

                                    if ($case) {

                                        $name_patient = $case['patient_name'];
                                        $name = $doctor->full_name;
                                        $phone = $doctor->phone;
                                        $newAddressId = $address->id;
                                        $address = $address->address;
                                        $code = $case->code;
                                        $reason = $data['reason'];
                                        $email = $doctor->email;

                                        AssignedCase::dispatch($phone, $name, $code, $reason, $name_patient, $email, $address);

                                        SecurityAudit::log('AUDIT_OPERATIONS_TELEMEDICINE_CASE_ASSIGNED', 'operations.telemedicine-patients.assign-doctor', [
                                            'telemedicine_patient_id' => $record->id,
                                            'telemedicine_case_id' => $case->id,
                                            'telemedicine_case_code' => $case->code,
                                            'doctor_id' => $data['doctor_id'] ?? null,
                                            'flow' => 'new_address',
                                            'new_address_id' => $newAddressId,
                                            'job' => AssignedCase::class,
                                        ]);

                                        Notification::make()
                                            ->title('Paciente Asignado')
                                            ->body('El paciente ha sido asignado exitosamente.')
                                            ->success()
                                            ->send();
                                    }
                                }

                            } catch (\Throwable $exception) {
                                SecurityAudit::log('AUDIT_OPERATIONS_TELEMEDICINE_CASE_ASSIGNMENT_FAILED', 'operations.telemedicine-patients.assign-doctor', [
                                    'telemedicine_patient_id' => $record->id,
                                    'patient_name' => $record->full_name,
                                    'doctor_id' => $data['doctor_id'] ?? null,
                                    'feedback' => $data['feedback'] ?? null,
                                    'address_id' => $data['address_id'] ?? null,
                                    'error' => $exception->getMessage(),
                                ]);

                                Notification::make()
                                    ->title('Asignación fallida')
                                    ->body('No se pudo asignar el caso. Intente nuevamente.')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->hidden(fn (TelemedicinePatient $record) => $record->managed_by == 'ATENMEDI' && ! in_array('ATENMEDI', Auth::user()->departament)),
                    // Action::make('assign_atenmedi')
                    //     ->label('Asignar ATENMEDI')
                    //     ->icon('heroicon-o-user')
                    //     ->color('primary')
                    //     ->requiresConfirmation()
                    //     ->modalWidth(Width::ThreeExtraLarge)
                    //     ->modalHeading('Asignación ATENMEDINA')
                    //     ->modalDescription('¿Está seguro de asignar el paciente a ATENMEDI?.')
                    //     ->modalIcon('heroicon-o-user')
                    //     ->action(function (TelemedicinePatient $record) {
                    //         try {
                    //             $record->managed_by = 'ATENMEDI';
                    //             $record->save();

                    //             SecurityAudit::log('AUDIT_OPERATIONS_TELEMEDICINE_PATIENT_ASSIGNED_ATENMEDI', 'operations.telemedicine-patients.assign-atenmedi', [
                    //                 'telemedicine_patient_id' => $record->id,
                    //                 'patient_name' => $record->full_name,
                    //                 'managed_by' => $record->managed_by,
                    //             ]);

                    //             Notification::make()
                    //                 ->title('Paciente Asignado')
                    //                 ->body('El paciente ha sido asignado a ATENMEDINA exitosamente.')
                    //                 ->success()
                    //                 ->send();
                    //         } catch (\Throwable $exception) {
                    //             SecurityAudit::log('AUDIT_OPERATIONS_TELEMEDICINE_PATIENT_ASSIGN_ATENMEDI_FAILED', 'operations.telemedicine-patients.assign-atenmedi', [
                    //                 'telemedicine_patient_id' => $record->id,
                    //                 'patient_name' => $record->full_name,
                    //                 'error' => $exception->getMessage(),
                    //             ]);

                    //             Notification::make()
                    //                 ->title('Asignación fallida')
                    //                 ->body('No se pudo asignar el paciente a ATENMEDINA.')
                    //                 ->danger()
                    //                 ->send();
                    //         }
                    //     })
                    //     ->hidden(fn (TelemedicinePatient $record) => $record->managed_by == 'ATENMEDI'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar Paciente')
                        ->color('danger')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar Paciente')
                        ->modalDescription('¿Está seguro de eliminar el paciente? Esta acción elimina la asociación del paciente. Para revertir deberá asociar nuevamente al afiliado.')
                        ->modalIcon('heroicon-o-trash')
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                Log::info('OPERACIONES: El usuario '.Auth::user()->name.' elimino el paciente: '.$record->full_name);
                                SecurityAudit::log('AUDIT_OPERATIONS_TELEMEDICINE_PATIENT_DELETED', 'operations.telemedicine-patients.bulk-delete', [
                                    'telemedicine_patient_id' => $record->id,
                                    'patient_name' => $record->full_name,
                                ]);
                                $record->delete();
                            }
                        }),
                ]),
            ])
            ->striped();
    }

    private static function supplierSummaryDescription(TelemedicinePatient $record): ?string
    {
        $supplierName = trim((string) ($record->supplier?->name ?? ''));

        if ($supplierName === '') {
            return 'Sin proveedor vinculado';
        }

        $managedBy = trim((string) ($record->managed_by ?? ''));

        if ($managedBy !== '') {
            return $supplierName.' ('.mb_strtoupper($managedBy).')';
        }

        return $supplierName;
    }

    private static function patientIdentificationDescription(TelemedicinePatient $record): ?string
    {
        $parts = array_values(array_filter([
            filled($record->nro_identificacion) ? 'C.I. '.$record->nro_identificacion : null,
            filled($record->phone) ? $record->phone : null,
        ]));

        if ($parts === []) {
            return null;
        }

        return implode(' · ', $parts);
    }

    /**
     * Asigna un color de badge estable según el texto (misma cadena = mismo color).
     *
     * @param  array<int, string>  $palette
     */
    private static function badgeColorFromString(?string $state, array $palette): string
    {
        if ($state === null || $state === '') {
            return 'gray';
        }

        $index = crc32(mb_strtolower($state)) % count($palette);

        return $palette[$index];
    }
}
