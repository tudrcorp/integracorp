<?php

declare(strict_types=1);

namespace App\Livewire\Affiliations;

use App\Models\AffiliateMovement;
use App\Models\User;
use App\Support\Affiliations\Movements\AffiliateMovements;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;
use Throwable;

/**
 * Historial de inclusiones y exclusiones de una afiliación, con el detalle de lo que cambió en
 * cada módulo y el reverso. Para auditoría de sistemas.
 */
class AffiliateMovementsHistory extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public string $affiliationType = AffiliateMovement::TYPE_CORPORATE;

    #[Locked]
    public int $affiliationId = 0;

    #[Locked]
    public bool $canRevert = false;

    public function table(Table $table): Table
    {
        return $table
            ->query(AffiliateMovement::query()->where('affiliation_type', $this->affiliationType)->where('affiliation_id', $this->affiliationId))
            ->heading('Historial de inclusiones y exclusiones')
            ->description('Cada alta y baja con lo que cambió en la afiliación, los avisos de cobro y operaciones. Se puede revertir el último movimiento de cada persona.')
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Registrado')->dateTime('d/m/Y H:i')->description(fn (AffiliateMovement $record): string => (string) $record->created_by_name),
                TextColumn::make('kind')->label('Movimiento')->badge()
                    ->formatStateUsing(fn (AffiliateMovement $record): string => $record->kindLabel())
                    ->color(fn (AffiliateMovement $record): string => $record->isAddition() ? 'success' : 'danger'),
                TextColumn::make('affiliate_name')->label('Persona')->description(fn (AffiliateMovement $record): string => 'C.I. '.$record->affiliate_document)->searchable(),
                TextColumn::make('effective_date')->label('Desde')->date('d/m/Y'),
                TextColumn::make('prorated_amount')->label('Prorrateo')
                    ->state(fn (AffiliateMovement $record): string => (float) $record->prorated_amount > 0 ? ($record->isAddition() ? '+' : '−').'US$ '.number_format((float) $record->prorated_amount, 2, ',', '.') : '—')
                    ->description(fn (AffiliateMovement $record): ?string => $record->period_days > 0 ? $record->prorated_days.' de '.$record->period_days.' días' : null),
                TextColumn::make('billing')->label('Cobranza')
                    ->state(fn (AffiliateMovement $record): string => self::billingSummary($record))
                    ->wrap(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->color(fn (string $state): string => $state === AffiliateMovement::STATUS_APPLIED ? 'success' : 'gray')
                    ->description(fn (AffiliateMovement $record): ?string => $record->reverted_at ? 'Por '.$record->reverted_by_name.' el '.$record->reverted_at->format('d/m/Y H:i') : null),
            ])
            ->recordActions([
                Action::make('detail')
                    ->label('Detalle')
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->color('gray')
                    ->slideOver()
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalHeading(fn (AffiliateMovement $record): string => $record->kindLabel().' de '.$record->affiliate_name)
                    ->modalContent(fn (AffiliateMovement $record): View => view('livewire.affiliations.affiliate-movement-detail', ['movement' => $record->load('collectionLines.collection')]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                Action::make('revert')
                    ->label('Revertir')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (AffiliateMovement $record): bool => $this->canRevert && $record->isApplied())
                    ->disabled(fn (AffiliateMovement $record): bool => AffiliateMovements::revertBlocker($record) !== null)
                    ->tooltip(fn (AffiliateMovement $record): ?string => AffiliateMovements::revertBlocker($record))
                    ->modalHeading(fn (AffiliateMovement $record): string => 'Revertir '.mb_strtolower($record->kindLabel()).' de '.$record->affiliate_name)
                    ->modalDescription(fn (AffiliateMovement $record): string => $record->isAddition()
                        ? 'La persona sale de la afiliación (eliminación reversible), los totales se recalculan y los avisos pendientes vuelven a su monto. Lo que ya se pagó se acredita en el próximo aviso pendiente.'
                        : 'La persona vuelve a quedar activa, los totales se recalculan y los avisos pendientes vuelven a su monto. Lo que ya se pagó con el descuento se cobra en el próximo aviso pendiente.')
                    ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
                    ->modalIconColor('danger')
                    ->schema([
                        Textarea::make('reason')->label('¿Por qué se revierte?')->rows(2)->required()->minLength(AffiliateMovements::REASON_MIN_LENGTH)->maxLength(1000),
                    ])
                    ->modalSubmitActionLabel('Revertir')
                    ->action(function (array $data, AffiliateMovement $record, Action $action): void {
                        abort_unless($this->canRevert, 403);
                        $user = Auth::user();
                        abort_unless($user instanceof User, 403);
                        abort_unless($record->affiliation_type === $this->affiliationType && (int) $record->affiliation_id === $this->affiliationId, 404);

                        try {
                            AffiliateMovements::revert($record, (string) $data['reason'], $user);
                        } catch (RuntimeException $exception) {
                            Notification::make()->title('No se revirtió')->body($exception->getMessage())->danger()->persistent()->send();
                            $action->halt();

                            return;
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->title('No se revirtió')->body('Error inesperado. No se cambió nada.')->danger()->send();
                            $action->halt();

                            return;
                        }

                        Notification::make()->title('Movimiento revertido')->body('La afiliación, los avisos y operaciones volvieron a su estado anterior.')->success()->send();
                        $this->dispatch('$refresh');
                    }),
            ])
            ->emptyStateHeading('Sin movimientos registrados')
            ->emptyStateDescription('Las inclusiones y exclusiones que se hagan desde ahora aparecerán aquí.')
            ->paginated([10, 25]);
    }

    public static function billingSummary(AffiliateMovement $movement): string
    {
        $parts = [];
        foreach ((array) ($movement->effects['collections'] ?? []) as $change) {
            $parts[] = $change['invoice'].': '.AffiliateMovements::decimal((int) $change['before_cents']).' → '.AffiliateMovements::decimal((int) $change['after_cents']);
        }

        if (isset($movement->effects['charge'])) {
            $parts[] = 'Emitido: '.$movement->effects['charge'];
        }

        if ((float) $movement->pending_credit > 0) {
            $parts[] = 'Crédito pendiente US$ '.$movement->pending_credit;
        }

        return $parts === [] ? ((string) $movement->billing_note ?: 'Sin avisos que ajustar') : implode(' · ', $parts);
    }

    public function render(): View
    {
        return view('livewire.affiliations.affiliate-movements-history');
    }
}
