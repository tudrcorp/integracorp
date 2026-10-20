<?php

namespace App\Filament\Marketing\Resources\BirthdayNotifications\RelationManagers;

use App\Enums\MassNotificationDeliveryStatus;
use App\Models\BirthdayNotification;
use App\Models\BirthdayNotificationDelivery;
use App\Support\Birthday\BirthdayCelebrationRecipientGate;
use App\Support\BirthdayNotificationAudience;
use App\Support\BirthdayNotificationRecipientCatalog;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BirthdayNotificationDeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveries';

    public ?string $viewerDataType = null;

    /** @var Collection<int, BirthdayNotificationDelivery>|null */
    protected ?Collection $deliveryLookup = null;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function mount(): void
    {
        parent::mount();

        $types = $this->getOwnerRecord() instanceof BirthdayNotification
            ? $this->getOwnerRecord()->dataTypes()
            : [];

        if ($this->viewerDataType === null || ! in_array($this->viewerDataType, $types, true)) {
            $this->viewerDataType = $types[0] ?? null;
        }
    }

    public function table(Table $table): Table
    {
        /** @var BirthdayNotification $notification */
        $notification = $this->getOwnerRecord();
        $dataTypes = $notification->dataTypes();
        $activeDataType = $this->resolveViewerDataType($dataTypes);
        $config = BirthdayNotificationRecipientCatalog::configFor($activeDataType);
        $channels = (array) ($notification->channels ?? []);
        $nameColumn = $config['name'];
        $emailColumn = $config['email'];
        $phoneColumn = $config['phone'];
        $birthDateColumn = $config['birth_date'];
        $excludedRecipientIds = $notification->sends_celebration_card
            ? BirthdayCelebrationRecipientGate::excludedIdMap($notification->id, $activeDataType)
            : [];
        $activeLabel = BirthdayNotificationAudience::labelForDataType($activeDataType) ?? $activeDataType;
        $padronLabels = BirthdayNotificationAudience::labelsForDataTypes($dataTypes);

        return $table
            ->query(fn (): Builder => BirthdayNotificationRecipientCatalog::queryFor($notification, $activeDataType))
            ->heading('Destinatarios de la notificación')
            ->description($this->tableDescription($notification, $activeLabel, $padronLabels))
            ->headerActions($this->padronSwitcherActions($notification, $dataTypes, $activeDataType))
            ->columns([
                ToggleColumn::make('receives_celebration')
                    ->label('Recibe la animación')
                    ->hidden(! $notification->sends_celebration_card)
                    ->getStateUsing(fn (Model $record): bool => ! isset($excludedRecipientIds[(int) $record->getKey()]))
                    ->updateStateUsing(function (Model $record, mixed $state) use ($notification, $dataTypes): bool {
                        $receives = $state === true || $state === 1 || $state === '1';
                        $type = $this->resolveViewerDataType($dataTypes);

                        BirthdayCelebrationRecipientGate::setReceives(
                            $notification->id,
                            $type,
                            (int) $record->getKey(),
                            $receives,
                        );

                        return $receives;
                    }),
                TextColumn::make($nameColumn)
                    ->label('Nombre completo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make($emailColumn ?? 'email_placeholder')
                    ->label('Email')
                    ->placeholder('—')
                    ->searchable($emailColumn !== null)
                    ->visible($emailColumn !== null),
                TextColumn::make($phoneColumn ?? 'phone_placeholder')
                    ->label('Teléfono')
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
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->deferFilters(false)
            ->emptyStateHeading('Sin destinatarios en este padrón')
            ->emptyStateDescription(count($dataTypes) > 1
                ? 'Cambia el padrón con los botones de arriba (por ejemplo COLABORADORES) y vuelve a buscar.'
                : 'No hay registros en el padrón asociado a esta notificación.')
            ->paginated([10, 25, 50, 100]);
    }

    /**
     * @param  list<string>  $dataTypes
     * @param  list<string>  $padronLabels
     */
    private function tableDescription(
        BirthdayNotification $notification,
        string $activeLabel,
        array $padronLabels,
    ): string {
        $base = $notification->sends_celebration_card
            ? "Padrón «{$activeLabel}»: todos reciben la tarjeta animada. Apaga «Recibe la animación» a quien no deba recibirla."
            : "Padrón «{$activeLabel}». El filtro de fecha muestra quienes cumplen años ese día.";

        if (count($padronLabels) <= 1) {
            return $base;
        }

        return $base.' Usa los botones de padrón para ver: '.implode(', ', $padronLabels).'.';
    }

    /**
     * @param  list<string>  $dataTypes
     * @return array<int, Action|ActionGroup>
     */
    private function padronSwitcherActions(
        BirthdayNotification $notification,
        array $dataTypes,
        string $activeDataType,
    ): array {
        if (count($dataTypes) <= 1) {
            return [];
        }

        return array_map(
            function (string $type) use ($notification, $activeDataType): Action {
                $label = BirthdayNotificationAudience::labelForDataType($type) ?? $type;

                return Action::make('padron_'.$type)
                    ->label($label)
                    ->button()
                    ->color($activeDataType === $type ? 'primary' : 'gray')
                    ->badge(fn (): string => (string) BirthdayNotificationRecipientCatalog::queryFor($notification, $type)->count())
                    ->action(function () use ($type): void {
                        $this->viewerDataType = $type;
                        $this->deliveryLookup = null;
                        $this->resetTable();
                    });
            },
            $dataTypes,
        );
    }

    /**
     * @param  list<string>  $dataTypes
     */
    private function resolveViewerDataType(array $dataTypes): string
    {
        if ($dataTypes === []) {
            return 'affiliates';
        }

        if (is_string($this->viewerDataType) && in_array($this->viewerDataType, $dataTypes, true)) {
            return $this->viewerDataType;
        }

        $this->viewerDataType = $dataTypes[0];

        return $this->viewerDataType;
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
