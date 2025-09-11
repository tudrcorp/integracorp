<?php

namespace App\Filament\Resources\CheckAffiliations\Tables;

use App\Models\Fee;
use App\Models\Plan;
use App\Models\Agent;
use App\Models\Agency;
use App\Models\AgeRange;
use App\Models\Coverage;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\CorporateQuote;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Radio;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Illuminate\Database\Eloquent\Collection;
use Filament\Schemas\Components\Utilities\Get;
use App\Http\Controllers\MigrationHistoricalController;

class CheckAffiliationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
            TextColumn::make('cobertura')
                ->searchable(isIndividual: true),
            TextColumn::make('tomador')
                ->searchable(isIndividual: true),
            TextColumn::make('tipo_plan')
                ->searchable(isIndividual: true)
                ->badge()
                ->colors([
                    'success' => 'Especial',
                    'warning' => 'Ideal',
                    'gray' => '',
                ]),
            TextColumn::make('edad')
                ->sortable()
                ->searchable(),
            TextColumn::make('status_migration')
                ->label('Estatus Migración')
                ->extraCellAttributes(function ($record) {
                    if($record->status_migration == 'PROCESADO') {
                        return [
                            'class' => 'bg-red-500 font-bold text-white text-center'
                        ];
                    }
                    if ($record->status_migration == 'PENDIENTE POR MIGRAR') {
                        return [
                            'class' => 'bg-green-500 font-bold text-white text-center'
                        ];
                    }
                    return [];
                })
                ->searchable(),
                TextColumn::make('fecha_emision')
                    ->searchable(),
                TextColumn::make('codigo_tdec')
                    ->searchable(),
                
                TextColumn::make('proveedor')
                    ->searchable(),
                TextColumn::make('nro_vaucher')
                    ->searchable(),
                
                TextColumn::make('tipo_doc')
                    ->searchable(),
                TextColumn::make('nro_doc')
                    ->searchable(),
                TextColumn::make('afiliado')
                    ->searchable(),
                TextColumn::make('tipo_doc_dos')
                    ->searchable(),
                TextColumn::make('nro_doc_tres')
                    ->searchable(),
                TextColumn::make('sexo')
                    ->searchable(),
                TextColumn::make('fecha_nacimiento')
                    ->searchable(),
                
                TextColumn::make('parentesco')
                    ->searchable(),
                TextColumn::make('telefono')
                    ->searchable(),
                TextColumn::make('correo')
                    ->searchable(),
                TextColumn::make('estado')
                    ->searchable(),
                TextColumn::make('ciudad')
                    ->searchable(),
                TextColumn::make('direccion')
                    ->searchable(),
                TextColumn::make('vigencia_desde')
                    ->searchable(),
                TextColumn::make('vigencia_hasta')
                    ->searchable(),
                TextColumn::make('agencia')
                    ->searchable(),
                TextColumn::make('agente')
                    ->searchable(),
                TextColumn::make('plan')
                    ->searchable()
                    ->badge()
                    ->colors([
                        'success' => 'INDIVIDUAL',
                        'warning' => 'CORPORATIVO',
                        'gray' => '',
                    ]),
                TextColumn::make('frecuencia_pago')
                    ->searchable(),
                TextColumn::make('forma_pago')
                    ->searchable(),
                TextColumn::make('monto_plan')
                    ->searchable(),
                TextColumn::make('monto_recibido')
                    ->searchable(),
                TextColumn::make('diferencia')
                    ->searchable(),
                TextColumn::make('estatus_pago')
                    ->searchable(),
                TextColumn::make('moneda')
                    ->searchable(),
                TextColumn::make('referencia')
                    ->searchable(),
                TextColumn::make('fecha_pago')
                    ->searchable(),
                TextColumn::make('pagado_desde')
                    ->searchable(),
                TextColumn::make('pagado_hasta')
                    ->searchable(),
                TextColumn::make('estatus_renovacion')
                    ->searchable(),
                TextColumn::make('estatus_afiliado')
                    ->searchable()
                    ->badge()
                    ->colors([
                        'success' => 'ACTIVO',
                        'danger' => 'INACTIVO',
                    ]),
                TextColumn::make('dias_para_vencer')
                    ->searchable(),
                TextColumn::make('estado_del_plan')
                    ->searchable(),
                TextColumn::make('pagado_ils_desde')
                    ->searchable(),
                TextColumn::make('pagado_ils_hasta')
                    ->searchable(),
                TextColumn::make('dia_vencimiento_ils')
                    ->searchable(),
                TextColumn::make('estado_plan_ils')
                    ->searchable(),
                TextColumn::make('fecha_egreso')
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
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('add_attributes')
                        ->label('Agregar Atributos')
                        ->icon('fontisto-reply')
                        ->modalWidth('3xl')
                        ->requiresConfirmation()
                        ->color('info')
                        ->form([
                            Fieldset::make('Información adicional requerida para INTEGRACORP')
                                ->columnSpanFull()
                                ->schema([
                                    Select::make('agency_id')
                                        ->label('Agencia')
                                        ->options(function (get $get) {
                                            return Agency::all()->pluck('name_corporative', 'code');
                                        })
                                        ->live()
                                        ->searchable()
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Campo Obligatorio',
                                        ])
                                        ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                            if ($state == null) {
                                                $set('owner_code', null);
                                                return;
                                            }
                                            $owner_code = Agency::where('code', $state)->first()->owner_code;
                                            $set('owner_code', $owner_code);
                                        })
                                        ->prefixIcon('heroicon-s-globe-europe-africa'),
                                    TextInput::make('owner_code')
                                        ->label('Código del propietario')
                                        ->disabled()
                                        ->dehydrated(),
                                    Select::make('agent_id')
                                        ->label('Agente')
                                        ->options(function (get $get) {
                                            return Agent::where('owner_code', $get('agency_id'))->get()->pluck('name', 'id');
                                        })
                                        ->live()
                                        ->searchable()
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Campo Obligatorio',
                                        ])
                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                        ->preload(),
                                    Select::make('plan_id')
                                        ->options(function () {
                                            return Plan::all()->pluck('description', 'id');
                                        })
                                        ->label('Planes')
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Campo Obligatorio',
                                        ])
                                        ->preload()
                                        ->placeholder('Seleccione plan(es)'),

                                    Select::make('age_range_id')
                                        ->label('Rango de edad')
                                        ->options(function (get $get, $state) {
                                            Log::info($state);
                                            return AgeRange::where('plan_id', $get('plan_id'))->get()->pluck('range', 'id');
                                        })
                                        ->searchable()
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Campo Obligatorio',
                                        ])
                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                        ->preload(),

                                    Select::make('coverage_id')
                                        ->label('Cobertura')
                                        ->options(function (get $get) {
                                            if ($get('age_range_id') == null) {
                                                return [];
                                            }
                                            $arrayFee = AgeRange::where('plan_id', $get('plan_id'))->where('id', $get('age_range_id'))->with('fees')->get()->toArray();
                                            return collect($arrayFee[0]['fees'])->pluck('coverage', 'coverage_id');
                                        })
                                        ->searchable()
                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                        ->preload(),
                                    
                                    Select::make('fee')
                                        ->label('Tarifa Anual')
                                        ->options(function (get $get) {
                                            Log::info(Fee::where('age_range_id', $get('age_range_id'))->where('coverage_id', $get('coverage_id'))->get()->pluck('price', 'price'));
                                            return Fee::where('age_range_id', $get('age_range_id'))->where('coverage_id', $get('coverage_id'))->get()->pluck('price', 'price');
                                        })
                                        ->live()
                                        ->searchable()
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Campo Obligatorio',
                                        ])
                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                        ->preload(),
                                    Select::make('payment_frequency')
                                        ->label('Frecuencia de pago')
                                        ->live()
                                        ->options([
                                            'ANUAL'      => 'ANUAL',
                                            'SEMESTRAL'  => 'SEMESTRAL',
                                            'TRIMESTRAL' => 'TRIMESTRAL',
                                        ])
                                        ->searchable()
                                        ->live()
                                        ->prefixIcon('heroicon-s-globe-europe-africa')
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Campo Obligatorio',
                                        ])
                                        ->preload(),
                                    TextInput::make('total_persons')
                                        ->numeric()
                                        ->required()
                                        ->validationMessages([
                                            'required'  => 'Campo Obligatorio',
                                        ]),
                                    Hidden::make('status_migration')
                                        ->default('SIN PROCESAR')
                                ])
                        ])
                        ->action(function (Collection $records, $data) {
                            $migration = MigrationHistoricalController::add_atributes($records, $data);

                            if ($migration) {
                                Notification::make()
                                    ->title('Migración exitosa')
                                    ->success()
                                    ->send();
                            }
                        }),
                    BulkAction::make('migrate_history')
                    ->label('Migrar Individuales')
                    ->icon('fontisto-reply')
                    ->requiresConfirmation()
                    ->color('info')
                    ->action(function (Collection $records) {
                        $migration = MigrationHistoricalController::migrate_history_affiliations($records);

                        if($migration) {
                            Notification::make()
                                ->title('Migración exitosa')
                                ->success()
                                ->send();
                        }
                    }),
                BulkAction::make('migrate_history_corporate')
                    ->label('Migrar Corporativos')
                    ->icon('fontisto-reply')
                    ->requiresConfirmation()
                    ->color('success')
                    ->form([
                        Radio::make('type')
                            ->label('Seleccione el tipo de cotización')
                            ->live()
                            ->options([
                                'BASICO' => 'BÁSICO',
                                'DRESS-TAILOR' => 'DRESS-TAILOR',
                            ]),
                        Select::make('corporate_quote_id')
                            ->label('Seleccione la Cotización Corporativa')
                            ->options(function (Get $get) {
                                return CorporateQuote::where('type', $get('type'))->pluck('code', 'id');
                            })
                    ])
                    ->action(function (Collection $records, array $data) {
                        MigrationHistoricalController::migrate_history_affiliations_corporate($records, $data, count($records));
                    })
                ]),
            ]);
    }
}