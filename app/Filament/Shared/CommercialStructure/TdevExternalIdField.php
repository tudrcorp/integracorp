<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialStructure;

use App\Support\CommercialStructure\TdevExternalId;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

final class TdevExternalIdField
{
    public static function agent(): TextInput
    {
        return self::make(
            'id_agent_tdev',
            'ID agente TDEV',
            'Obligatorio cuando la comisión TDEV es mayor a 0.',
        );
    }

    public static function agency(): TextInput
    {
        return self::make(
            'id_agency_tdev',
            'ID agencia TDEV',
            'Obligatorio cuando la comisión TDEV es mayor a 0.',
        );
    }

    private static function make(string $name, string $label, string $helper): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->helperText($helper)
            ->numeric()
            ->integer()
            ->minValue(0)
            ->maxValue(4294967295)
            ->nullable()
            ->required(fn (Get $get): bool => TdevExternalId::isRequired($get('commission_tdev')))
            ->validationMessages([
                'required' => 'Indique el ID TDEV cuando la comisión TDEV es mayor a 0.',
                'integer' => 'El ID TDEV debe ser un número entero.',
                'min' => 'El ID TDEV no puede ser negativo.',
                'max' => 'El ID TDEV excede el máximo permitido.',
            ]);
    }
}
