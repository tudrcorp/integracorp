<?php

declare(strict_types=1);

namespace App\Livewire\NotificationCenter;

use App\Enums\NotificationAudience;
use App\Enums\OutboundChannel;
use App\Enums\OutboundMessageStatus;
use App\Models\NotificationCatalogEntry;
use App\Models\OutboundMessage;
use App\Support\NotificationCenter\NotificationCenterAccess;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Centro de notificaciones → Bitácora: lo que salió (o se bloqueó) por WhatsApp,
 * correo y Telegram, interno y externo, con los contadores del día arriba.
 *
 * Ligera: últimos 7 días por defecto, consultas sobre índices por fecha, sin sondeo
 * automático (se actualiza al pulsar «Actualizar» o al cambiar un filtro).
 */
class OutboundMessagesTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** Tipo con el que se abrió la bitácora desde el catálogo. */
    #[Locked]
    public ?string $catalogKey = null;

    /** Atajo de los contadores: «canal:audiencia», «failed» o «blocked». */
    public ?string $quick = null;

    public function mount(?string $catalogKey = null): void
    {
        abort_unless(NotificationCenterAccess::canAccess(), 403);

        $this->catalogKey = filled($catalogKey) ? $catalogKey : null;
    }

    public function applyQuick(?string $quick): void
    {
        $this->quick = $this->quick === $quick ? null : $quick;
        $this->resetPage();
        unset($this->todayStats);
    }

    public function clearCatalogFilter(): void
    {
        $this->catalogKey = null;
        $this->resetPage();
    }

    public function refreshLog(): void
    {
        unset($this->todayStats);
        $this->resetPage();
    }

    /**
     * Contadores de hoy en una sola consulta agrupada.
     *
     * @return array{telegram_internal: int, whatsapp_internal: int, whatsapp_external: int, mail_internal: int, mail_external: int, failed: int, blocked: int, total: int}
     */
    #[Computed]
    public function todayStats(): array
    {
        $stats = ['telegram_internal' => 0, 'whatsapp_internal' => 0, 'whatsapp_external' => 0, 'mail_internal' => 0, 'mail_external' => 0, 'failed' => 0, 'blocked' => 0, 'total' => 0];

        $rows = OutboundMessage::query()
            ->where('created_at', '>=', now()->startOfDay())
            ->selectRaw('channel, audience, status, count(*) as total')
            ->groupBy('channel', 'audience', 'status')
            ->toBase()
            ->get();

        foreach ($rows as $row) {
            $count = (int) $row->total;
            $stats['total'] += $count;

            if ($row->status === OutboundMessageStatus::Failed->value) {
                $stats['failed'] += $count;
            }

            if ($row->status === OutboundMessageStatus::Blocked->value) {
                $stats['blocked'] += $count;

                continue;
            }

            $key = $row->channel.'_'.$row->audience;

            if (array_key_exists($key, $stats)) {
                $stats[$key] += $count;
            }
        }

        return $stats;
    }

    #[Computed]
    public function catalogName(): ?string
    {
        return $this->catalogKey !== null
            ? NotificationCatalogEntry::query()->where('key', $this->catalogKey)->value('name')
            : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->baseQuery())
            ->defaultSort('id', 'desc')
            ->searchable()
            ->searchPlaceholder('Nombre, teléfono, correo o asunto…')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Sin mensajes en este rango')
            ->emptyStateDescription('Cambie las fechas o los filtros. Los mensajes se registran desde que se activó el Centro de notificaciones.')
            ->emptyStateIcon('heroicon-o-inbox')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Hora')
                    ->dateTime('d/m H:i')
                    ->description(fn (OutboundMessage $record): string => (string) $record->created_at?->diffForHumans(short: true))
                    ->sortable(),
                TextColumn::make('channel')
                    ->label('Canal')
                    ->badge()
                    ->formatStateUsing(fn (OutboundChannel $state): string => $state->label())
                    ->icon(fn (OutboundChannel $state): string => $state->icon())
                    ->color(fn (OutboundChannel $state): string => $state->color()),
                TextColumn::make('audience')
                    ->label('Para')
                    ->badge()
                    ->formatStateUsing(fn (NotificationAudience $state): string => $state->label())
                    ->color(fn (NotificationAudience $state): string => $state->color()),
                TextColumn::make('recipient_name')
                    ->label('Destinatario')
                    ->searchable(['recipient_name', 'recipient', 'recipient_key', 'subject'])
                    ->getStateUsing(fn (OutboundMessage $record): string => filled($record->recipient_name) ? (string) $record->recipient_name : $record->maskedRecipient())
                    ->description(fn (OutboundMessage $record): ?string => filled($record->recipient_name)
                        ? $record->maskedRecipient().($record->recipients_count > 1 ? ' +'.($record->recipients_count - 1) : '')
                        : ($record->recipients_count > 1 ? '+'.($record->recipients_count - 1).' destinatarios' : null))
                    ->wrap(),
                TextColumn::make('catalogEntry.name')
                    ->label('Notificación')
                    ->placeholder('Sin tipo')
                    ->description(fn (OutboundMessage $record): ?string => filled($record->subject) ? (string) str($record->subject)->limit(70) : null)
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (OutboundMessageStatus $state): string => $state->label())
                    ->color(fn (OutboundMessageStatus $state): string => $state->color())
                    ->icon(fn (OutboundMessageStatus $state): string => $state->icon())
                    ->tooltip(fn (OutboundMessage $record): ?string => $record->error),
            ])
            ->filters([
                Filter::make('period')
                    ->label('Fechas')
                    ->schema([
                        DatePicker::make('from')->label('Desde')->native(false)->displayFormat('d/m/Y')->default(now()->subDays(6)->toDateString())->maxDate(now()),
                        DatePicker::make('until')->label('Hasta')->native(false)->displayFormat('d/m/Y')->maxDate(now()),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $from): Builder => $q->where('created_at', '>=', now()->parse($from)->startOfDay()))
                        ->when($data['until'] ?? null, fn (Builder $q, string $until): Builder => $q->where('created_at', '<=', now()->parse($until)->endOfDay())))
                    ->indicateUsing(function (array $data): ?string {
                        $from = filled($data['from'] ?? null) ? now()->parse($data['from'])->format('d/m') : null;
                        $until = filled($data['until'] ?? null) ? now()->parse($data['until'])->format('d/m') : 'hoy';

                        return $from !== null ? 'Del '.$from.' al '.$until : null;
                    }),
                SelectFilter::make('channel')
                    ->label('Canal')
                    ->options(collect(OutboundChannel::cases())->mapWithKeys(fn (OutboundChannel $channel): array => [$channel->value => $channel->label()])->all()),
                SelectFilter::make('audience')
                    ->label('Para')
                    ->options([NotificationAudience::Internal->value => 'Interna', NotificationAudience::External->value => 'Externa']),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(collect(OutboundMessageStatus::cases())->mapWithKeys(fn (OutboundMessageStatus $status): array => [$status->value => $status->label()])->all()),
                SelectFilter::make('catalog_key')
                    ->label('Notificación')
                    ->searchable()
                    ->options(fn (): array => NotificationCatalogEntry::query()->where('is_bulk', false)->orderBy('name')->pluck('name', 'key')->all()),
            ])
            ->filtersFormColumns(2)
            ->recordAction('detail')
            ->recordActions([
                Action::make('detail')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->iconButton()
                    ->slideOver()
                    ->modalWidth(Width::Large)
                    ->modalHeading(fn (OutboundMessage $record): string => $record->catalogEntry?->name ?? 'Mensaje')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn (OutboundMessage $record): View => view('livewire.notification-center.outbound-message-detail', [
                        'message' => $record->loadMissing('catalogEntry'),
                        'canReadBody' => $record->audience === NotificationAudience::Internal || NotificationCenterAccess::canReadExternalContent(),
                    ])),
            ]);
    }

    public function render(): View
    {
        return view('livewire.notification-center.outbound-messages-table');
    }

    /**
     * @return Builder<OutboundMessage>
     */
    private function baseQuery(): Builder
    {
        return OutboundMessage::query()
            ->with('catalogEntry:id,key,name')
            ->when($this->catalogKey, fn (Builder $query, string $key): Builder => $query->where('catalog_key', $key))
            ->when($this->quick, function (Builder $query, string $quick): Builder {
                $query->where('created_at', '>=', now()->startOfDay());

                return match (true) {
                    $quick === 'failed' => $query->where('status', OutboundMessageStatus::Failed->value),
                    $quick === 'blocked' => $query->where('status', OutboundMessageStatus::Blocked->value),
                    str_contains($quick, ':') => $query
                        ->where('channel', explode(':', $quick)[0])
                        ->where('audience', explode(':', $quick)[1])
                        ->where('status', '!=', OutboundMessageStatus::Blocked->value),
                    default => $query,
                };
            });
    }
}
