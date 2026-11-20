<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\ApiPartners\RelationManagers;

use App\Models\ApiPartnerAccessLog;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Bitácora de consultas del aliado. Solo lectura.
 */
class AccessLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'accessLogs';

    protected static ?string $title = 'Bitácora de consultas';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedClipboardDocumentList;

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('outcome')
                    ->label('Resultado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ApiPartnerAccessLog::outcomeLabels()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        ApiPartnerAccessLog::OUTCOME_OK => 'success',
                        ApiPartnerAccessLog::OUTCOME_THROTTLED => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('status_code')
                    ->label('HTTP')
                    ->alignCenter(),
                TextColumn::make('path')
                    ->label('Consulta')
                    ->description(fn (ApiPartnerAccessLog $record): ?string => $record->query !== null
                        ? collect($record->query)->map(fn (string $value, string $key): string => $key.'='.$value)->implode(' · ')
                        : null)
                    ->fontFamily('mono')
                    ->wrap(),
                TextColumn::make('records_count')
                    ->label('Registros')
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('ip')
                    ->label('IP')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('duration_ms')
                    ->label('Tiempo')
                    ->suffix(' ms')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('outcome')
                    ->label('Resultado')
                    ->options(ApiPartnerAccessLog::outcomeLabels()),
            ])
            ->emptyStateHeading('Sin consultas todavía')
            ->emptyStateDescription('Cada petición del aliado al API queda registrada aquí, también las rechazadas.');
    }
}
