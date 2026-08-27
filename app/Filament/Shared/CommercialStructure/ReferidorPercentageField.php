<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialStructure;

use App\Support\CommercialStructure\ReferidorAccess;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class ReferidorPercentageField
{
    public static function make(bool $forceReadOnly = false): TextInput
    {
        return TextInput::make('referidor_percentage')
            ->label('Porcentaje de referidor')
            ->helperText(function () use ($forceReadOnly): string {
                if ($forceReadOnly) {
                    return 'Solo el administrador del sistema puede asignar este porcentaje.';
                }

                if (ReferidorAccess::userCanManage()) {
                    return 'Porcentaje asignado a este referidor. Use punto como separador decimal.';
                }

                return 'No tiene permiso para asignar el porcentaje de referidor. Solicítelo al administrador del sistema.';
            })
            ->prefix('%')
            ->numeric()
            ->minValue(0)
            ->maxValue(100)
            ->required(fn (Get $get): bool => (bool) $get('is_referidor') && ! $forceReadOnly && ReferidorAccess::userCanManage())
            ->visible(fn (Get $get): bool => (bool) $get('is_referidor'))
            ->disabled(fn (): bool => $forceReadOnly || ! ReferidorAccess::userCanManage())
            ->dehydrated(fn (): bool => ! $forceReadOnly && ReferidorAccess::userCanManage())
            ->dehydratedWhenHidden()
            ->dehydrateStateUsing(function (mixed $state, Get $get): mixed {
                if (! $get('is_referidor')) {
                    return null;
                }

                if ($state === '' || $state === null) {
                    return null;
                }

                return $state;
            })
            ->validationMessages([
                'required' => 'El referidor debe tener un porcentaje asignado.',
                'numeric' => 'Campo tipo numérico.',
                'min' => 'El porcentaje no puede ser menor a 0.',
                'max' => 'El porcentaje no puede ser mayor a 100.',
            ]);
    }

    public static function entry(): TextEntry
    {
        return TextEntry::make('referidor_percentage')
            ->label('Porcentaje de referidor')
            ->suffix('%')
            ->numeric(decimalPlaces: 2)
            ->placeholder('—')
            ->visible(fn (Model $record): bool => (bool) $record->getAttribute('is_referidor'));
    }

    public static function column(): TextColumn
    {
        return TextColumn::make('referidor_percentage')
            ->label('% Referidor')
            ->suffix('%')
            ->alignCenter()
            ->numeric(2)
            ->placeholder('—')
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: false);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeFormData(array $data): array
    {
        if (! ReferidorAccess::userCanManage()) {
            unset($data['referidor_percentage']);

            return $data;
        }

        if (! filter_var($data['is_referidor'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $data['referidor_percentage'] = null;

            return $data;
        }

        if (! filled($data['referidor_percentage'] ?? null)) {
            throw ValidationException::withMessages([
                'referidor_percentage' => 'El referidor debe tener un porcentaje asignado.',
            ]);
        }

        $percentage = (float) $data['referidor_percentage'];

        if ($percentage < 0 || $percentage > 100) {
            throw ValidationException::withMessages([
                'referidor_percentage' => 'El porcentaje de referidor debe estar entre 0 y 100.',
            ]);
        }

        return $data;
    }
}
