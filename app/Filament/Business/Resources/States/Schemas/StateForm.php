<?php

namespace App\Filament\Business\Resources\States\Schemas;

use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class StateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Estado')
                    ->description('Información de la entidad.')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Select::make('country_id')
                            ->label('Pais')
                            ->options(DB::table('countries')->pluck('name', 'id')),
                        TextInput::make('definition')
                            ->label('Estado')   
                            ->required(),
                        Select::make('region_id')
                            ->label('Región')
                            ->options(DB::table('regions')->pluck('definition', 'id')),
                    ])->columnSpanFull()->columns(3)
            ]);
    }
}