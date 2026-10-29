<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Upgrades\Tables;

use App\Models\Upgrade;
use App\Models\UpgradeCoverage;
use App\Support\Upgrades\UpgradeCatalogPersistence;
use App\Support\Upgrades\UpgradePricing;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UpgradesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->heading('Upgrades')
            ->description('Catálogo de upgrades que se agregan a afiliados individuales y corporativos. El precio depende de la cobertura y de la edad del afiliado.')
            ->emptyStateHeading('Todavía no hay upgrades')
            ->emptyStateDescription('Cree el primero con el botón «Nuevo upgrade»: nombre, servicios, coberturas y precio por rango de edad.')
            ->emptyStateIcon(Heroicon::Sparkles)
            ->striped()
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Upgrade')
                    ->weight('semibold')
                    ->wrap()
                    ->description(fn (Upgrade $record): ?string => filled($record->description) ? str((string) $record->description)->limit(90)->toString() : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('active_coverages_count')
                    ->label('Coberturas')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('price_range')
                    ->label('Precio anual')
                    ->state(fn (Upgrade $record): string => self::priceRange($record))
                    ->icon(Heroicon::CurrencyDollar),
                TextColumn::make('active_services_count')
                    ->label('Servicios')
                    ->numeric()
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('assignments')
                    ->label('Afiliados con el upgrade')
                    ->state(fn (Upgrade $record): int => (int) $record->getAttribute('active_corporate_assignments_count') + (int) $record->getAttribute('active_individual_assignments_count'))
                    ->description(fn (Upgrade $record): string => (int) $record->getAttribute('active_corporate_assignments_count').' corporativos · '.(int) $record->getAttribute('active_individual_assignments_count').' individuales')
                    ->numeric()
                    ->icon(Heroicon::UserGroup),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(fn (?string $state): string => $state === Upgrade::STATUS_ACTIVE ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y h:i a')
                    ->description(fn (Upgrade $record): ?string => $record->updated_by)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        Upgrade::STATUS_ACTIVE => 'Activo',
                        Upgrade::STATUS_INACTIVE => 'Inactivo',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('Ver'),
                    EditAction::make()->label('Editar')->color('warning'),
                    Action::make('deactivate')
                        ->label('Inactivar')
                        ->icon(Heroicon::NoSymbol)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Upgrade $record): string => 'Inactivar '.$record->name)
                        ->modalDescription(fn (Upgrade $record): string => 'Dejará de aparecer al asignar upgrades. '
                            .self::assignmentSummary($record)
                            .' No se borra nada y puede reactivarlo cuando quiera.')
                        ->modalSubmitActionLabel('Inactivar')
                        ->action(fn (Upgrade $record) => self::changeStatus($record, Upgrade::STATUS_INACTIVE))
                        ->visible(fn (Upgrade $record): bool => $record->isActive()),
                    Action::make('activate')
                        ->label('Reactivar')
                        ->icon(Heroicon::CheckCircle)
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Upgrade $record): string => 'Reactivar '.$record->name)
                        ->modalDescription('Volverá a aparecer al asignar upgrades, con sus coberturas y precios actuales.')
                        ->modalSubmitActionLabel('Reactivar')
                        ->action(fn (Upgrade $record) => self::changeStatus($record, Upgrade::STATUS_ACTIVE))
                        ->visible(fn (Upgrade $record): bool => ! $record->isActive()),
                ])->icon(Heroicon::EllipsisVertical),
            ]);
    }

    private static function priceRange(Upgrade $record): string
    {
        $prices = $record->activeCoverages
            ->flatMap(fn (UpgradeCoverage $coverage) => $coverage->activeAgeRanges->pluck('price'))
            ->map(fn (mixed $price): float => (float) $price);

        if ($prices->isEmpty()) {
            return 'Sin precios';
        }

        $min = (float) $prices->min();
        $max = (float) $prices->max();

        return $min === $max
            ? UpgradePricing::money($min)
            : UpgradePricing::money($min).' a '.UpgradePricing::money($max);
    }

    private static function assignmentSummary(Upgrade $record): string
    {
        $total = (int) $record->getAttribute('active_corporate_assignments_count') + (int) $record->getAttribute('active_individual_assignments_count');

        return $total > 0
            ? 'Los '.$total.' afiliados que ya lo tienen lo conservan con su monto actual.'
            : 'Ningún afiliado lo tiene activo.';
    }

    private static function changeStatus(Upgrade $record, string $status): void
    {
        UpgradeCatalogPersistence::changeStatus($record, $status);

        Notification::make()
            ->success()
            ->title($status === Upgrade::STATUS_ACTIVE ? 'Upgrade reactivado' : 'Upgrade inactivado')
            ->body($record->name)
            ->send();
    }
}
