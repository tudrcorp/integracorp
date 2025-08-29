<?php

namespace App\Filament\Marketing\Resources\Events\Schemas;

use App\Models\Event;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;

class EventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make()
                    ->description('Información Principal del Evento')
                    ->columnSpanFull()
                    ->icon('fontisto-prescription')
                    ->schema([
                        Fieldset::make('Flayer del Evento')
                            ->schema([
                                ImageEntry::make('image')
                                    ->label('Publicidad:')
                                    ->imageHeight(150)
                                    ->square()
                                    ->visibility('public'),
                            ])->columnSpanFull()->columns(5),
                        Fieldset::make('Descripción del Evento')
                            ->schema([
                                TextEntry::make('title')
                                    ->label('Título del Evento:')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('dateInit')
                                    ->label('Fecha de Inicio:')
                                    ->badge(),
                                TextEntry::make('dateEnd')
                                    ->label('Fecha de Culminación:')
                                    ->badge(),
                                TextEntry::make('status')
                                    ->label('Estatus:')
                                    ->color('success')
                                    ->badge(),
                            ])->columnSpanFull()->columns(5),
                    ])->columnSpanFull(),

            ]);
    }
}