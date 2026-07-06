<?php

namespace App\Filament\Operations\Resources\TelemedicinePatients\Actions;

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
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;

class AssignDoctorAction
{
    public static function make(): Action
    {
        return Action::make('asigned_doctor')
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
                            ->live()
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
                        Select::make('belongs_to')
                            ->label('Pertenece a?')
                            ->options(function (Get $get): array {
                                $options = [
                                    'Diagnomovil' => 'Diagnomovil',
                                    'Centro Diagnostico 3 de Febrero' => 'Centro Diagnostico 3 de Febrero',
                                ];

                                $doctorId = $get('doctor_id');

                                if (filled($doctorId)) {
                                    $doctorSupplierName = TelemedicineDoctor::with('supplier')
                                        ->find($doctorId)?->supplier?->name;

                                    if (filled($doctorSupplierName)) {
                                        $options[$doctorSupplierName] = $doctorSupplierName;
                                    }
                                }

                                return $options;
                            })
                            ->searchable()
                            ->required()
                            ->visible(fn (): bool => OperationsSupplierScope::authenticatedUserIsTdgAnalyst()),
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
                            'belongs_to' => $data['belongs_to'] ?? null,
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
                            'belongs_to' => $data['belongs_to'] ?? null,
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
                            'belongs_to' => $data['belongs_to'] ?? null,
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
            ->hidden(fn (TelemedicinePatient $record) => $record->managed_by == 'ATENMEDI' && ! in_array('ATENMEDI', Auth::user()->departament));
    }
}
