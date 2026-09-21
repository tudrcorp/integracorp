<?php

namespace App\Filament\Business\Resources\Plans\Tables;

use App\Models\Plan;
use App\Support\Plans\PlanQuotability;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->heading('PLANES')
            ->description('Lista de planes registrados en el sistema. La columna Uso clínico indica si el médico ya puede asignar servicios tipo 1.')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['benefitPlans', 'clinicalSettings']))
            ->columns([
                TextColumn::make('code')
                    ->label('Codigo')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Definicion')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo de Plan')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('quotability')
                    ->label('Cotizable')
                    ->badge()
                    ->state(fn (Plan $record): string => PlanQuotability::tableLabel($record))
                    ->color(fn (Plan $record): string => PlanQuotability::tableColor($record)),
                TextColumn::make('businessUnit.definition')
                    ->label('Unidad de negocios')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'ACTIVO' => 'success',
                            'INACTIVO' => 'danger',
                            default => 'gray',
                        };
                    })
                    ->searchable(),
                TextColumn::make('clinical_usage')
                    ->label('Uso clínico')
                    ->badge()
                    ->state(fn (Plan $record): string => \App\Support\ClinicalEntitlements\PlanClinicalCompleteness::isComplete($record)
                        ? 'Listo'
                        : 'Pendiente')
                    ->color(fn (Plan $record): string => \App\Support\ClinicalEntitlements\PlanClinicalCompleteness::isComplete($record)
                        ? 'success'
                        : 'warning'),
                TextColumn::make('created_by')
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
                            $indicators['desde'] = 'Venta desde '.Carbon::parse($data['desde'])->toFormattedDateString();
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Venta hasta '.Carbon::parse($data['hasta'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver'),
                    Action::make('usoClinico')
                        ->label(fn (Plan $record): string => \App\Support\ClinicalEntitlements\PlanClinicalCompleteness::isComplete($record)
                            ? 'Uso clínico'
                            : 'Completar uso clínico')
                        ->icon('heroicon-o-heart')
                        ->color(fn (Plan $record): string => \App\Support\ClinicalEntitlements\PlanClinicalCompleteness::isComplete($record)
                            ? 'gray'
                            : 'warning')
                        ->url(fn (Plan $record): string => \App\Filament\Business\Resources\Plans\PlanResource::getUrl('uso-clinico', ['record' => $record])),
                    EditAction::make()
                        ->label('Editar'),
                    Action::make('update_status')
                        ->label('Actualizar Estatus')
                        ->icon('heroicon-s-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->form([
                            Select::make('status')
                                ->options([
                                    'ACTIVO' => 'ACTIVO',
                                    'INACTIVO' => 'INACTIVO',
                                ])
                                ->required(),
                        ])
                        ->action(function (Plan $record, array $data): void {
                            $record->update($data);
                        }),

                    DeleteAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->color('danger'),

                ])->icon('heroicon-c-ellipsis-vertical')->color('azulOscuro'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped();
    }
}
