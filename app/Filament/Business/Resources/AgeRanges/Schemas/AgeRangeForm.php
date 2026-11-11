<?php

namespace App\Filament\Business\Resources\AgeRanges\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AgeRangeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('plan_id')
                    ->relationship('plan', 'id')
                    ->required()
                    ->default(0),
                TextInput::make('coverage_id')
                    ->numeric(),
                TextInput::make('fee'),
                TextInput::make('code')
                    ->required(),
                TextInput::make('range')
                    ->required()
                    ->disabledOn('edit')
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Las edades se cambian desde la ficha del plan (botón «Editar rango de edad»): así se validan y se actualizan en todo el sistema.' : null),
                TextInput::make('status'),
                TextInput::make('created_by'),
                TextInput::make('age_init')
                    ->numeric()
                    ->disabledOn('edit')
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Las edades se cambian desde la ficha del plan (botón «Editar rango de edad»): así se validan y se actualizan en todo el sistema.' : null),
                TextInput::make('age_end')
                    ->numeric()
                    ->disabledOn('edit')
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Las edades se cambian desde la ficha del plan (botón «Editar rango de edad»): así se validan y se actualizan en todo el sistema.' : null),
            ]);
    }
}
