<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\AffiliateCorporates\Tables;

use App\Filament\Exports\AffiliateCorporateExporter;
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

class AffiliateCorporatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->heading('Afiliados corporativos')
            ->description('Orden por fecha de registro (más recientes primero).')
            ->striped()
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'affiliationCorporate.businessLine:id,definition',
                'affiliationCorporate.businessUnit:id,definition',
            ]))
            ->emptyStateHeading('Sin afiliados corporativos')
            ->emptyStateDescription('No hay registros o no coinciden con la búsqueda y los filtros.')
            ->columns([
                TextColumn::make('first_name')
                    ->label('Nombre y apellido')
                    ->color('info')
                    ->badge()
                    ->icon(Heroicon::OutlinedUser)
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
                TextColumn::make('sex')
                    ->label('Sexo')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
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
                TextColumn::make('full_name_emergency')
                    ->label('Contacto de emergencia')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('phone_emergency')
                    ->label('Teléfono emergencia')
                    ->icon(Heroicon::OutlinedPhone)
                    ->copyable()
                    ->searchable(),
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('affiliationCorporate.business_line_id')
                    ->label('Línea de servicio')
                    ->formatStateUsing(fn ($record): string => filled($record->affiliationCorporate?->businessLine?->definition)
                        ? (string) $record->affiliationCorporate->businessLine->definition
                        : '—')
                    ->description(fn ($record): ?string => filled($record->affiliationCorporate?->business_line_id)
                        ? 'ID: '.$record->affiliationCorporate->business_line_id
                        : null)
                    ->badge()
                    ->color('success')
                    ->placeholder('—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('affiliationCorporate.businessLine', fn (Builder $lineQuery): Builder => $lineQuery->where('definition', 'like', "%{$search}%"));
                    }),
                TextColumn::make('affiliationCorporate.business_unit_id')
                    ->label('Unidad de negocio')
                    ->formatStateUsing(fn ($record): string => filled($record->affiliationCorporate?->businessUnit?->definition)
                        ? (string) $record->affiliationCorporate->businessUnit->definition
                        : '—')
                    ->description(fn ($record): ?string => filled($record->affiliationCorporate?->business_unit_id)
                        ? 'ID: '.$record->affiliationCorporate->business_unit_id
                        : null)
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('affiliationCorporate.businessUnit', fn (Builder $unitQuery): Builder => $unitQuery->where('definition', 'like', "%{$search}%"));
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
                    ->color('info')
                    ->label('Ver detalles'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(AffiliateCorporateExporter::class)
                        ->label('Exportar XLS')
                        ->color('info')
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}
