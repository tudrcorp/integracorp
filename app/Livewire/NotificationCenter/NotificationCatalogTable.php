<?php

declare(strict_types=1);

namespace App\Livewire\NotificationCenter;

use App\Enums\NotificationAudience;
use App\Models\NotificationCatalogEntry;
use App\Models\OutboundMessage;
use App\Models\SystemNotificationRecipientSetting;
use App\Support\NotificationCenter\NotificationCatalogDefaults;
use App\Support\NotificationCenter\NotificationCenterAccess;
use App\Support\NotificationCenter\NotificationCenterSettings;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Centro de notificaciones → Notificaciones: todos los tipos de mensaje del sistema
 * (internos y externos) con sus interruptores en la misma fila.
 */
class NotificationCatalogTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function mount(): void
    {
        abort_unless(NotificationCenterAccess::canAccess(), 403);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => self::query())
            ->defaultGroup(
                Group::make('area')
                    ->label('Área')
                    ->getTitleFromRecordUsing(fn (NotificationCatalogEntry $record): string => NotificationCatalogDefaults::AREAS[$record->area] ?? 'Sin clasificar')
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderByRaw(self::areaOrderSql().' '.($direction === 'desc' ? 'desc' : 'asc')))
                    ->collapsible(),
            )
            ->groupingSettingsHidden()
            ->defaultSort('name')
            ->searchable()
            ->searchPlaceholder('Buscar notificación…')
            ->searchDebounce('350ms')
            ->deferFilters(false)
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->emptyStateIcon('heroicon-o-bell-slash')
            ->emptyStateHeading('Sin notificaciones')
            ->emptyStateDescription('El catálogo se carga solo en la primera visita. Si sigue vacío, corra php artisan notifications:catalog-seed.')
            ->columns([
                TextColumn::make('name')
                    ->label('Notificación')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->wrap()
                    ->description(fn (NotificationCatalogEntry $record): ?string => self::rowHint($record)),
                TextColumn::make('audience')
                    ->label('Para')
                    ->badge()
                    ->formatStateUsing(fn (NotificationAudience $state): string => $state->label())
                    ->color(fn (NotificationAudience $state): string => $state->color())
                    ->icon(fn (NotificationAudience $state): string => $state->icon()),
                TextColumn::make('channels')
                    ->label('Canales')
                    ->badge()
                    ->separator(',')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'whatsapp' => 'WhatsApp',
                        'mail' => 'Correo',
                        'telegram' => 'Telegram',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'whatsapp' => 'success',
                        'mail' => 'warning',
                        default => 'info',
                    }),
                ToggleColumn::make('is_active')
                    ->label('Activa')
                    ->alignCenter()
                    ->getStateUsing(fn (NotificationCatalogEntry $record): bool => self::isActive($record))
                    ->disabled(fn (NotificationCatalogEntry $record): bool => $record->is_critical)
                    ->tooltip(fn (NotificationCatalogEntry $record): ?string => $record->is_critical ? 'Tipo crítico: use el menú ⋯ para pausarlo.' : null)
                    ->updateStateUsing(fn (NotificationCatalogEntry $record, bool $state): bool => $this->toggle($record, 'is_active', $state)),
                ToggleColumn::make('whatsapp_enabled')
                    ->label('WhatsApp')
                    ->alignCenter()
                    ->onColor('success')
                    ->disabled(fn (NotificationCatalogEntry $record): bool => self::channelLocked($record, 'whatsapp'))
                    ->tooltip(fn (NotificationCatalogEntry $record): ?string => self::channelTooltip($record, 'whatsapp'))
                    ->updateStateUsing(fn (NotificationCatalogEntry $record, bool $state): bool => $this->toggle($record, 'whatsapp_enabled', $state)),
                ToggleColumn::make('mail_enabled')
                    ->label('Correo')
                    ->alignCenter()
                    ->onColor('warning')
                    ->disabled(fn (NotificationCatalogEntry $record): bool => self::channelLocked($record, 'mail'))
                    ->tooltip(fn (NotificationCatalogEntry $record): ?string => self::channelTooltip($record, 'mail'))
                    ->updateStateUsing(fn (NotificationCatalogEntry $record, bool $state): bool => $this->toggle($record, 'mail_enabled', $state)),
                TextColumn::make('last_sent_at')
                    ->label('Último envío')
                    ->since()
                    ->placeholder('Sin envíos')
                    ->description(fn (NotificationCatalogEntry $record): ?string => $record->is_bulk
                        ? 'Bitácora propia'
                        : ((int) $record->sent_week > 0 ? number_format((int) $record->sent_week, 0, ',', '.').' en 7 días' : null))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('audience')
                    ->label('Para')
                    ->options([NotificationAudience::Internal->value => 'Interna (personal TDG)', NotificationAudience::External->value => 'Externa (clientes, red, proveedores)']),
                SelectFilter::make('area')
                    ->label('Área')
                    ->options(NotificationCatalogDefaults::AREAS),
                SelectFilter::make('channel')
                    ->label('Usa el canal')
                    ->options(['whatsapp' => 'WhatsApp', 'mail' => 'Correo', 'telegram' => 'Telegram'])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null) ? $query->whereJsonContains('channels', $data['value']) : $query),
                TernaryFilter::make('paused')
                    ->label('Estado')
                    ->placeholder('Todas')
                    ->trueLabel('Con algo apagado')
                    ->falseLabel('Todo encendido')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where(fn (Builder $q): Builder => $q->where('is_active', false)->orWhere('whatsapp_enabled', false)->orWhere('mail_enabled', false)),
                        false: fn (Builder $query): Builder => $query->where('is_active', true)->where('whatsapp_enabled', true)->where('mail_enabled', true),
                    ),
                TernaryFilter::make('is_discovered')
                    ->label('Sin clasificar')
                    ->placeholder('Todas')
                    ->trueLabel('Solo las detectadas solas')
                    ->falseLabel('Solo las catalogadas'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('viewLog')
                        ->label('Ver envíos')
                        ->icon('heroicon-o-queue-list')
                        ->hidden(fn (NotificationCatalogEntry $record): bool => $record->is_bulk)
                        ->action(fn (NotificationCatalogEntry $record) => $this->dispatch('notification-center-open-log', catalogKey: $record->key)),
                    Action::make('configureRecipients')
                        ->label('Configurar destinatarios')
                        ->icon('heroicon-o-users')
                        ->visible(fn (NotificationCatalogEntry $record): bool => $record->isSystemNotice())
                        ->action(fn (NotificationCatalogEntry $record) => $this->dispatch('notification-center-open-internal', key: (string) $record->system_key)),
                    Action::make('edit')
                        ->label('Nombrar y clasificar')
                        ->icon('heroicon-o-pencil-square')
                        ->modalHeading(fn (NotificationCatalogEntry $record): string => 'Clasificar «'.$record->name.'»')
                        ->modalDescription('El nombre y el área solo ordenan la pantalla. «Para» indica si el mensaje es para el personal de TDG o para fuera de TDG.')
                        ->modalWidth(Width::Large)
                        ->fillForm(fn (NotificationCatalogEntry $record): array => [
                            'name' => $record->name,
                            'area' => $record->area,
                            'audience' => $record->audience->value,
                            'description' => $record->description,
                        ])
                        ->schema(fn (NotificationCatalogEntry $record): array => [
                            TextInput::make('name')->label('Nombre')->required()->maxLength(160),
                            Select::make('area')->label('Área')->options(NotificationCatalogDefaults::AREAS)->required()->native(false),
                            ToggleButtons::make('audience')
                                ->label('Para')
                                ->inline()
                                ->options([NotificationAudience::Internal->value => 'Interna', NotificationAudience::External->value => 'Externa'])
                                ->icons([NotificationAudience::Internal->value => 'heroicon-m-building-office-2', NotificationAudience::External->value => 'heroicon-m-globe-alt'])
                                ->colors([NotificationAudience::Internal->value => 'info', NotificationAudience::External->value => 'warning'])
                                ->disabled($record->isSystemNotice())
                                ->helperText($record->isSystemNotice() ? 'Los avisos internos del Centro siempre van al personal de TDG.' : null)
                                ->required(),
                            Textarea::make('description')->label('Nota para el equipo (opcional)')->rows(2)->maxLength(500),
                        ])
                        ->action(function (array $data, NotificationCatalogEntry $record, Action $action): void {
                            try {
                                NotificationCenterSettings::updateEntry($record, $data);
                            } catch (InvalidArgumentException $exception) {
                                Notification::make()->title($exception->getMessage())->danger()->send();
                                $action->halt();
                            }

                            $this->dispatch('notification-center-changed');

                            Notification::make()->title('Notificación actualizada')->success()->send();
                        }),
                    Action::make('pauseCritical')
                        ->label(fn (NotificationCatalogEntry $record): string => self::isActive($record) ? 'Pausar tipo crítico' : 'Reactivar tipo crítico')
                        ->icon(fn (NotificationCatalogEntry $record): string => self::isActive($record) ? 'heroicon-o-pause-circle' : 'heroicon-o-play-circle')
                        ->color(fn (NotificationCatalogEntry $record): string => self::isActive($record) ? 'danger' : 'success')
                        ->visible(fn (NotificationCatalogEntry $record): bool => $record->is_critical && NotificationCenterAccess::canManageCritical())
                        ->requiresConfirmation()
                        ->modalHeading(fn (NotificationCatalogEntry $record): string => (self::isActive($record) ? 'Pausar' : 'Reactivar').' «'.$record->name.'»')
                        ->modalDescription(fn (NotificationCatalogEntry $record): string => self::isActive($record)
                            ? 'Es un mensaje crítico (claves OTP, alertas o acceso al sistema). Mientras esté pausado no le llegará a nadie por ningún canal. Escriba PAUSAR para confirmar.'
                            : 'Vuelve a enviarse con normalidad.')
                        ->schema(fn (NotificationCatalogEntry $record): array => self::isActive($record) ? [
                            TextInput::make('confirmation')->label('Confirmación')->placeholder('PAUSAR')->required()->in(['PAUSAR'])
                                ->validationMessages(['in' => 'Escriba PAUSAR en mayúsculas.']),
                        ] : [])
                        ->action(function (NotificationCatalogEntry $record): void {
                            $this->toggle($record, 'is_active', ! self::isActive($record), true);
                        }),
                ])->tooltip('Más acciones'),
            ])
            ->recordClasses(fn (NotificationCatalogEntry $record): ?string => self::isActive($record) ? null : 'opacity-60');
    }

    public function render(): View
    {
        return view('livewire.notification-center.catalog-table');
    }

    /**
     * @return Builder<NotificationCatalogEntry>
     */
    public static function query(): Builder
    {
        $since = now()->subDays(7);

        return NotificationCatalogEntry::query()
            ->select('notification_catalog_entries.*')
            ->selectSub(
                SystemNotificationRecipientSetting::query()
                    ->select('is_active')
                    ->whereColumn('system_notification_recipient_settings.notification_key', 'notification_catalog_entries.system_key')
                    ->limit(1),
                'system_is_active',
            )
            ->selectSub(
                OutboundMessage::query()->selectRaw('max(created_at)')->whereColumn('outbound_messages.catalog_key', 'notification_catalog_entries.key'),
                'last_sent_at',
            )
            ->selectSub(
                OutboundMessage::query()->selectRaw('count(*)')->whereColumn('outbound_messages.catalog_key', 'notification_catalog_entries.key')->where('created_at', '>=', $since),
                'sent_week',
            );
    }

    private function toggle(NotificationCatalogEntry $record, string $flag, bool $state, bool $confirmedCritical = false): bool
    {
        abort_unless(NotificationCenterAccess::canAccess(), 403);

        try {
            NotificationCenterSettings::setEntryFlag($record, $flag, $state, $confirmedCritical);
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title('No se aplicó el cambio')->body($exception->getMessage())->warning()->send();

            return ! $state;
        }

        /** La página recalcula las insignias de sus pestañas. */
        $this->dispatch('notification-center-changed');

        $label = match ($flag) {
            'whatsapp_enabled' => 'WhatsApp',
            'mail_enabled' => 'Correo',
            default => 'La notificación',
        };

        Notification::make()
            ->title($label.($state ? ' encendido' : ' apagado').' · '.$record->name)
            ->body($state ? 'Vuelve a enviarse desde ahora.' : 'Desde ahora no sale; cada intento queda en la bitácora como «Bloqueado».')
            ->color($state ? 'success' : 'warning')
            ->icon($state ? 'heroicon-o-check-circle' : 'heroicon-o-no-symbol')
            ->send();

        return $state;
    }

    private static function isActive(NotificationCatalogEntry $record): bool
    {
        if ($record->isSystemNotice()) {
            $value = $record->getAttribute('system_is_active');

            return $value === null ? true : (bool) $value;
        }

        return $record->is_active;
    }

    private static function channelLocked(NotificationCatalogEntry $record, string $channel): bool
    {
        return $record->isSystemNotice()
            || $record->is_critical
            || ! in_array($channel, (array) ($record->channels ?? []), true);
    }

    private static function channelTooltip(NotificationCatalogEntry $record, string $channel): ?string
    {
        return match (true) {
            $record->isSystemNotice() => 'Aviso interno: el canal se elige con sus destinatarios (⋯ → Configurar destinatarios).',
            $record->is_critical => 'Tipo crítico: no se apaga por canal.',
            ! in_array($channel, (array) ($record->channels ?? []), true) => 'Este tipo no envía por '.($channel === 'mail' ? 'correo' : 'WhatsApp').'.',
            default => null,
        };
    }

    private static function rowHint(NotificationCatalogEntry $record): ?string
    {
        $parts = [];

        if ($record->is_critical) {
            $parts[] = 'Crítico: no lo detiene el interruptor general';
        }

        if ($record->is_bulk) {
            $parts[] = 'Masivo';
        }

        if ($record->is_discovered) {
            $parts[] = 'Detectado solo: nómbrelo y clasifíquelo';
        }

        if ($record->isSystemNotice()) {
            $parts[] = 'Aviso interno del Centro';
        }

        if (filled($record->description)) {
            $parts[] = (string) $record->description;
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private static function areaOrderSql(): string
    {
        $cases = collect(array_keys(NotificationCatalogDefaults::AREAS))
            ->map(fn (string $area, int $index): string => "when '".$area."' then ".$index)
            ->implode(' ');

        return 'case area '.$cases.' else 99 end';
    }
}
