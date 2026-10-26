<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PersonIdentityFindings\Tables;

use App\Enums\PersonIdentityFindingKind as Kind;
use App\Enums\PersonIdentitySource;
use App\Models\PersonIdentityFinding as Finding;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PersonIdentityFindingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw("CASE severity WHEN 'ALTA' THEN 0 ELSE 1 END")
                ->orderBy('first_detected_at'))
            ->striped()
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100])
            ->searchDebounce('350ms')
            ->emptyStateHeading('Sin casos en esta vista')
            ->emptyStateDescription('El sistema revisa todos los días a las 6:50 los afiliados individuales, corporativos y de Nuevos Negocios. Lo que encuentre aparece aquí, explicado.')
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge)
            ->columns([
                TextColumn::make('kind')
                    ->label('Caso')
                    ->badge()
                    ->formatStateUsing(fn (Finding $record): string => $record->kindEnum()?->label() ?? (string) $record->kind)
                    ->color(fn (Finding $record): string => Kind::severityColor($record->severity)),
                TextColumn::make('person_name')
                    ->label('Persona')
                    ->weight('bold')
                    ->description(fn (Finding $record): string => self::documents($record))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('record_a_type')
                    ->label('Registro A')
                    ->state(fn (Finding $record): string => (string) ($record->side('a')['where'] ?? '—'))
                    ->wrap(),
                TextColumn::make('record_b_type')
                    ->label('Registro B')
                    ->state(fn (Finding $record): string => (string) ($record->side('b')['where'] ?? '—'))
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('first_detected_at')
                    ->label('Detectado')
                    ->dateTime('d/m/Y')
                    ->description(fn (Finding $record): ?string => $record->first_detected_at?->diffForHumans())
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (Finding $record): string => $record->statusLabel())
                    ->color(fn (?string $state): string => Finding::statusColor($state))
                    ->description(fn (Finding $record): ?string => $record->resolved_by_name !== null ? 'por '.$record->resolved_by_name : null),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label('Caso')
                    ->options(Kind::options())
                    ->native(false),
                SelectFilter::make('source')
                    ->label('Población')
                    ->options(PersonIdentitySource::options())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where(fn (Builder $query): Builder => $query->where('record_a_type', $data['value'])->orWhere('record_b_type', $data['value']))
                        : $query)
                    ->native(false),
            ])
            ->recordActions([
                ViewAction::make()->label('Revisar'),
            ]);
    }

    private static function documents(Finding $record): string
    {
        $documents = array_unique(array_filter([
            (string) ($record->side('a')['document'] ?? ''),
            (string) ($record->side('b')['document'] ?? ''),
        ]));

        return $documents === [] ? 'Sin cédula' : 'Cédula: '.implode(' / ', $documents);
    }
}
