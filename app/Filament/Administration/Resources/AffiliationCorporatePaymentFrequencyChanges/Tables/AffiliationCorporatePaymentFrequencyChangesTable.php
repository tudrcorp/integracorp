<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationCorporatePaymentFrequencyChanges\Tables;

use App\Models\AffiliationCorporatePaymentFrequencyChange as FrequencyChange;
use App\Support\AffiliationCorporates\CorporatePaymentFrequency;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AffiliationCorporatePaymentFrequencyChangesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->striped()
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50])
            ->emptyStateHeading('Sin cambios de frecuencia')
            ->emptyStateDescription('Aquí aparece cada cambio de frecuencia de pago que Negocios haga en una afiliación corporativa.')
            ->emptyStateIcon(Heroicon::OutlinedArrowsRightLeft)
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (FrequencyChange $record): ?string => $record->created_at?->diffForHumans())
                    ->sortable(),
                TextColumn::make('affiliation_code')
                    ->label('Afiliación')
                    ->weight('bold')
                    ->description(fn (FrequencyChange $record): ?string => $record->affiliation_name)
                    ->searchable(['affiliation_code', 'affiliation_name'])
                    ->copyable(),
                TextColumn::make('new_frequency')
                    ->label('Cambio')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (FrequencyChange $record): string => CorporatePaymentFrequency::label($record->previous_frequency)
                        .' → '.CorporatePaymentFrequency::label($record->new_frequency)),
                TextColumn::make('new_total_amount')
                    ->label('Monto por período')
                    ->formatStateUsing(fn (FrequencyChange $record): string => self::money((float) $record->previous_total_amount)
                        .' → '.self::money((float) $record->new_total_amount)),
                TextColumn::make('created_collections')
                    ->label('Avisos')
                    ->state(fn (FrequencyChange $record): string => count($record->cancelled_collections ?? []).' cancelados · '
                        .count($record->created_collections ?? []).' nuevos')
                    ->color('gray'),
                TextColumn::make('performed_by_name')
                    ->label('Analista')
                    ->icon(Heroicon::OutlinedUser)
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->state(fn (FrequencyChange $record): string => $record->displayStatus())
                    ->color(fn (FrequencyChange $record): string => match ($record->displayStatus()) {
                        'Revertido' => 'danger',
                        'Validado' => 'success',
                        default => 'warning',
                    })
                    ->description(fn (FrequencyChange $record): ?string => match (true) {
                        $record->isReversed() => 'por '.$record->reversed_by_name,
                        $record->isValidated() => 'por '.$record->validated_by_name,
                        default => null,
                    }),
            ])
            ->filters([
                SelectFilter::make('new_frequency')
                    ->label('Nueva frecuencia')
                    ->options(CorporatePaymentFrequency::options())
                    ->native(false),
                Filter::make('created_at')
                    ->label('Fecha')
                    ->schema([
                        DatePicker::make('from')->label('Desde'),
                        DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make()->label('Revisar'),
            ]);
    }

    private static function money(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' US$';
    }
}
