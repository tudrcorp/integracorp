<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\NotificationDeliveries\Tables;

use App\Enums\NotificationDeliveryChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\SystemNotificationKey;
use App\Models\NotificationDelivery;
use App\Support\Telegram\TelegramLinkDelivery;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class NotificationDeliveriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->poll('10s')
            ->emptyStateIcon('heroicon-o-queue-list')
            ->emptyStateHeading('Todavía no hay envíos')
            ->emptyStateDescription('Envíe la «Prueba de notificación a Telegram» desde el Centro de notificaciones y aparecerá aquí.')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y h:i:s A')
                    ->description(fn (NotificationDelivery $record): ?string => $record->created_at?->diffForHumans())
                    ->sortable(),
                TextColumn::make('notification_key')
                    ->label('Aviso')
                    ->formatStateUsing(fn (string $state): string => $state === TelegramLinkDelivery::NOTIFICATION_KEY ? 'Enlace de vinculación a Telegram' : (SystemNotificationKey::tryFrom($state)?->label() ?? $state))
                    ->description(fn (NotificationDelivery $record): string => $record->title)
                    ->wrap(),
                TextColumn::make('channel')
                    ->label('Canal')
                    ->badge()
                    ->formatStateUsing(fn (NotificationDeliveryChannel $state): string => $state->label())
                    ->color(fn (NotificationDeliveryChannel $state): string => match ($state) {
                        NotificationDeliveryChannel::Telegram => 'info',
                        NotificationDeliveryChannel::WhatsApp => 'success',
                        NotificationDeliveryChannel::Mail => 'gray',
                    })
                    ->description(fn (NotificationDelivery $record): ?string => $record->parent_id !== null ? 'Respaldo' : null),
                TextColumn::make('target_label')
                    ->label('Para')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Resultado')
                    ->badge()
                    ->formatStateUsing(fn (NotificationDeliveryStatus $state): string => $state->label())
                    ->color(fn (NotificationDeliveryStatus $state): string => $state->color())
                    ->description(fn (NotificationDelivery $record): ?string => $record->attempts > 1 ? $record->attempts.' intentos' : null),
                TextColumn::make('error')
                    ->label('Detalle')
                    ->limit(70)
                    ->tooltip(fn (NotificationDelivery $record): ?string => $record->error)
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('sent_at')
                    ->label('Entregado')
                    ->dateTime('h:i:s A')
                    ->placeholder('—'),
                TextColumn::make('created_by')
                    ->label('Originado por')
                    ->placeholder('Sistema')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Resultado')
                    ->options(collect(NotificationDeliveryStatus::cases())->mapWithKeys(fn (NotificationDeliveryStatus $status): array => [$status->value => $status->label()])->all()),
                SelectFilter::make('channel')
                    ->label('Canal')
                    ->options(collect(NotificationDeliveryChannel::cases())->mapWithKeys(fn (NotificationDeliveryChannel $channel): array => [$channel->value => $channel->label()])->all()),
            ]);
    }
}
