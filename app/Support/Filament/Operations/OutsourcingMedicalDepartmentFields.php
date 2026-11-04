<?php

declare(strict_types=1);

namespace App\Support\Filament\Operations;

use App\Models\CapitationPlan;
use App\Models\DoctorNurse;
use App\Support\Operations\OutsourcingMedicalDepartment;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Campos, entradas, columna y filtro del Departamento Médico Outsourcing,
 * compartidos por proveedores naturales y jurídicos.
 */
final class OutsourcingMedicalDepartmentFields
{
    /**
     * @return array{0: Toggle, 1: TextInput}
     */
    public static function formFields(): array
    {
        return [
            Toggle::make(OutsourcingMedicalDepartment::FLAG_COLUMN)
                ->label('Pertenece al Departamento Médico Outsourcing')
                ->helperText('Actívalo si el proveedor atiende afiliados asignados a cambio de una tarifa mensual por afiliado (capitación).')
                ->live()
                ->inline(false)
                ->default(false)
                ->onIcon('heroicon-s-check')
                ->offIcon('heroicon-s-x-mark')
                ->onColor('success')
                ->offColor('gray')
                ->afterStateUpdated(function (?bool $state, Set $set): void {
                    if (! $state) {
                        $set(OutsourcingMedicalDepartment::FEE_COLUMN, null);
                    }
                }),
            TextInput::make(OutsourcingMedicalDepartment::FEE_COLUMN)
                ->label('Tarifa mensual por afiliado asignado')
                ->helperText('Monto en dólares que se paga al proveedor cada mes por cada afiliado que tenga asignado.')
                ->placeholder('0,00')
                ->prefix('US$')
                ->suffix('/ afiliado / mes')
                ->numeric()
                ->inputMode('decimal')
                ->step(0.01)
                ->minValue(OutsourcingMedicalDepartment::MIN_FEE)
                ->maxValue(OutsourcingMedicalDepartment::MAX_FEE)
                ->rule('decimal:0,2')
                ->visible(fn (Get $get): bool => (bool) $get(OutsourcingMedicalDepartment::FLAG_COLUMN))
                ->required(fn (Get $get): bool => (bool) $get(OutsourcingMedicalDepartment::FLAG_COLUMN))
                ->validationMessages([
                    'required' => 'Indica la tarifa mensual por afiliado: es obligatoria para proveedores outsourcing.',
                    'min' => 'La tarifa debe ser mayor que cero.',
                    'max' => 'La tarifa supera el máximo permitido.',
                    'decimal' => 'Usa como máximo dos decimales.',
                    'numeric' => 'La tarifa debe ser un número.',
                ]),
        ];
    }

    /**
     * @return array{0: IconEntry, 1: TextEntry, 2: TextEntry}
     */
    public static function infolistEntries(): array
    {
        return [
            IconEntry::make(OutsourcingMedicalDepartment::FLAG_COLUMN)
                ->label('Departamento Médico Outsourcing')
                ->boolean()
                ->trueIcon(Heroicon::OutlinedCheckCircle)
                ->falseIcon(Heroicon::OutlinedXCircle)
                ->trueColor('success')
                ->falseColor('gray')
                ->default(false),
            TextEntry::make(OutsourcingMedicalDepartment::FEE_COLUMN)
                ->label('Tarifa mensual por afiliado')
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->badge()
                ->color('success')
                ->formatStateUsing(fn (mixed $state): ?string => OutsourcingMedicalDepartment::formatFee($state))
                ->visible(fn (?Model $record): bool => (bool) $record?->getAttribute(OutsourcingMedicalDepartment::FLAG_COLUMN))
                ->placeholder('Sin tarifa registrada'),
            TextEntry::make('capitation_plans_summary')
                ->label('Planes de capitado')
                ->icon(Heroicon::OutlinedHandRaised)
                ->columnSpanFull()
                ->state(fn (?Model $record): ?HtmlString => $record === null ? null : self::capitationPlansSummary($record))
                ->visible(fn (?Model $record): bool => $record !== null && ((bool) $record->getAttribute(OutsourcingMedicalDepartment::FLAG_COLUMN) || self::capitationPlans($record)->isNotEmpty()))
                ->placeholder('Sin planes de capitado. Se crean en Negocios → Configuración → Planes de Capitado.'),
        ];
    }

    /**
     * Planes de capitado activos del proveedor (solo lectura; se gestionan en Negocios).
     *
     * @return \Illuminate\Support\Collection<int, CapitationPlan>
     */
    private static function capitationPlans(Model $provider): \Illuminate\Support\Collection
    {
        $type = $provider instanceof DoctorNurse ? CapitationPlan::PROVIDER_DOCTOR_NURSE : CapitationPlan::PROVIDER_SUPPLIER;

        return once(static fn (): \Illuminate\Support\Collection => CapitationPlan::query()
            ->forProvider($type, (int) $provider->getKey())
            ->where('status', CapitationPlan::STATUS_ACTIVE)
            ->withCount('benefits')
            ->orderBy('name')
            ->get(['id', 'name', 'cost_per_affiliate_usd', 'payment_frequency', 'valid_until']));
    }

    private static function capitationPlansSummary(Model $provider): ?HtmlString
    {
        $plans = self::capitationPlans($provider);

        if ($plans->isEmpty()) {
            return null;
        }

        return new HtmlString('<ul class="space-y-1 text-sm">'.$plans->map(fn (CapitationPlan $plan): string => '<li><span class="font-semibold text-gray-900 dark:text-white">'.e($plan->name).'</span> · US$ '
            .number_format((float) $plan->cost_per_affiliate_usd, 2, ',', '.').' por afiliado, '.mb_strtolower($plan->payment_frequency->label())
            .' · '.($plan->benefits_count === 1 ? '1 beneficio' : $plan->benefits_count.' beneficios')
            .($plan->valid_until !== null ? ' · hasta '.$plan->valid_until->format('d/m/Y') : '')
            .'</li>')->implode('').'</ul>');
    }

    public static function tableColumn(): TextColumn
    {
        return TextColumn::make(OutsourcingMedicalDepartment::FLAG_COLUMN)
            ->label('Outsourcing médico')
            ->icon('heroicon-o-currency-dollar')
            ->badge()
            ->formatStateUsing(fn (mixed $state, Model $record): string => OutsourcingMedicalDepartment::summary($record))
            ->color(fn (mixed $state): string => (bool) $state ? 'success' : 'gray')
            ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                ->orderBy($query->qualifyColumn(OutsourcingMedicalDepartment::FLAG_COLUMN), $direction)
                ->orderBy($query->qualifyColumn(OutsourcingMedicalDepartment::FEE_COLUMN), $direction))
            ->toggleable();
    }

    public static function tableFilter(): TernaryFilter
    {
        return TernaryFilter::make(OutsourcingMedicalDepartment::FLAG_COLUMN)
            ->label('Departamento Médico Outsourcing')
            ->placeholder('Todos los proveedores')
            ->trueLabel('Solo outsourcing')
            ->falseLabel('Sin outsourcing')
            ->queries(
                true: fn (Builder $query): Builder => $query->where($query->qualifyColumn(OutsourcingMedicalDepartment::FLAG_COLUMN), true),
                false: fn (Builder $query): Builder => $query->where($query->qualifyColumn(OutsourcingMedicalDepartment::FLAG_COLUMN), false),
                blank: fn (Builder $query): Builder => $query,
            );
    }
}
