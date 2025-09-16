<?php

namespace App\Filament\Marketing\Resources\MassNotifications\Schemas;

use Filament\Schemas\Schema;
use App\Models\MassNotification;
use Filament\Schemas\Components\Section;
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
                Section::make()
                    ->description('Información Principal de la Notificación')
                    ->columnSpanFull()
                    ->icon('fontisto-prescription')
                    ->schema([
                        Fieldset::make('Imagen de la Notificación')
                            ->schema([
                                ImageEntry::make('file')
                                    ->label('Publicidad:')
                                    ->imageHeight('auto')
                                    ->imageWidth('70%')
                                    ->square()
                                    ->visibility('public'),
                            ])->columnSpanFull()->columns(5),
                        Fieldset::make('Información de la Notificación')
                            ->schema([
                                TextEntry::make('title')
                                    ->label('Título de la Notificación:')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('header_title')
                                    ->label('Encabezado de la Notificación(Opcional):')
                                    ->badge()
                                    ->color('success'),
                                
                                TextEntry::make('channels')
                                    ->label('Canales de Envío:')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('date_programed')
                                    ->label('Fecha de Envío Programado:')
                                    ->default(fn(MassNotification $record) => $record->date_programed ? $record->date_programed : '---')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('status')
                                    ->label('Estatus:')
                                    ->color('success')
                                    ->badge(),
                                Fieldset::make('Acciones')
                                    ->schema([
                                        TextEntry::make('content')
                                            ->label('Copy:')
                                            ->badge()
                                            ->color('success')
                                            ->limit(50)
                                            ->tooltip(function (TextEntry $component): ?string {
                                                $state = $component->getState();

                                                if (strlen($state) <= $component->getCharacterLimit()) {
                                                    return null;
                                                }

                                                // Only render the tooltip if the entry contents exceeds the length limit.
                                                return $state;
                                            })
                                    ])->columnSpanFull(),
                            ])->columnSpanFull()->columns(3),
                    ])->columnSpanFull(),
                ]);
    }
}