<?php

namespace App\Filament\Marketing\Resources\MassNotifications\Schemas;

use App\Models\MassNotification;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                                TextEntry::make('email_subject')
                                    ->label('Asunto del correo:')
                                    ->badge()
                                    ->color('success')
                                    ->placeholder('---')
                                    ->visible(fn (MassNotification $record): bool => in_array('email', (array) $record->channels, true)),

                                TextEntry::make('channels')
                                    ->label('Canales de Envío:')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('date_programed')
                                    ->label('Fecha de Envío Programado:')
                                    ->default(fn (MassNotification $record) => $record->date_programed?->format('d/m/Y H:i') ?? '---')
                                    ->badge()
                                    ->color('success')
                                    ->helperText(fn (MassNotification $record): ?string => $record->isScheduledForFuture() && ! $record->is_sent
                                        ? 'El envío se ejecutará automáticamente en esta fecha (requiere schedule activo).'
                                        : ($record->is_sent ? 'Esta notificación ya fue encolada para envío.' : null)),
                                TextEntry::make('is_sent')
                                    ->label('Encolada para envío:')
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Sí' : 'No')
                                    ->badge()
                                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                                Fieldset::make('Métricas de envío (correo)')
                                    ->schema([
                                        TextEntry::make('email_metrics_sent')
                                            ->label('Enviados')
                                            ->state(fn (MassNotification $record): int => $record->deliveryStats()['email']['sent'])
                                            ->badge()
                                            ->color('success'),
                                        TextEntry::make('email_metrics_failed')
                                            ->label('Fallidos')
                                            ->state(fn (MassNotification $record): int => $record->deliveryStats()['email']['failed'])
                                            ->badge()
                                            ->color('danger'),
                                        TextEntry::make('email_metrics_pending')
                                            ->label('Pendientes')
                                            ->state(fn (MassNotification $record): int => $record->deliveryStats()['email']['pending'])
                                            ->badge()
                                            ->color('warning'),
                                        TextEntry::make('email_metrics_skipped')
                                            ->label('Omitidos')
                                            ->state(fn (MassNotification $record): int => $record->deliveryStats()['email']['skipped'])
                                            ->badge()
                                            ->color('gray'),
                                        TextEntry::make('test_email_success_count')
                                            ->label('Pruebas enviadas')
                                            ->badge()
                                            ->color('info'),
                                        TextEntry::make('test_email_failed_count')
                                            ->label('Pruebas fallidas')
                                            ->badge()
                                            ->color('danger'),
                                        TextEntry::make('last_test_email_to')
                                            ->label('Última prueba a')
                                            ->placeholder('—')
                                            ->columnSpan(2),
                                        TextEntry::make('last_test_email_at')
                                            ->label('Fecha última prueba')
                                            ->dateTime('d/m/Y H:i')
                                            ->placeholder('—'),
                                    ])
                                    ->columns(4)
                                    ->visible(fn (MassNotification $record): bool => in_array('email', (array) $record->channels, true)),
                                Fieldset::make('Métricas de envío (WhatsApp)')
                                    ->schema([
                                        TextEntry::make('whatsapp_metrics_sent')
                                            ->label('Enviados')
                                            ->state(fn (MassNotification $record): int => $record->deliveryStats()['whatsapp']['sent'])
                                            ->badge()
                                            ->color('success'),
                                        TextEntry::make('whatsapp_metrics_failed')
                                            ->label('Fallidos')
                                            ->state(fn (MassNotification $record): int => $record->deliveryStats()['whatsapp']['failed'])
                                            ->badge()
                                            ->color('danger'),
                                        TextEntry::make('whatsapp_metrics_pending')
                                            ->label('Pendientes')
                                            ->state(fn (MassNotification $record): int => $record->deliveryStats()['whatsapp']['pending'])
                                            ->badge()
                                            ->color('warning'),
                                        TextEntry::make('whatsapp_metrics_skipped')
                                            ->label('Omitidos')
                                            ->state(fn (MassNotification $record): int => $record->deliveryStats()['whatsapp']['skipped'])
                                            ->badge()
                                            ->color('gray'),
                                    ])
                                    ->columns(4)
                                    ->visible(fn (MassNotification $record): bool => in_array('whatsapp', (array) $record->channels, true)),
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
                                            }),
                                    ])->columnSpanFull(),
                            ])->columnSpanFull()->columns(3),
                    ])->columnSpanFull(),
            ]);
    }
}
