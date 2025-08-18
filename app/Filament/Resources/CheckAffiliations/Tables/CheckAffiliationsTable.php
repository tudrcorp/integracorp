<?php

namespace App\Filament\Resources\CheckAffiliations\Tables;

use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\CorporateQuote;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Radio;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Filament\Schemas\Components\Utilities\Get;
use App\Http\Controllers\MigrationHistoricalController;

class CheckAffiliationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nro_afiliado')
                    ->numeric()
                    ->searchable()
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
                    }),
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
                TextColumn::make('tipo_plan')
                    ->searchable()
                    ->badge()
                    ->colors([
                        'success' => 'Especial',
                        'warning' => 'Ideal',
                        'gray' => '',
                    ]),
                TextColumn::make('proveedor')
                    ->searchable(),
                TextColumn::make('nro_vaucher')
                    ->searchable(),
                TextColumn::make('cobertura')
                    ->searchable(),
                TextColumn::make('tomador')
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
                TextColumn::make('edad')
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