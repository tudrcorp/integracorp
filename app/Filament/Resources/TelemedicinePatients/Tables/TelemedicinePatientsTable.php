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
use Filament\Support\RawJs;
use Filament\Actions\Action;
use App\Mail\MailAssignedCase;
use App\Models\AnotherAddress;
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
use Filament\Forms\Components\Checkbox;
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
                TextColumn::make('name_corporate')
                    ->label('Nombre Corporativo')
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
                        ->label('Dirección Principal')
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
                            
                            //...Informacion del Doctor
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
                                        ->hidden(fn(Get $get) => !$get('feedback')),
                                    Textarea::make('directionAmbulance')
                                        ->label('Dirección alternativa del Estacionamiento para Ambulancias')
                                        ->autosize()
                                        ->hidden(fn(Get $get) => $get('ambulanceParking'))
                                ])->columnSpanFull()->hiddenOn('edit'),
                            ])->columnSpanFull()->columns(1),

                            //... Lista de ubicaciones ya registradas
                            Fieldset::make('Lista de ubicaciones registradas por el paciente')
                            ->hidden(fn(Get $get) => $get('feedback'))
                            ->schema([
                                Select::make('address_id')
                                    ->label('Ubicación')
                                    ->live()
                                    ->options(function ($record, Get $get) {
                                        return AnotherAddress::where('telemedicine_patient_id', $record->id)->pluck('address', 'id');
                                    })
                                    ->helperText(function ($record, Get $get, $state) {
                                        if($state == null){
                                            return '';
                                        }   
                                        return AnotherAddress::where('telemedicine_patient_id', $record->id)->where('id', $get('address_id'))->first()->ambulanceParking == true ? 'La Dirección SI posee estacionamiento para ambulancias' : 'La Dirección NO posee estacionamiento para ambulancias';
                                    }),
                                Checkbox::make('new_address')
                                    ->inline()
                                    ->live()
                                    ->label('Nueva Ubicación')
                                    ->default(false)
                            ])->columnSpanFull()->columns(1),

                            //...SECCION NUEVA UBICACION
                            Section::make()
                            ->hidden(fn(Get $get) => !$get('new_address'))
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
                                        'required'  => 'Campo Requerido',
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
                                        'required'  => 'Campo Requerido',
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
                                        'required'  => 'Campo Requerido',
                                    ])
                                    ->preload(),
                                TextInput::make('phone_1')
                                    ->label('Número de Teléfono Principal')
                                    ->tel()
                                    ->mask(fn(Get $get) => $get('country_id') == 189 ? '99999999999' : '')
                                    ->required()
                                    ->helperText('Ejemplo: 04161234567'),
                                TextInput::make('phone_2')
                                    ->label('Número de Teléfono Alternativo')
                                    ->tel()
                                    ->mask(fn(Get $get) => $get('country_id') == 189 ? '99999999999' : '')
                                    ->helperText('Ejemplo: 04161234567'),
                                Select::make('relationship')
                                    ->label('Parentesco')
                                    ->options([
                                        'TITULAR'   => 'TITULAR',
                                        'MADRE'     => 'MADRE',
                                        'PADRE'     => 'PADRE',
                                        'HIJO(A)'   => 'HIJO(A)',
                                        'ABUELO(A)' => 'ABUELO(A)',
                                        'AMIGO(A)'  => 'AMIGO(A)',
                                        'OTRO'      => 'OTRO',
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
                                            ->hidden(fn(Get $get) => $get('ambulanceParking'))
                                        ])->columnSpanFull()->columns(1),
                            ])->columnSpanFull()->columns(2)
                            
                        ])
                        ->action(function (TelemedicinePatient $record, array $data) {
                            /**
                             * CASO 1: El paciente tiene la misma ubicacion que la registrada en la afiliacion
                             */
                            if ($data['feedback'] == true) {

                                $case = TelemedicineCase::create([
                                    'code'                      => UtilsController::generateCaseCode(),
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
                                    'ambulanceParking'         => $data['ambulanceParking'],
                                    'status'                    => 'ASIGNADO',
                                    'assigned_by'               => Auth::user()->name,
                                ]);

                                if ($case) {

                                    $doctor         = TelemedicineDoctor::find($data['doctor_id'])->first();
                                    $name_patient   = $case['patient_name'];
                                    $name           = $doctor->full_name;
                                    $phone          = $doctor->phone;
                                    $address        = $record->address;
                                    $code           = $case->code;
                                    $reason         = $data['reason'];
                                    $email          = $doctor->email;

                                    AssignedCase::dispatch($phone, $name, $code, $reason, $name_patient, $email, $address);

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
                                $address = AnotherAddress::find($data['address_id'])->first();

                                $case = TelemedicineCase::create([

                                    'code'                      => UtilsController::generateCaseCode(),
                                    'telemedicine_patient_id'   => $record->id,
                                    'telemedicine_doctor_id'    => $data['doctor_id'],
                                    'patient_name'              => $record->full_name,
                                    'patient_age'               => $record->age,
                                    'patient_sex'               => $record->sex,
                                    'patient_phone'             => $address['phone_1'],
                                    'patient_phone_2'           => $address['phone_2'],
                                    'patient_address'           => $address['address'],
                                    'patient_country_id'        => $address['country_id'],
                                    'patient_state_id'          => $address['state_id'],
                                    'patient_city_id'           => $address['city_id'],
                                    'reason'                    => $data['reason'],
                                    'ambulanceParking'         => $data['ambulanceParking'],
                                    'status'                    => 'ASIGNADO',
                                    'assigned_by'               => Auth::user()->name,
                                ]);

                                if ($case) {

                                    $doctor         = TelemedicineDoctor::find($data['doctor_id'])->first();
                                    $name_patient   = $case['patient_name'];
                                    $name           = $doctor->full_name;
                                    $phone          = $doctor->phone;
                                    $address        = $address['address'];
                                    $code           = $case->code;
                                    $reason         = $data['reason'];
                                    $email          = $doctor->email;

                                    AssignedCase::dispatch($phone, $name, $code, $reason, $name_patient, $email, $address);

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
                                
                                //...Creo la nueva ubicacion en la tabla de ubicaciones
                                $address = new AnotherAddress();
                                $address->address                   = $data['address'];
                                $address->phone_1                   = $data['phone_1'];
                                $address->phone_2                   = $data['phone_2'];
                                $address->city_id                   = $data['city_id'];
                                $address->state_id                  = $data['state_id'];
                                $address->country_id                = $data['country_id'];
                                $address->ambulanceParking          = $data['ambulanceParking'];
                                $address->relationship              = $data['relationship']; 
                                $address->telemedicine_patient_id   = $record->id;
                                $address->save();
                                
                                $case = TelemedicineCase::create([

                                    'code'                      => UtilsController::generateCaseCode(),
                                    'telemedicine_patient_id'   => $record->id,
                                    'telemedicine_doctor_id'    => $data['doctor_id'],
                                    'patient_name'              => $record->full_name,
                                    'patient_age'               => $record->age,
                                    'patient_sex'               => $record->sex,
                                    'patient_phone'             => $address->phone_1,
                                    'patient_phone_2'           => $address->phone_2,
                                    'patient_address'           => $address->address,
                                    'patient_country_id'        => $address->country_id,
                                    'patient_state_id'          => $address->state_id,
                                    'patient_city_id'           => $address->city_id,
                                    'reason'                    => $data['reason'],
                                    'ambulanceParking'         => $data['ambulanceParking'],
                                    'status'                    => 'ASIGNADO',
                                    'assigned_by'               => Auth::user()->name,
                                ]);

                                if ($case) {

                                    $doctor         = TelemedicineDoctor::find($data['doctor_id'])->first();
                                    $name_patient   = $case['patient_name'];
                                    $name           = $doctor->full_name;
                                    $phone          = $doctor->phone;
                                    $address        = $address->address;
                                    $code           = $case->code;
                                    $reason         = $data['reason'];
                                    $email          = $doctor->email;

                                    AssignedCase::dispatch($phone, $name, $code, $reason, $name_patient, $email, $address);

                                    Notification::make()
                                        ->title('Paciente Asignado')
                                        ->body('El paciente ha sido asignado exitosamente.')
                                        ->success()
                                        ->send();
                                }
                            }
                            
                        })
                ])
            ]);
    }
}