<?php

namespace App\Filament\Operations\Resources\Affiliates\Tables;

use App\Filament\Exports\AffiliateExporter;
use App\Models\Affiliate;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AffiliatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->defaultSort('created_at', 'desc')
        ->heading('LISTA DE AFILIADOS INDIVIDUALES')
        ->description('A continuacion se muestra la lista de afiliados individuales. La tabla esta ordenada por fecha de registro de forma descendente, los mas recientes se muestran primero')
            ->columns([
                TextColumn::make('full_name')
                    ->icon(function ($record) {
                        $now = Carbon::today();
                        if ($record->status == 'ACTIVO' && $record->created_at >= $now) {
                            return 'heroicon-s-star';
                        }
                        return 'heroicon-s-user-group';
                    })
                    ->iconColor(function ($record) {
                        $now = Carbon::today();
                        // Forzamos el color del icono a rojo (danger) solo cuando el if es true
                        if ($record->status == 'ACTIVO' && $record->created_at >= $now) {
                            return 'danger';
                        }
                        return null; // Color por defecto (blanco por el estilo extraAttributes)
                    })
                    ->badge(function ($record) {
                        $now = Carbon::today();
                        if ($record->status == 'ACTIVO' && $record->created_at >= $now) {
                            return false;
                        }
                        return true;
                    })
                    ->color(function ($record) {
                        return 'success';
                    })
                    ->extraAttributes(function ($record) {

                        /**
                         * Diseño optimizado con estilo iOS System Green.
                         * Utilizamos el verde oficial de Apple (#34C759) para máximo resaltado.
                         */
                        $iosGreen = '#34C759';
                        $iosGreenDark = '#248A3D'; // Para el texto, asegurando legibilidad

                        $now = Carbon::today();
                        // dd($now->diffInDays($record->created_at));

                        if ($record->status == 'ACTIVO' && $record->created_at >= $now) {
                            $iosGreen = '#34C759';
                            $iosGreenDark = '#248A3D';

                            return [
                                'style' => "
                                            background-color: {$iosGreen} !important;
                                            color: #ffffff !important;
                                            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
                                            font-weight: 700;
                                            font-size: 0.85rem;
                                            letter-spacing: -0.02em;
                                            padding: 0.2rem 0.8rem;
                                            border-radius: 20px;
                                            box-shadow: 0 4px 12px rgba(52, 199, 89, 0.35);
                                            border: 1px solid rgba(255, 255, 255, 0.2);
                                            text-shadow: 0px 1px 2px rgba(0, 0, 0, 0.1);
                                            display: inline-flex;
                                            align-items: center;
                                        ",
                            ];
                        }
                        return [];
                    })
                    ->label('Nombre Y Apellido')
                    ->searchable(),
                TextColumn::make('nro_identificacion')
                    ->color('info')
                    ->badge()
                    ->icon('heroicon-s-identification')
                    ->label('Nro Identificacion')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telefono')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('sex')
                    ->label('Sexo')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Fecha Nacimiento')
                    ->searchable(),
                TextColumn::make('age')
                    ->label('Edad')
                    ->searchable(),
                TextColumn::make('country.name')
                    ->label('Pais')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('city.definition')
                    ->label('Ciudad')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('region')
                    ->label('Region')
                    ->searchable(),
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->prefix('$')
                    ->numeric()
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
                SelectFilter::make('plan_id')
                    ->label('Plan Afiliado')
                    ->relationship('plan', 'description')
                    ->multiple(),
            ])
            ->filtersTriggerAction(
                fn(Action $action) => $action
                    ->button()
                    ->label('Filtros'),
            )
            ->recordActions([
                ViewAction::make()
                ->icon('heroicon-o-eye')
                ->label('Ver Detalles')
                ->color('primary'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()->exporter(AffiliateExporter::class)->label('Exportar XLS')->color('info')->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
