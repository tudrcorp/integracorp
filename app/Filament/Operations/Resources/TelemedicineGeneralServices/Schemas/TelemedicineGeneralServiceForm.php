<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineGeneralServices\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TelemedicineGeneralServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->afterStateUpdatedJs(<<<'JS'
                        $set('name', $state.toUpperCase());
                    JS)
                    ->helperText('Se guardará en mayúsculas.'),
                Textarea::make('description')
                    ->label('Descripción')
                    ->rows(3)
                    ->maxLength(1000)
                    ->columnSpanFull(),
                Select::make('status')
                    ->label('Estado')
                    ->options([
                        'ACTIVO' => 'ACTIVO',
                        'INACTIVO' => 'INACTIVO',
                    ])
                    ->default('ACTIVO')
                    ->required()
                    ->native(false),
            ]);
    }
}
