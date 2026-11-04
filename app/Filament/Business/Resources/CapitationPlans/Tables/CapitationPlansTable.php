<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CapitationPlans\Tables;

use App\Enums\CapitationPaymentFrequency;
use App\Filament\Business\Resources\CapitationPlans\Actions\CapitationPlanActions;
use App\Models\CapitationPlan;
use App\Support\Business\CapitationPlans\CapitationProviders;
use App\Support\Operations\ProviderDeletion;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CapitationPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Planes de capitado')
            ->description('Planes negociados con los proveedores de la red Outsourcing: costo por afiliado, frecuencia de pago y beneficios.')
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder('Buscar por plan o proveedor')
            ->searchDebounce('350ms')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon(Heroicon::OutlinedHandRaised)
            ->emptyStateHeading('Aún no hay planes de capitado')
            ->emptyStateDescription('Cree el primero con «Nuevo plan». Solo se pueden elegir proveedores de la red Outsourcing.')
            ->columns([
                TextColumn::make('name')
                    ->label('Plan')
                    ->weight('semibold')
                    ->wrap()
                    ->description(fn (CapitationPlan $record): string => ($record->benefits_count === 1 ? '1 beneficio' : $record->benefits_count.' beneficios')
                        .(count((array) $record->documents) > 0 ? ' · '.count((array) $record->documents).' doc.' : ''))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('provider_name')
                    ->label('Proveedor')
                    ->state(fn (CapitationPlan $record): string => (string) ProviderDeletion::displayName($record->provider()))
                    ->description(fn (CapitationPlan $record): string => CapitationProviders::typeLabel($record->provider_type))
                    ->wrap()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(function (Builder $query) use ($search): void {
                        $like = '%'.$search.'%';

                        $query
                            ->where(fn (Builder $query): Builder => $query
                                ->where('provider_type', CapitationPlan::PROVIDER_SUPPLIER)
                                ->whereHas('supplier', fn (Builder $query): Builder => $query->where('name', 'like', $like)))
                            ->orWhere(fn (Builder $query): Builder => $query
                                ->where('provider_type', CapitationPlan::PROVIDER_DOCTOR_NURSE)
                                ->whereHas('doctorNurse', fn (Builder $query): Builder => $query->where('name', 'like', $like)));
                    })),
                TextColumn::make('cost_per_affiliate_usd')
                    ->label('Costo por afiliado')
                    ->money('USD')
                    ->description(fn (CapitationPlan $record): string => $record->payment_frequency === CapitationPaymentFrequency::Monthly
                        ? 'Mensual'
                        : $record->payment_frequency->label().' · ≈ US$ '.number_format($record->monthlyEquivalent(), 2, ',', '.').'/mes')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('validity')
                    ->label('Vigencia')
                    ->state(fn (CapitationPlan $record): string => match (true) {
                        $record->valid_from === null && $record->valid_until === null => 'Sin fecha de fin',
                        $record->valid_until === null => 'Desde '.$record->valid_from->format('d/m/Y'),
                        $record->valid_from === null => 'Hasta '.$record->valid_until->format('d/m/Y'),
                        default => $record->valid_from->format('d/m/Y').' – '.$record->valid_until->format('d/m/Y'),
                    })
                    ->color(fn (CapitationPlan $record): ?string => $record->valid_until !== null && $record->valid_until->isPast() ? 'danger' : null)
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === CapitationPlan::STATUS_ACTIVE ? 'Activo' : 'Inactivo')
                    ->color(fn (string $state): string => $state === CapitationPlan::STATUS_ACTIVE ? 'success' : 'gray'),
                TextColumn::make('updated_by_name')
                    ->label('Actualizado por')
                    ->description(fn (CapitationPlan $record): ?string => $record->updated_at?->format('d/m/Y h:i A'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('provider')
                    ->label('Proveedor')
                    ->options(fn (): array => self::providersWithPlans())
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        $provider = CapitationProviders::parse($data['value']);

                        return $query->forProvider($provider['type'], $provider['id']);
                    }),
                SelectFilter::make('payment_frequency')
                    ->label('Frecuencia de pago')
                    ->options(CapitationPaymentFrequency::options()),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([CapitationPlan::STATUS_ACTIVE => 'Activo', CapitationPlan::STATUS_INACTIVE => 'Inactivo']),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('Ver plan'),
                    EditAction::make()->label('Editar'),
                    CapitationPlanActions::pdf(),
                    CapitationPlanActions::send(),
                    CapitationPlanActions::delete(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    CapitationPlanActions::bulkPdf(),
                    CapitationPlanActions::bulkSend(),
                ])->label('Acciones con los seleccionados'),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function providersWithPlans(): array
    {
        $options = [];

        foreach (CapitationPlan::query()->select(['provider_type', 'provider_id'])->distinct()->get() as $plan) {
            $label = CapitationProviders::label($plan->providerKey());

            if ($label !== null) {
                $options[$plan->providerKey()] = $label;
            }
        }

        asort($options);

        return $options;
    }
}
