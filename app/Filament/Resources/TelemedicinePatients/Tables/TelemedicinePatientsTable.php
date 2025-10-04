<?php

namespace App\Filament\Resources\TelemedicinePatients\Tables;

use Carbon\Carbon;
use App\Models\City;
use App\Models\Plan;
use App\Models\State;
use App\Models\Region;
use App\Models\Country;
use App\Jobs\AssignedCase;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Mail\MailAssignedCase;
use App\Models\TelemedicineCase;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatient;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Radio;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\ColumnGroup;
use App\Http\Controllers\UtilsController;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Fieldset;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Collection;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Http\Controllers\NotificationController;
use App\Filament\Resources\Plans\Tables\PlansTable;
use App\Filament\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;

class TelemedicinePatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Listado de Pacientes')
            ->description('Tabla que muestra la lista de pacientes aifiliados y/o pacientes externos')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Paciente')
                    ->searchable(),
                TextColumn::make('businessUnit.definition')
                    ->label('Unidad de Negocios')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('businessLine.definition')
                    ->label('Linea de Servicio')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('type_affiliation')
                    ->label('Tipo de Afiliación')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('nro_identificacion')
                    ->label('Número de Identificación')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Fecha de Nacimiento')
                    ->searchable(),
                TextColumn::make('sex')
                    ->label('Sexo')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Número de Teléfono')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable(),
                ColumnGroup::make('Domicilio y Ubicación')->columns([
                    TextColumn::make('address')
                        ->label('Dirección')
                        ->searchable(),
                    TextColumn::make('country.name')
                        ->label('País')
                        ->searchable(),
                    TextColumn::make('city.definition')
                        ->label('Ciudad')
                        ->searchable(),
                    TextColumn::make('region')
                        ->label('Región')
                        ->searchable(),
                    TextColumn::make('state.definition')
                        ->label('Estado'),

                ]),
                ColumnGroup::make('Informacion de la Afiliación')->columns([
                    TextColumn::make('plan.description')
                        ->label('Plan')
                        ->badge()
                        ->color('success')
                        ->searchable()
                        ->action(
                            Action::make('view_plan')
                                ->label('Ver Plan')
                                ->modal()
                                ->modalHeading('Beneficios del Plan')
                                ->modalWidth('xl')
                                ->modalContent(function () {
                                    //quiero colocar un recurso de tabla en el modal
                                    
                                }),
                        ),
                    TextColumn::make('coverage.price')
                        ->label('Cobertura')
                        ->badge()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('code_affiliation')
                        ->label('Codigo')
                        ->badge()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('type_affiliation')
                        ->label('Tipo')
                        ->badge()
                        ->color('success')
                        ->searchable(),
                    TextColumn::make('status_affiliation')
                        ->label('Estatus')
                        ->badge()
                        ->color('success')
                        ->searchable(),
                ]),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('desde'),
                        DatePicker::make('hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Venta desde ' . Carbon::parse($data['desde'])->toFormattedDateString();
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Venta hasta ' . Carbon::parse($data['hasta'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    // ...
                    Action::make('view_history')
                        ->label('Historia Clínica')
                        ->icon('healthicons-f-cardiogram-e')
                        ->color('info')
                        ->url(fn(TelemedicinePatient $record): string => TelemedicineHistoryPatientResource::getUrl('create', ['record' => $record]),),
                    // ...
                    Action::make('asigned_doctor')
                        ->label('Asignar Doctor')
                        ->icon('healthicons-f-i-exam-qualification')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalWidth(Width::ThreeExtraLarge)
                        ->modalHeading('Asignación de Caso')
                        ->form([
                            Fieldset::make('Asignar Doctor')
                                ->schema([
                                    Select::make('doctor_id')
                                        ->label('Doctor')
                                        ->required()
                                        ->options(TelemedicineDoctor::all()->pluck('full_name', 'id')),
                                    Grid::make()
                                        ->schema([
                                            Textarea::make('reason')
                                                ->label('Motivo de la consulta')
                                                ->autosize()
                                                ->required()
                                                ->afterStateUpdated(function (Set $set, $state) {
                                                    $set('reason', strtoupper($state));
                                                })
                                                ->helperText('Escriba el motivo de la llamada del paciente. Por favor sea lo más especifico posible ya que el médico tomará esta información para determinar el tipo de atención que requiere el paciente.'),
                                        ])->columnSpanFull()->columns(1),
                                    Grid::make(1)
                                        ->schema([
                                            Radio::make('feedback')
                                                ->label('¿La ubicación actual del paciente es la registrada en el sistema?')
                                                ->default(true)
                                                ->live()
                                                ->boolean()
                                                ->inline()
                                                ->inlineLabel(false)
                                        ])->columnSpanFull()->hiddenOn('edit'),

                                ])->columnSpanFull()->columns(1),
                            Section::make()
                                ->hidden(fn(Get $get) => $get('feedback'))
                                ->heading('Ubicación Actual del Paciente')
                                ->description('La ubicación actual permite coordinar un servicio IN SITU. No afecta los datos registrados del afiliado.')
                                ->schema([
                                    TextInput::make('other_phone')
                                        ->label('Número de Teléfono'),

                                    Select::make('other_country_id')
                                        ->label('País')
                                        ->live()
                                        ->options(Country::all()->pluck('name', 'id'))
                                        ->searchable()
                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Campo Requerido',
                                        ])
                                        ->default(189)
                                        ->preload(),
                                    Select::make('other_state_id')
                                        ->label('Estado')
                                        ->options(function (Get $get) {
                                            return State::where('country_id', $get('other_country_id'))->pluck('definition', 'id');
                                        })
                                        ->live()
                                        ->searchable()
                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Campo Requerido',
                                        ])
                                        ->preload(),
                                    Select::make('other_city_id')
                                        ->label('Ciudad')
                                        ->options(function (Get $get) {
                                            return City::where('country_id', $get('other_country_id'))->where('state_id', $get('other_state_id'))->pluck('definition', 'id');
                                        })
                                        ->searchable()
                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Campo Requerido',
                                        ])
                                        ->preload(),
                                    Grid::make()
                                        ->schema([
                                            Textarea::make('other_address')
                                                ->label('Dirección Exacta')
                                                ->autosize()
                                                ->required()
                                                ->afterStateUpdated(function (Set $set, $state) {
                                                    $set('other_address', strtoupper($state));
                                                })
                                                ->helperText('Redacte la ubicación exacta del paciente, describa esquinas, calle, edificio, piso en el edificio, casa, número de la casa, y puntos de referencia. Por favor sea lo más específico posible.'),
                                        ])->columnSpanFull()->columns(1),
                                ])->columnSpanFull()->columns(2)
                        ])
                        ->action(function (TelemedicinePatient $record, array $data) {
                            // ...
                            if ($data['feedback'] == true) {

                                $case = TelemedicineCase::create([
                                    'code'                      => random_int(11111, 99999),
                                    'telemedicine_patient_id'   => $record->id,
                                    'telemedicine_doctor_id'    => $data['doctor_id'],
                                    'patient_name'              => $record->full_name,
                                    'patient_age'               => $record->age,
                                    'patient_sex'               => $record->sex,
                                    'patient_phone'             => $record->phone,
                                    'patient_address'           => $record->address,
                                    'patient_country_id'        => $record->country_id,
                                    'patient_state_id'          => $record->state_id,
                                    'patient_city_id'           => $record->city_id,
                                    'reason'                    => $data['reason'],
                                    'status'                    => 'ASIGNADO',
                                    'assigned_by'               => Auth::user()->name,
                                ]);


                                $doctor         = TelemedicineDoctor::find($data['doctor_id'])->first();
                                $name_patient   = $case['patient_name'];
                                $name           = $doctor->full_name;
                                $phone          = $doctor->phone;
                                $code           = $case->code;
                                $reason         = $data['reason'];
                                $email          = $doctor->email;

                                AssignedCase::dispatch($phone, $name, $code, $reason, $name_patient, $email);

                                if ($case) {
                                    Notification::make()
                                        ->title('Paciente Asignado')
                                        ->body('El paciente ha sido asignado exitosamente.')
                                        ->success()
                                        ->send();
                                }
                            } else {

                                $case = TelemedicineCase::create([
                                    'code'                      => random_int(11111, 99999),
                                    'telemedicine_patient_id'   => $record->id,
                                    'telemedicine_doctor_id'    => $data['doctor_id'],
                                    'patient_name'              => $record->full_name,
                                    'patient_age'               => $record->age,
                                    'patient_sex'               => $record->sex,
                                    'patient_phone'             => $data['other_phone'],
                                    'patient_address'           => $data['other_address'],
                                    'patient_country_id'        => $data['other_country_id'],
                                    'patient_state_id'          => $data['other_state_id'],
                                    'patient_city_id'           => $data['other_city_id'],
                                    'reason'                    => $data['reason'],
                                    'status'                    => 'ASIGNADO',
                                    'assigned_by'               => Auth::user()->name,
                                ]);

                                if ($case) {
                                    Notification::make()
                                        ->title('Paciente Asignado')
                                        ->body('El paciente ha sido asignado exitosamente.')
                                        ->success()
                                        ->send();
                                }
                            }
                        })
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DeleteBulkAction::make(),
                    BulkAction::make('asigned_doctor')
                        ->label('Asignar Doctor')
                        ->icon('healthicons-f-i-exam-qualification')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalWidth(Width::ThreeExtraLarge)
                        ->modalHeading('Asignación de Caso')
                    ->form([
                        Fieldset::make('Asignar Doctor')
                            ->schema([
                                Select::make('doctor_id')
                                    ->label('Doctor')
                                    ->required()
                                    ->options(TelemedicineDoctor::all()->pluck('full_name', 'id')),
                                Grid::make()
                                    ->schema([
                                        Textarea::make('reason')
                                            ->label('Motivo de la consulta')
                                            ->autosize()
                                            ->required()
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $set('reason', strtoupper($state));
                                            })
                                            ->helperText('Escriba el motivo de la llamada del paciente. Por favor sea lo más especifico posible ya que el médico tomará esta información para determinar el tipo de atención que requiere el paciente.'),
                                    ])->columnSpanFull()->columns(1),
                                Grid::make(1)
                                    ->schema([
                                        Radio::make('feedback')
                                            ->label('¿La ubicación actual del paciente es la registrada en el sistema?')
                                            ->default(true)
                                            ->live()
                                            ->boolean()
                                            ->inline()
                                            ->inlineLabel(false)
                                    ])->columnSpanFull()->hiddenOn('edit'),

                            ])->columnSpanFull()->columns(1),
                        Section::make()
                            ->hidden(fn(Get $get) => $get('feedback'))
                            ->heading('Ubicación Actual del Paciente')
                            ->description('La ubicación actual permite coordinar un servicio IN SITU. No afecta los datos registrados del afiliado.')
                            ->schema([
                                TextInput::make('other_phone')
                                    ->label('Número de Teléfono'),

                                Select::make('other_country_id')
                                    ->label('País')
                                    ->live()
                                    ->options(Country::all()->pluck('name', 'id'))
                                    ->searchable()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Requerido',
                                    ])
                                    ->default(189)
                                    ->preload(),
                                Select::make('other_state_id')
                                    ->label('Estado')
                                    ->options(function (Get $get) {
                                        return State::where('country_id', $get('other_country_id'))->pluck('definition', 'id');
                                    })
                                    ->live()
                                    ->searchable()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Requerido',
                                    ])
                                    ->preload(),
                                Select::make('other_city_id')
                                    ->label('Ciudad')
                                    ->options(function (Get $get) {
                                        return City::where('country_id', $get('other_country_id'))->where('state_id', $get('other_state_id'))->pluck('definition', 'id');
                                    })
                                    ->searchable()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Requerido',
                                    ])
                                    ->preload(),
                                Grid::make()
                                    ->schema([
                                        Textarea::make('other_address')
                                            ->label('Dirección Exacta')
                                            ->autosize()
                                            ->required()
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $set('other_address', strtoupper($state));
                                            })
                                            ->helperText('Redacte la ubicación exacta del paciente, describa esquinas, calle, edificio, piso en el edificio, casa, número de la casa, y puntos de referencia. Por favor sea lo más específico posible.'),
                                    ])->columnSpanFull()->columns(1),
                            ])->columnSpanFull()->columns(2)
                    ])
                    ->action(function (Collection $records, array $data) {
                        // ...
                        $record = $records->toArray();

                        for ($i = 0; $i < count($record); $i++) {
                            if ($data['feedback'] == true) {

                                $case = TelemedicineCase::create([
                                    'code'                      => UtilsController::generateCaseCode(),
                                    'telemedicine_patient_id'   => $record[$i]['id'],
                                    'telemedicine_doctor_id'    => $data['doctor_id'],
                                    'patient_name'              => $record[$i]['full_name'],
                                    'patient_age'               => $record[$i]['age'],
                                    'patient_sex'               => $record[$i]['sex'],
                                    'patient_phone'             => $record[$i]['phone'],
                                    'patient_address'           => $record[$i]['address'],
                                    'patient_country_id'        => $record[$i]['country_id'],
                                    'patient_state_id'          => $record[$i]['state_id'],
                                    'patient_city_id'           => $record[$i]['city_id'],
                                    'reason'                    => $data['reason'],
                                    'status'                    => 'ASIGNADO',
                                    'assigned_by'               => Auth::user()->name,
                                ]);
    
                                if ($case) {

                                    $doctor         = TelemedicineDoctor::find($data['doctor_id'])->first();
                                    $name_patient   = $case['patient_name'];
                                    $name           = $doctor->full_name;
                                    $phone          = $doctor->phone;
                                    $code           = $case->code;
                                    $reason         = $data['reason'];
                                    $email          = $doctor->email;
        
                                    AssignedCase::dispatch($phone, $name, $code, $reason, $name_patient, $email);
                                    
                                    Notification::make()
                                        ->title('Paciente Asignado')
                                        ->body('El paciente ha sido asignado exitosamente.')
                                        ->success()
                                        ->send();
                                }
                            } else {
    
                                $case = TelemedicineCase::create([
                                    
                                    'code'                      => UtilsController::generateCaseCode(),
                                    'telemedicine_patient_id'   => $record[0]['id'],
                                    'telemedicine_doctor_id'    => $data['doctor_id'],
                                    'patient_name'              => $record[0]['full_name'],
                                    'patient_age'               => $record[0]['age'],
                                    'patient_sex'               => $record[0]['sex'],
                                    'patient_phone'             => $data['other_phone'],
                                    'patient_address'           => $data['other_address'],
                                    'patient_country_id'        => $data['other_country_id'],
                                    'patient_state_id'          => $data['other_state_id'],
                                    'patient_city_id'           => $data['other_city_id'],
                                    'reason'                    => $data['reason'],
                                    'status'                    => 'ASIGNADO',
                                    'assigned_by'               => Auth::user()->name,
                                ]);
    
                                if ($case) {

                                    $doctor         = TelemedicineDoctor::find($data['doctor_id'])->first();
                                    $name_patient   = $case['patient_name'];
                                    $name           = $doctor->full_name;
                                    $phone          = $doctor->phone;
                                    $code           = $case->code;
                                    $reason         = $data['reason'];
                                    $email          = $doctor->email;
        
                                    AssignedCase::dispatch($phone, $name, $code, $reason, $name_patient, $email);

                                    Notification::make()
                                        ->title('Paciente Asignado')
                                        ->body('El paciente ha sido asignado exitosamente.')
                                        ->success()
                                        ->send();
                                }
                            }
                            
                        }
                    })
                ]),
            ]);
    }
}