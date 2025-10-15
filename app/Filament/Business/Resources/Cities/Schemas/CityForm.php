<?php

namespace App\Filament\Business\Resources\Cities\Schemas;

use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class CityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ciudad')
                    ->collapsible()
                    ->description('Información de la entidad.')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Select::make('country_id')
                            ->options(DB::table('countries')->pluck('name', 'id')),
                        Select::make('state_id')
                            ->options(DB::table('states')->pluck('definition', 'id')),
                        TextInput::make('definition')
                            ->label('Ciudad')
                            ->required(),
                        
                    ])->columnSpanFull()->columns(3),
            ]);
    }
}