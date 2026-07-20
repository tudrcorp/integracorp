<?php

namespace App\Filament\Marketing\Resources\BirthdayNotifications\Schemas;

use App\Models\BirthdayNotification;
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
                                Fieldset::make('Métricas de envío (correo)')
                                    ->schema([
                                        TextEntry::make('email_metrics_sent')
                                            ->label('Enviados')
                                            ->state(fn (BirthdayNotification $record): int => $record->deliveryStats()['email']['sent'])
                                            ->badge()
                                            ->color('success'),
                                        TextEntry::make('email_metrics_failed')
                                            ->label('Fallidos')
                                            ->state(fn (BirthdayNotification $record): int => $record->deliveryStats()['email']['failed'])
                                            ->badge()
                                            ->color('danger'),
                                        TextEntry::make('email_metrics_pending')
                                            ->label('Pendientes')
                                            ->state(fn (BirthdayNotification $record): int => $record->deliveryStats()['email']['pending'])
                                            ->badge()
                                            ->color('warning'),
                                        TextEntry::make('email_metrics_skipped')
                                            ->label('Omitidos')
                                            ->state(fn (BirthdayNotification $record): int => $record->deliveryStats()['email']['skipped'])
                                            ->badge()
                                            ->color('gray'),
                                    ])
                                    ->columns(4)
                                    ->visible(fn (BirthdayNotification $record): bool => in_array('email', (array) $record->channels, true)),
                                Fieldset::make('Métricas de envío (WhatsApp)')
                                    ->schema([
                                        TextEntry::make('whatsapp_metrics_sent')
                                            ->label('Enviados')
                                            ->state(fn (BirthdayNotification $record): int => $record->deliveryStats()['whatsapp']['sent'])
                                            ->badge()
                                            ->color('success'),
                                        TextEntry::make('whatsapp_metrics_failed')
                                            ->label('Fallidos')
                                            ->state(fn (BirthdayNotification $record): int => $record->deliveryStats()['whatsapp']['failed'])
                                            ->badge()
                                            ->color('danger'),
                                        TextEntry::make('whatsapp_metrics_pending')
                                            ->label('Pendientes')
                                            ->state(fn (BirthdayNotification $record): int => $record->deliveryStats()['whatsapp']['pending'])
                                            ->badge()
                                            ->color('warning'),
                                        TextEntry::make('whatsapp_metrics_skipped')
                                            ->label('Omitidos')
                                            ->state(fn (BirthdayNotification $record): int => $record->deliveryStats()['whatsapp']['skipped'])
                                            ->badge()
                                            ->color('gray'),
                                    ])
                                    ->columns(4)
                                    ->visible(fn (BirthdayNotification $record): bool => in_array('whatsapp', (array) $record->channels, true)),
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
