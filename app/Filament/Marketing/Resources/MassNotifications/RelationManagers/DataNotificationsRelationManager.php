<?php

namespace App\Filament\Marketing\Resources\MassNotifications\RelationManagers;

use App\Enums\MassNotificationDeliveryStatus;
use App\Filament\Marketing\Resources\AffiliationCorporates\AffiliationCorporateResource;
use App\Filament\Marketing\Resources\Affiliations\AffiliationResource;
use App\Filament\Marketing\Resources\Agencies\AgencyResource;
use App\Filament\Marketing\Resources\Agents\AgentResource;
use App\Filament\Marketing\Resources\InfoFrees\InfoFreeResource;
use App\Models\DataNotification;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DataNotificationsRelationManager extends RelationManager
{
    protected static string $relationship = 'dataNotifications';

    public function table(Table $table): Table
    {
        $channels = (array) ($this->getOwnerRecord()->channels ?? []);

        return $table
            ->heading('Data asociada a la notificación')
            ->columns([
                TextColumn::make('fullName')->label('Full Name'),
                TextColumn::make('email')->label('Email'),
                TextColumn::make('phone')->label('Phone'),
                TextColumn::make('email_status')
                    ->label('Correo')
                    ->badge()
                    ->formatStateUsing(fn (?MassNotificationDeliveryStatus $state): string => $state?->label() ?? '—')
                    ->color(fn (?MassNotificationDeliveryStatus $state): string => match ($state) {
                        MassNotificationDeliveryStatus::Sent => 'success',
                        MassNotificationDeliveryStatus::Failed => 'danger',
                        MassNotificationDeliveryStatus::Pending => 'warning',
                        MassNotificationDeliveryStatus::Skipped => 'gray',
                        default => 'gray',
                    })
                    ->tooltip(fn (DataNotification $record): ?string => $record->email_error)
                    ->visible(in_array('email', $channels, true)),
                TextColumn::make('email_sent_at')
                    ->label('Enviado (correo)')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->visible(in_array('email', $channels, true)),
                TextColumn::make('whatsapp_status')
                    ->label('WhatsApp')
                    ->badge()
                    ->formatStateUsing(fn (?MassNotificationDeliveryStatus $state): string => $state?->label() ?? '—')
                    ->color(fn (?MassNotificationDeliveryStatus $state): string => match ($state) {
                        MassNotificationDeliveryStatus::Sent => 'success',
                        MassNotificationDeliveryStatus::Failed => 'danger',
                        MassNotificationDeliveryStatus::Pending => 'warning',
                        MassNotificationDeliveryStatus::Skipped => 'gray',
                        default => 'gray',
                    })
                    ->tooltip(fn (DataNotification $record): ?string => $record->whatsapp_error)
                    ->visible(in_array('whatsapp', $channels, true)),
                TextColumn::make('whatsapp_sent_at')
                    ->label('Enviado (WhatsApp)')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->visible(in_array('whatsapp', $channels, true)),
            ])
            ->headerActions([
                Action::make('add_agency')
                    ->label('Agencias')
                    ->color('warning')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => AgencyResource::getUrl('index')),
                Action::make('add_agents')
                    ->label('Agentes')
                    ->color('warning')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => AgentResource::getUrl('index')),
                Action::make('add_corporatives')
                    ->label('Corporativos')
                    ->color('success')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => AffiliationCorporateResource::getUrl('index')),
                Action::make('add_individuals')
                    ->label('Individuales')
                    ->color('success')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => AffiliationResource::getUrl('index')),
                Action::make('add_info_free')
                    ->label('Data Externa(FREE)')
                    ->color('info')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => InfoFreeResource::getUrl('index')),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
