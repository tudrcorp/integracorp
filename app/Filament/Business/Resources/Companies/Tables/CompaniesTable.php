<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Companies\Tables;

use App\Filament\Business\Resources\Companies\Actions\CompanyTableActions;
use App\Models\Company;
use App\Support\Companies\CompanyResponsibleDays;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CompaniesTable
{
    private static function planStatusColor(?string $state): string
    {
        return match (strtoupper((string) $state)) {
            'ACTIVO', 'ACTIVA', 'APROBADA', 'APROBADO' => 'success',
            'PRE-APROBADO' => 'warning',
            'INACTIVO', 'INACTIVA' => 'gray',
            default => 'gray',
        };
    }

    private static function utilizationColor(Company $record): string
    {
        $population = CompanyResponsibleDays::populationTotalFor($record->planGenerator);
        $contracted = (int) ($record->responsibles_sum_contracted_days ?? 0);

        if ($population === null) {
            return 'gray';
        }

        if ($contracted > $population) {
            return 'danger';
        }

        if ($contracted === $population) {
            return 'success';
        }

        return 'info';
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->deferFilters(false)
            ->recordTitleAttribute('name')
            ->emptyStateHeading('Sin nuevos negocios registrados')
            ->emptyStateDescription('Apruebe una cotización y complete el registro de empresa, o cree un negocio manualmente.')
            ->emptyStateIcon(Heroicon::OutlinedBriefcase)
            ->columns([
                ColumnGroup::make('Empresa', [
                    TextColumn::make('name')
                        ->label('Nombre / Razón Social')
                        ->icon(Heroicon::OutlinedBuildingOffice2)
                        ->weight('semibold')
                        ->searchable()
                        ->sortable()
                        ->limit(36)
                        ->tooltip(fn (Company $record): string => (string) $record->name),
                    TextColumn::make('rif')
                        ->label('RIF')
                        ->icon(Heroicon::OutlinedIdentification)
                        ->badge()
                        ->color('gray')
                        ->searchable()
                        ->sortable(),
                ]),
                ColumnGroup::make('Cotización asociada', [
                    TextColumn::make('planGenerator.control_number')
                        ->label('Nro. Control')
                        ->icon(Heroicon::OutlinedHashtag)
                        ->badge()
                        ->color('gray')
                        ->searchable()
                        ->sortable()
                        ->placeholder('—'),
                    TextColumn::make('planGenerator.name')
                        ->label('Plan / Cotización')
                        ->icon(Heroicon::OutlinedTableCells)
                        ->searchable()
                        ->sortable()
                        ->limit(28)
                        ->tooltip(fn (Company $record): ?string => filled($record->planGenerator?->name) ? (string) $record->planGenerator->name : null)
                        ->placeholder('Sin plan'),
                    TextColumn::make('planGenerator.status')
                        ->label('Estatus plan')
                        ->badge()
                        ->color(fn (?string $state): string => self::planStatusColor($state))
                        ->sortable()
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('population_total')
                        ->label('Población')
                        ->icon(Heroicon::OutlinedUsers)
                        ->state(fn (Company $record): ?int => CompanyResponsibleDays::populationTotalFor($record->planGenerator))
                        ->formatStateUsing(fn (?int $state): string => $state !== null
                            ? number_format($state, 0, ',', '.').' pers.'
                            : '—')
                        ->badge()
                        ->color('info')
                        ->alignCenter()
                        ->tooltip(fn (Company $record): ?string => filled($record->planGenerator?->population_summary)
                            ? (string) $record->planGenerator->population_summary
                            : null),
                ]),
                ColumnGroup::make('Responsables', [
                    TextColumn::make('responsibles_count')
                        ->label('Cantidad')
                        ->counts('responsibles')
                        ->badge()
                        ->color('info')
                        ->alignCenter(),
                    TextColumn::make('responsibles_sum_contracted_days')
                        ->label('Días contratados')
                        ->formatStateUsing(fn ($state): string => number_format((int) ($state ?? 0), 0, ',', '.'))
                        ->badge()
                        ->color(fn (Company $record): string => self::utilizationColor($record))
                        ->alignCenter()
                        ->default(0),
                    TextColumn::make('population_utilization')
                        ->label('Uso población')
                        ->state(function (Company $record): string {
                            $population = CompanyResponsibleDays::populationTotalFor($record->planGenerator);
                            $contracted = (int) ($record->responsibles_sum_contracted_days ?? 0);

                            if ($population === null) {
                                return '—';
                            }

                            $percentage = $population > 0
                                ? min(999, (int) round(($contracted / $population) * 100))
                                : 0;

                            return number_format($contracted, 0, ',', '.').' / '.number_format($population, 0, ',', '.')." ({$percentage}%)";
                        })
                        ->badge()
                        ->color(fn (Company $record): string => self::utilizationColor($record))
                        ->tooltip('Suma de días contratados vs población total del plan asociado.')
                        ->alignCenter(),
                ]),
                ColumnGroup::make('Contacto', [
                    TextColumn::make('email')
                        ->label('Correo')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->searchable()
                        ->copyable()
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('phone')
                        ->label('Teléfono')
                        ->icon(Heroicon::OutlinedPhone)
                        ->searchable()
                        ->copyable()
                        ->placeholder('—')
                        ->toggleable(),
                ]),
                ColumnGroup::make('Auditoría', [
                    TextColumn::make('created_by')
                        ->label('Registrado por')
                        ->icon(Heroicon::OutlinedUserCircle)
                        ->searchable()
                        ->placeholder('—')
                        ->toggleable(isToggledHiddenByDefault: true),
                    TextColumn::make('created_at')
                        ->label('Registrado')
                        ->icon(Heroicon::OutlinedClock)
                        ->dateTime('d/m/Y H:i')
                        ->sortable()
                        ->toggleable(),
                ]),
            ])
            ->filters([
                TernaryFilter::make('has_plan')
                    ->label('Plan asociado')
                    ->placeholder('Todos')
                    ->trueLabel('Con plan')
                    ->falseLabel('Sin plan')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('plan_generator_id'),
                        false: fn ($query) => $query->whereNull('plan_generator_id'),
                    ),
                SelectFilter::make('plan_generator_id')
                    ->label('Plan / Cotización')
                    ->relationship('planGenerator', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver negocio'),
                    EditAction::make()
                        ->label('Editar negocio'),
                    CompanyTableActions::sendPublicRegistrationLinkAction(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                ]),
            ]);
    }
}
