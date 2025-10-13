<?php

namespace App\Filament\Business\Resources\Regions\Schemas;

use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Console\View\Components\Secret;

class RegionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Región')
                    ->description('Información de la entidad.')
                    ->schema([
                        TextInput::make('definition')
                            ->label('Región')   
                            ->required(),
                        Select::make('country_id')
                            ->label('Pais')
                            ->options(DB::table('countries')->pluck('name', 'id')),
                    ])->columnSpanFull()->columns(4)
            ]);
    }
}