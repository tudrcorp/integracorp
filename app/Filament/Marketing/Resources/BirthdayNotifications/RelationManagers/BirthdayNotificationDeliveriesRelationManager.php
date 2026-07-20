<?php

namespace App\Filament\Marketing\Resources\BirthdayNotifications\RelationManagers;

use App\Enums\MassNotificationDeliveryStatus;
use App\Models\BirthdayNotification;
use App\Models\BirthdayNotificationDelivery;
use App\Support\BirthdayNotificationRecipientCatalog;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BirthdayNotificationDeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveries';

    /** @var Collection<int, BirthdayNotificationDelivery>|null */
    protected ?Collection $deliveryLookup = null;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        /** @var BirthdayNotification $notification */
        $notification = $this->getOwnerRecord();
        $config = BirthdayNotificationRecipientCatalog::configForNotification($notification);
        $channels = (array) ($notification->channels ?? []);
        $nameColumn = $config['name'];
        $emailColumn = $config['email'];
        $phoneColumn = $config['phone'];
        $birthDateColumn = $config['birth_date'];

        return $table
            ->query(fn (): Builder => BirthdayNotificationRecipientCatalog::queryFor($notification))
            ->heading('Destinatarios de la notificación')
            ->description('Padrón asociado al tipo de destinatario de esta tarjeta. El filtro de fecha muestra quienes cumplen años ese día.')
            ->columns([
                TextColumn::make($nameColumn)
                    ->label('Full Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make($emailColumn ?? 'email_placeholder')
                    ->label('Email')
                    ->placeholder('—')
                    ->searchable($emailColumn !== null)
                    ->visible($emailColumn !== null),
                TextColumn::make($phoneColumn ?? 'phone_placeholder')
                    ->label('Phone')
                    ->placeholder('—')
                    ->searchable($phoneColumn !== null)
                    ->visible($phoneColumn !== null),
                TextColumn::make($birthDateColumn ?? 'birth_date_placeholder')
                    ->label('Fecha de cumpleaños')
                    ->placeholder('—')
                    ->sortable($birthDateColumn !== null)
                    ->visible($birthDateColumn !== null),
                TextColumn::make('email_status')
                    ->label('Correo')
                    ->badge()
                    ->state(function (Model $record) use ($notification, $config): ?MassNotificationDeliveryStatus {
                        return $this->deliveryFor($notification, $record, $config)?->email_status;
                    })
                    ->formatStateUsing(fn (?MassNotificationDeliveryStatus $state): string => BirthdayNotificationRecipientCatalog::statusLabel($state))
                    ->color(fn (?MassNotificationDeliveryStatus $state): string => BirthdayNotificationRecipientCatalog::statusColor($state))
                    ->tooltip(function (Model $record) use ($notification, $config): ?string {
                        return $this->deliveryFor($notification, $record, $config)?->email_error;
                    })
                    ->visible(in_array('email', $channels, true)),
                TextColumn::make('email_sent_at')
                    ->label('Enviado (correo)')
                    ->state(function (Model $record) use ($notification, $config): ?string {
                        $sentAt = $this->deliveryFor($notification, $record, $config)?->email_sent_at;

                        return $sentAt?->format('d/m/Y H:i');
                    })
                    ->placeholder('—')
                    ->visible(in_array('email', $channels, true)),
                TextColumn::make('whatsapp_status')
                    ->label('WhatsApp')
                    ->badge()
                    ->state(function (Model $record) use ($notification, $config): ?MassNotificationDeliveryStatus {
                        return $this->deliveryFor($notification, $record, $config)?->whatsapp_status;
                    })
                    ->formatStateUsing(fn (?MassNotificationDeliveryStatus $state): string => BirthdayNotificationRecipientCatalog::statusLabel($state))
                    ->color(fn (?MassNotificationDeliveryStatus $state): string => BirthdayNotificationRecipientCatalog::statusColor($state))
                    ->tooltip(function (Model $record) use ($notification, $config): ?string {
                        return $this->deliveryFor($notification, $record, $config)?->whatsapp_error;
                    })
                    ->visible(in_array('whatsapp', $channels, true)),
                TextColumn::make('whatsapp_sent_at')
                    ->label('Enviado (WhatsApp)')
                    ->state(function (Model $record) use ($notification, $config): ?string {
                        $sentAt = $this->deliveryFor($notification, $record, $config)?->whatsapp_sent_at;

                        return $sentAt?->format('d/m/Y H:i');
                    })
                    ->placeholder('—')
                    ->visible(in_array('whatsapp', $channels, true)),
            ])
            ->filters([
                Filter::make('birthday_date')
                    ->label('Fecha de cumpleaños')
                    ->form([
                        DatePicker::make('fecha')
                            ->label('Cumpleaños el día'),
                    ])
                    ->query(function (Builder $query, array $data) use ($birthDateColumn): Builder {
                        if ($birthDateColumn === null || blank($data['fecha'] ?? null)) {
                            return $query;
                        }

                        return BirthdayNotificationRecipientCatalog::applyBirthdayDateFilter(
                            $query,
                            $birthDateColumn,
                            Carbon::parse($data['fecha']),
                        );
                    })
                    ->indicateUsing(function (array $data): array {
                        if (blank($data['fecha'] ?? null)) {
                            return [];
                        }

                        return [
                            'fecha' => 'Cumpleaños el '.Carbon::parse($data['fecha'])->format('d/m'),
                        ];
                    }),
            ])
            ->paginated([10, 25, 50, 100]);
    }

    /**
     * @param  array{model: class-string<Model>, name: string, email: string|null, phone: string|null, birth_date: string|null}  $config
     */
    private function deliveryFor(BirthdayNotification $notification, Model $record, array $config): ?BirthdayNotificationDelivery
    {
        $this->deliveryLookup ??= BirthdayNotificationRecipientCatalog::deliveriesForNotification($notification);

        return BirthdayNotificationRecipientCatalog::matchDelivery(
            $this->deliveryLookup,
            BirthdayNotificationRecipientCatalog::recipientName($record, $config),
            BirthdayNotificationRecipientCatalog::recipientEmail($record, $config),
            BirthdayNotificationRecipientCatalog::recipientPhone($record, $config),
        );
    }
}
