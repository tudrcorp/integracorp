<?php

namespace App\Filament\Business\Resources\Fees\Tables;

use App\Models\Coverage;
use App\Models\Fee;
use App\Models\Plan;
use App\Support\Filament\FeePriceUpdater;
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
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Throwable;

class FeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->heading('TARIFAS')
            ->description('Lista de tarifas calculadas registradas en el sistema')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'plan',
                'ageRange',
            ]))
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->badge()
                    ->sortable()
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->badge()
                    ->color('success')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('ageRange.range')
                    ->label('Rango de Edad')
                    ->suffix(' años')
                    ->color('primary')
                    ->badge()
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('coverage')
                    ->label('Cobertura')
                    ->color('primary')
                    ->badge()
                    ->suffix(' UD$')
                    ->numeric()
                    ->placeholder('—')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('price')
                    ->label('Tarifa')
                    ->money('USD')
                    ->alignEnd()
                    ->weight('semibold')
                    ->color('warning')
                    ->tooltip('Clic para editar el monto')
                    ->sortable()
                    ->searchable()
                    ->action(self::editPriceAction()),
                TextColumn::make('neta')
                    ->label('Neta')
                    ->money('USD')
                    ->alignEnd()
                    ->placeholder('—')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (?string $state): string => match (mb_strtoupper((string) $state)) {
                        'ACTIVO' => 'success',
                        'INACTIVO' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Fecha de Actualización')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('plan_and_coverage')
                    ->label('Plan y cobertura')
                    ->form([
                        Select::make('plan_id')
                            ->label('Plan')
                            ->options(fn (): array => Plan::query()
                                ->orderBy('description')
                                ->pluck('description', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('coverage_id', null)),
                        Select::make('coverage_id')
                            ->label('Cobertura')
                            ->options(function (Get $get): array {
                                $planId = $get('plan_id');

                                if (blank($planId)) {
                                    return [];
                                }

                                return Coverage::query()
                                    ->where('plan_id', $planId)
                                    ->orderBy('price')
                                    ->get(['id', 'price'])
                                    ->mapWithKeys(fn (Coverage $coverage): array => [
                                        $coverage->id => number_format((float) $coverage->price, 0, ',', '.').' UD$',
                                    ])
                                    ->all();
                            })
                            ->searchable()
                            ->preload()
                            ->disabled(fn (Get $get): bool => blank($get('plan_id')))
                            ->placeholder(fn (Get $get): string => blank($get('plan_id'))
                                ? 'Seleccione un plan primero'
                                : 'Todas'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['plan_id'] ?? null,
                                fn (Builder $query, $planId): Builder => $query->whereHas(
                                    'plan',
                                    fn (Builder $planQuery): Builder => $planQuery->whereKey($planId),
                                ),
                            )
                            ->when(
                                $data['coverage_id'] ?? null,
                                fn (Builder $query, $coverageId): Builder => $query->where('coverage_id', $coverageId),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['plan_id'] ?? null)) {
                            $planLabel = Plan::query()->find($data['plan_id'])?->description ?? (string) $data['plan_id'];
                            $indicators['plan_id'] = 'Plan: '.$planLabel;
                        }

                        if (filled($data['coverage_id'] ?? null)) {
                            $coveragePrice = Coverage::query()->find($data['coverage_id'])?->price;
                            $coverageLabel = $coveragePrice !== null
                                ? number_format((float) $coveragePrice, 0, ',', '.').' UD$'
                                : (string) $data['coverage_id'];
                            $indicators['coverage_id'] = 'Cobertura: '.$coverageLabel;
                        }

                        return $indicators;
                    }),
                SelectFilter::make('age_range_id')
                    ->label('Rango de edad')
                    ->relationship('ageRange', 'range')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'ACTIVO' => 'ACTIVO',
                        'INACTIVO' => 'INACTIVO',
                    ]),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('desde')
                            ->label('Creado desde'),
                        DatePicker::make('hasta')
                            ->label('Creado hasta'),
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
                            $indicators['desde'] = 'Creado desde '.Carbon::parse($data['desde'])->toFormattedDateString();
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Creado hasta '.Carbon::parse($data['hasta'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver')
                        ->icon('heroicon-o-eye'),
                    EditAction::make()
                        ->label('Editar')
                        ->icon('heroicon-o-pencil-square'),
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

    private static function editPriceAction(): Action
    {
        return Action::make('editFeePrice')
            ->modalHeading('Editar tarifa')
            ->modalDescription('Modifique el monto y registre el motivo. El cambio quedará en las trazas de seguridad del sistema.')
            ->modalIcon('heroicon-o-currency-dollar')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Guardar tarifa')
            ->modalCancelActionLabel('Cancelar')
            ->closeModalByClickingAway(false)
            ->fillForm(fn (Fee $record): array => [
                'price' => $record->price,
                'reason' => '',
            ])
            ->form([
                TextInput::make('price')
                    ->label('Monto de la tarifa')
                    ->prefix('US$')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->helperText('Indique el nuevo monto en dólares estadounidenses.')
                    ->validationMessages([
                        'required' => 'Debe indicar el monto de la tarifa.',
                        'min' => 'El monto no puede ser negativo.',
                    ]),
                Textarea::make('reason')
                    ->label('Motivo del cambio')
                    ->placeholder('Explique por qué se actualiza la tarifa…')
                    ->helperText('Campo obligatorio. Mínimo 10 caracteres. Quedará en las trazas de seguridad.')
                    ->required()
                    ->minLength(10)
                    ->maxLength(5000)
                    ->rows(4)
                    ->columnSpanFull()
                    ->validationMessages([
                        'required' => 'Debe indicar el motivo del cambio de tarifa.',
                        'minLength' => 'El motivo debe tener al menos 10 caracteres.',
                    ]),
            ])
            ->action(function (Fee $record, array $data): void {
                try {
                    $result = FeePriceUpdater::update(
                        fee: $record,
                        newPrice: $data['price'] ?? 0,
                        reason: (string) ($data['reason'] ?? ''),
                    );

                    Notification::make()
                        ->title('Tarifa actualizada')
                        ->body('Monto actualizado de US$ '.number_format($result['price_from'], 2, ',', '.').' a US$ '.number_format($result['price_to'], 2, ',', '.').'.')
                        ->success()
                        ->send();
                } catch (ValidationException $exception) {
                    throw $exception;
                } catch (Throwable $throwable) {
                    Notification::make()
                        ->title('No se pudo actualizar la tarifa')
                        ->body($throwable->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
