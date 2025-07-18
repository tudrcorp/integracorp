<?php

namespace App\Filament\Resources\MassNotifications\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Fieldset;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;

class MassNotificationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Fieldset::make('Informacion Privia')
                    ->schema([
                        Grid::make()
                            ->schema([
                                TextEntry::make('title')
                                ->label('Título')
                            ])->columns(2),
                        Grid::make()
                            ->schema([
                                TextEntry::make('content')
                                ->label('Contenido de la notificación')
                            ])->columnSpanFull(),
                    
                ])->columns(2)->columnSpanFull(),
                Fieldset::make('Imagen')
                    ->schema([
                        ImageEntry::make('image')
                            ->label('Imagen de la notificación')
                            ->imageSize(250)
                    ])->columnSpanFull(),
                Fieldset::make('Estattus de la notificación')
                    ->schema([
                        TextEntry::make('status')
                            ->label('Estatus de la notificación')
                            ->badge(),
                        TextEntry::make('user.name')
                            ->label('Aprobado por:')
                            ->badge()
                            ->color('success')
                    ])->columnSpanFull(),
                
            ]);
    }
}