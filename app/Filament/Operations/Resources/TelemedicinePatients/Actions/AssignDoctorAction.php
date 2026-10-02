<?php

namespace App\Filament\Operations\Resources\TelemedicinePatients\Actions;

use App\Jobs\AssignedCase;
use App\Models\AnotherAddress;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatient;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\TelemedicineCaseFactory;
use App\Support\Telemedicine\TelemedicineMedicalTeam;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
                        Toggle::make('assign_to_medical_team')
                            ->label('Asignar al Equipo Médico')
                            ->helperText('El caso entra al panel de telemedicina sin médico particular y lo toma cualquier médico de guardia del equipo.')
                            ->default(false)
                            ->live()
                            ->visible(fn (): bool => TelemedicineMedicalTeam::optionsForCurrentUser() !== []),
                        Select::make('medical_team')
                            ->label('Equipo médico')
                            ->placeholder('Seleccione el equipo de guardia')
                            ->options(fn (): array => TelemedicineMedicalTeam::optionsForCurrentUser())
                            ->default(function (): ?string {
                                $options = TelemedicineMedicalTeam::optionsForCurrentUser();

                                return count($options) === 1 ? (string) array_key_first($options) : null;
                            })
                            ->required()
                            ->validationMessages([
                                'required' => 'Seleccione el equipo médico que recibirá el caso.',
                            ])
                            ->helperText('TDG o el proveedor cuyos médicos atenderán el caso.')
                            ->visible(fn (Get $get): bool => (bool) $get('assign_to_medical_team')),
                        Select::make('doctor_id')
                            ->label('Doctor')
                            ->required()
                            ->hidden(fn (Get $get): bool => (bool) $get('assign_to_medical_team'))
                            ->live()
                            ->searchable()
                            ->helperText('Analistas TDG ven todos los médicos registrados (TDG y proveedores). Entre paréntesis: proveedor y grupo.')
                            ->options(function (?TelemedicinePatient $record): array {
                                $departments = Auth::user()?->departament ?? [];
                                $isTdgAnalyst = OperationsSupplierScope::authenticatedUserIsTdgAnalyst();

                                $doctorQuery = TelemedicineDoctor::query()
                                    ->with('supplier:id,name')
                                    ->orderBy('full_name');

                                // Analistas TDG: todos los médicos registrados (TDG o cualquier proveedor).
                                // Usuarios de proveedor / no TDG: se mantienen los filtros por supplier.
                                if (! $isTdgAnalyst) {
                                    OperationsSupplierScope::applyToQuery($doctorQuery);

                                    if (OperationsSupplierScope::currentSupplierId() === null && filled($record?->supplier_id)) {
                                        $doctorQuery->where('supplier_id', $record->supplier_id);
                                    }
                                }

                                if (in_array('ATENMEDI', $departments, true)) {
                                    $doctorQuery->where('managed_by', 'ATENMEDI');
                                }

                                return $doctorQuery
                                    ->get()
                                    ->mapWithKeys(function (TelemedicineDoctor $doctor): array {
                                        $group = filled($doctor->managed_by) ? (string) $doctor->managed_by : 'Sin grupo';
                                        $supplierName = trim((string) ($doctor->supplier?->name ?? ''));
                                        $context = $supplierName !== ''
                                            ? sprintf('%s (%s)', $supplierName, $group)
                                            : $group;

                                        return [
                                            $doctor->id => sprintf('%s (%s)', $doctor->full_name, $context),
                                        ];
                                    })
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
                            ->visible(fn (Get $get): bool => OperationsSupplierScope::authenticatedUserIsTdgAnalyst()
                                && ! $get('assign_to_medical_team')),
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

                                $parking = AnotherAddress::where('telemedicine_patient_id', $record->id)->where('id', $get('address_id'))->value('ambulanceParking');

                                return match (true) {
                                    $parking === null => 'No se registró si la dirección posee estacionamiento para ambulancias',
                                    (bool) $parking => 'La Dirección SI posee estacionamiento para ambulancias',
                                    default => 'La Dirección NO posee estacionamiento para ambulancias',
                                };
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
                    $assignToTeam = (bool) ($data['assign_to_medical_team'] ?? false);
                    $doctor = null;

                    if ($assignToTeam) {
                        $team = TelemedicineMedicalTeam::resolveForCurrentUser($data['medical_team'] ?? null);

                        if ($team === null) {
                            Notification::make()
                                ->title('Equipo médico no válido')
                                ->body('Seleccione un equipo médico de la lista. No se creó el caso.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $assignment = [
                            'telemedicine_doctor_id' => null,
                            'assigned_to_medical_team' => true,
                            'medical_team_supplier_id' => $team['supplier_id'],
                            'managed_by' => $team['managed_by'],
                            'belongs_to' => null,
                        ];
                        $successMessage = 'El caso fue asignado al '.(TelemedicineMedicalTeam::optionsForCurrentUser()[(string) $data['medical_team']] ?? TelemedicineMedicalTeam::LABEL).'. Lo tomará el médico de guardia.';
                    } else {
                        $doctor = TelemedicineDoctor::query()->findOrFail($data['doctor_id']);

                        $assignment = [
                            'telemedicine_doctor_id' => $doctor->id,
                            'assigned_to_medical_team' => false,
                            'medical_team_supplier_id' => null,
                            'managed_by' => $doctor->managed_by,
                            'belongs_to' => $data['belongs_to'] ?? null,
                        ];
                        $successMessage = 'El paciente ha sido asignado exitosamente.';
                    }

                    $teamAudit = $assignToTeam ? [
                        'medical_team' => (string) $data['medical_team'],
                        'medical_team_supplier_id' => $assignment['medical_team_supplier_id'],
                    ] : [];

                    SecurityAudit::log('AUDIT_OPERATIONS_TELEMEDICINE_CASE_ASSIGNMENT_STARTED', 'operations.telemedicine-patients.assign-doctor', [
                        'telemedicine_patient_id' => $record->id,
                        'patient_name' => $record->full_name,
                        'doctor_id' => $data['doctor_id'] ?? null,
                        'feedback' => $data['feedback'] ?? null,
                        'address_id' => $data['address_id'] ?? null,
                        ...$teamAudit,
                    ]);
                    /**
                     * CASO 1: El paciente tiene la misma ubicacion que la registrada en la afiliacion
                     */
                    if ($data['feedback'] == true) {

                        $case = TelemedicineCaseFactory::createForPatient($record, [
                            ...$assignment,
                            'reason' => $data['reason'],
                            'ambulanceParking' => $data['ambulanceParking'],
                            'assigned_by' => Auth::user()->name,
                            'supplier_id' => OperationsSupplierScope::resolveFromPatient($record),
                        ]);

                        if ($case) {

                            $name_patient = $case['patient_name'];
                            $name = $doctor?->full_name;
                            $phone = $doctor?->phone;
                            $address = $record->address;
                            $code = $case->code;
                            $reason = $data['reason'];
                            $email = $doctor?->email;

                            // ...Asignado al equipo: sin notificación, el caso aparece en el escritorio del médico de guardia.
                            if ($doctor !== null) {
                                AssignedCase::dispatch($phone, $name, $code, $reason, $name_patient, $email, $address);
                            }

                            SecurityAudit::log('AUDIT_OPERATIONS_TELEMEDICINE_CASE_ASSIGNED', 'operations.telemedicine-patients.assign-doctor', [
                                'telemedicine_patient_id' => $record->id,
                                'telemedicine_case_id' => $case->id,
                                'telemedicine_case_code' => $case->code,
                                'doctor_id' => $data['doctor_id'] ?? null,
                                'flow' => 'same_registered_address',
                                'job' => $doctor !== null ? AssignedCase::class : null,
                                ...$teamAudit,
                            ]);

                            Notification::make()
                                ->title('Paciente Asignado')
                                ->body($successMessage)
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

                        $case = TelemedicineCaseFactory::createForPatient($record, [
                            ...$assignment,
                            'patient_phone' => $address['phone_1'],
                            'patient_phone_2' => $address['phone_2'],
                            'patient_address' => $address['address'],
                            'patient_country_id' => $address['country_id'],
                            'patient_state_id' => $address['state_id'],
                            'patient_city_id' => $address['city_id'],
                            'reason' => $data['reason'],
                            'ambulanceParking' => $data['ambulanceParking'],
                            'assigned_by' => Auth::user()->name,
                            'supplier_id' => OperationsSupplierScope::resolveFromPatient($record),
                        ]);

                        if ($case) {

                            $name_patient = $case['patient_name'];
                            $name = $doctor?->full_name;
                            $phone = $doctor?->phone;
                            $address = $address['address'];
                            $code = $case->code;
                            $reason = $data['reason'];
                            $email = $doctor?->email;

                            // ...Asignado al equipo: sin notificación, el caso aparece en el escritorio del médico de guardia.
                            if ($doctor !== null) {
                                AssignedCase::dispatch($phone, $name, $code, $reason, $name_patient, $email, $address);
                            }

                            SecurityAudit::log('AUDIT_OPERATIONS_TELEMEDICINE_CASE_ASSIGNED', 'operations.telemedicine-patients.assign-doctor', [
                                'telemedicine_patient_id' => $record->id,
                                'telemedicine_case_id' => $case->id,
                                'telemedicine_case_code' => $case->code,
                                'doctor_id' => $data['doctor_id'] ?? null,
                                'flow' => 'selected_registered_address',
                                'address_id' => $data['address_id'] ?? null,
                                'job' => $doctor !== null ? AssignedCase::class : null,
                                ...$teamAudit,
                            ]);

                            Notification::make()
                                ->title('Paciente Asignado')
                                ->body($successMessage)
                                ->success()
                                ->send();
                        }
                    }

                    /**
                     * CASO 3: El paciente registro una NUEVA ubicacion
                     */
                    if ($data['feedback'] == false && $data['address_id'] == null) {

                        // ...La ubicacion y el caso se guardan juntos: si el caso falla, no queda una ubicacion huerfana.
                        [$address, $case] = DB::transaction(function () use ($record, $data, $assignment): array {
                            $address = new AnotherAddress;
                            $address->address = $data['address'];
                            $address->phone_1 = $data['phone_1'];
                            $address->phone_2 = filled($data['phone_2'] ?? null) ? $data['phone_2'] : null;
                            $address->city_id = $data['city_id'];
                            $address->state_id = $data['state_id'];
                            $address->country_id = $data['country_id'];
                            $address->ambulanceParking = (bool) ($data['ambulanceParking'] ?? false);
                            $address->relationship = $data['relationship'] ?? null;
                            $address->telemedicine_patient_id = $record->id;
                            $address->save();

                            $case = TelemedicineCaseFactory::createForPatient($record, [
                                ...$assignment,
                                'patient_phone' => $address->phone_1,
                                'patient_phone_2' => $address->phone_2,
                                'patient_address' => $address->address,
                                'patient_country_id' => $address->country_id,
                                'patient_state_id' => $address->state_id,
                                'patient_city_id' => $address->city_id,
                                'reason' => $data['reason'],
                                'ambulanceParking' => $data['ambulanceParking'],
                                'assigned_by' => Auth::user()->name,
                                'supplier_id' => OperationsSupplierScope::resolveFromPatient($record),
                            ]);

                            return [$address, $case];
                        });

                        if ($case) {

                            $name_patient = $case['patient_name'];
                            $name = $doctor?->full_name;
                            $phone = $doctor?->phone;
                            $newAddressId = $address->id;
                            $address = $address->address;
                            $code = $case->code;
                            $reason = $data['reason'];
                            $email = $doctor?->email;

                            // ...Asignado al equipo: sin notificación, el caso aparece en el escritorio del médico de guardia.
                            if ($doctor !== null) {
                                AssignedCase::dispatch($phone, $name, $code, $reason, $name_patient, $email, $address);
                            }

                            SecurityAudit::log('AUDIT_OPERATIONS_TELEMEDICINE_CASE_ASSIGNED', 'operations.telemedicine-patients.assign-doctor', [
                                'telemedicine_patient_id' => $record->id,
                                'telemedicine_case_id' => $case->id,
                                'telemedicine_case_code' => $case->code,
                                'doctor_id' => $data['doctor_id'] ?? null,
                                'flow' => 'new_address',
                                'new_address_id' => $newAddressId,
                                'job' => $doctor !== null ? AssignedCase::class : null,
                                ...$teamAudit,
                            ]);

                            Notification::make()
                                ->title('Paciente Asignado')
                                ->body($successMessage)
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
