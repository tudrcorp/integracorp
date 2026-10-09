<?php

declare(strict_types=1);

namespace App\Support\Filament\Operations;

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
     * @return array{0: IconEntry, 1: TextEntry}
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
        ];
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
