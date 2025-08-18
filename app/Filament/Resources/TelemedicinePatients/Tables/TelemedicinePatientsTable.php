<?php

namespace App\Filament\Resources\TelemedicinePatients\Tables;

use Carbon\Carbon;
use App\Models\City;
use App\Models\State;
use App\Models\Region;
use App\Models\Country;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\TelemedicineCase;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatient;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Radio;
use Illuminate\Support\Facades\Auth;
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
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Fieldset;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Filament\Resources\TelemedicineHistoryPatients\TelemedicineHistoryPatientResource;

class TelemedicinePatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->searchable(),
                TextColumn::make('nro_identificacion')
                    ->searchable(),
                TextColumn::make('date_birth')
                    ->searchable(),
                TextColumn::make('sex')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('address')
                    ->searchable(),
                TextColumn::make('city_id')
                    ->searchable(),
                TextColumn::make('country_id')
                    ->searchable(),
                TextColumn::make('region')
                    ->searchable(),
                TextColumn::make('state_id')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                        ->url(fn (TelemedicinePatient $record): string => TelemedicineHistoryPatientResource::getUrl('create', ['record' => $record]),),
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
                            if($data['feedback'] == true){
                                
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

                                if($case){
                                    Notification::make()
                                    ->title('Paciente Asignado')
                                    ->body('El paciente ha sido asignado exitosamente.')
                                    ->success()
                                    ->send();
                                }
                                
                                }else{
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
                ]),
            ]);
    }
}