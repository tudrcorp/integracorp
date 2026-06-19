<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\Affiliates\Tables;

use App\Filament\Exports\AffiliateExporter;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
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
            ->heading('Afiliados individuales')
            ->description('Orden por fecha de registro (más recientes primero). La celda del nombre resalta en verde cuando el estado es ACTIVO y el alta es hoy.')
            ->striped()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'affiliation.businessLine:id,definition',
                'affiliation.businessUnit:id,definition',
            ]))
            ->emptyStateHeading('Sin afiliados')
            ->emptyStateDescription('No hay registros o no coinciden con la búsqueda y los filtros.')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Nombre y apellido')
                    ->icon(function ($record) {
                        $now = Carbon::today();
                        if ($record->status == 'ACTIVO' && $record->created_at >= $now) {
                            return 'heroicon-s-star';
                        }

                        return 'heroicon-s-user-group';
                    })
                    ->iconColor(function ($record) {
                        $now = Carbon::today();
                        if ($record->status == 'ACTIVO' && $record->created_at >= $now) {
                            return 'danger';
                        }

                        return null;
                    })
                    ->badge(function ($record) {
                        $now = Carbon::today();
                        if ($record->status == 'ACTIVO' && $record->created_at >= $now) {
                            return false;
                        }

                        return true;
                    })
                    ->color(fn (): string => 'success')
                    ->extraAttributes(function ($record) {
                        $now = Carbon::today();
                        if ($record->status == 'ACTIVO' && $record->created_at >= $now) {
                            $iosGreen = '#34C759';

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
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (?string $state): ?string => filled($state) ? $state : null),
                TextColumn::make('nro_identificacion')
                    ->label('Identificación')
                    ->color('info')
                    ->badge()
                    ->icon(Heroicon::OutlinedIdentification)
                    ->copyable()
                    ->copyMessage('Identificación copiada')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon(Heroicon::OutlinedPhone)
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('sex')
                    ->label('Sexo')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Fecha de nacimiento')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->formatStateUsing(function (mixed $state): ?string {
                        if (blank($state)) {
                            return null;
                        }
                        try {
                            return Carbon::parse($state)->format('d/m/Y');
                        } catch (\Throwable) {
                            return (string) $state;
                        }
                    })
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('age')
                    ->label('Edad')
                    ->alignCenter()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('country.name')
                    ->label('País')
                    ->icon(Heroicon::OutlinedGlobeAmericas)
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->icon(Heroicon::OutlinedMap)
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('city.definition')
                    ->label('Ciudad')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('region')
                    ->label('Región')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('affiliation.business_line_id')
                    ->label('Línea de servicio')
                    ->formatStateUsing(fn ($record): string => filled($record->affiliation?->businessLine?->definition)
                        ? (string) $record->affiliation->businessLine->definition
                        : '—')
                    ->description(fn ($record): ?string => filled($record->affiliation?->business_line_id)
                        ? 'ID: '.$record->affiliation->business_line_id
                        : null)
                    ->badge()
                    ->color('success')
                    ->placeholder('—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('affiliation.businessLine', fn (Builder $lineQuery): Builder => $lineQuery->where('definition', 'like', "%{$search}%"));
                    }),
                TextColumn::make('affiliation.business_unit_id')
                    ->label('Unidad de negocio')
                    ->formatStateUsing(fn ($record): string => filled($record->affiliation?->businessUnit?->definition)
                        ? (string) $record->affiliation->businessUnit->definition
                        : '—')
                    ->description(fn ($record): ?string => filled($record->affiliation?->business_unit_id)
                        ? 'ID: '.$record->affiliation->business_unit_id
                        : null)
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('affiliation.businessUnit', fn (Builder $unitQuery): Builder => $unitQuery->where('definition', 'like', "%{$search}%"));
                    }),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->money('USD')
                    ->alignEnd()
                    ->sortable()
                    ->placeholder('—'),
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
                            $indicators['desde'] = 'Alta desde '.Carbon::parse($data['desde'])->toFormattedDateString();
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Alta hasta '.Carbon::parse($data['hasta'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
                SelectFilter::make('plan_id')
                    ->label('Plan')
                    ->relationship('plan', 'description')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->placeholder('Todos'),
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filtros')
                    ->icon(Heroicon::OutlinedFunnel),
            )
            ->recordActions([
                ViewAction::make()
                    ->icon(Heroicon::OutlinedEye)
                    ->label('Ver detalles')
                    ->color('primary'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(AffiliateExporter::class)
                        ->label('Exportar XLS')
                        ->color('info')
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
