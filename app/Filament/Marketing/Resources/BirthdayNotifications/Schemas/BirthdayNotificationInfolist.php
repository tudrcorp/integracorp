<?php

namespace App\Filament\Marketing\Resources\BirthdayNotifications\Schemas;

use App\Support\BirthdayNotificationAudience;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BirthdayNotificationInfolist
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
                        Fieldset::make('Información')
                            ->schema([
                                TextEntry::make('title')
                                    ->label('Título:')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('header_title')
                                    ->label('Encabezado(Opcional):')
                                    ->badge()
                                    ->color('success'),

                                TextEntry::make('channels')
                                    ->label('Canales de Envío:')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('data_type')
                                    ->label('Destinatarios:')
                                    ->suffix(fn ($record): string => ($label = BirthdayNotificationAudience::labelForDataType($record->data_type))
                                        ? ' - '.$label
                                        : '')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('status')
                                    ->label('Estatus:')
                                    ->color('success')
                                    ->badge(),
                                Fieldset::make('Cuerpo')
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
                                            }),
                                    ])->columnSpanFull(),
                            ])->columnSpanFull()->columns(5),
                    ])->columnSpanFull(),
            ]);
    }
}
