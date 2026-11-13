<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\FiscalBooks\Tables;

use App\Enums\FiscalBook;
use App\Filament\Administration\Resources\FiscalBooks\Pages\ListFiscalBookEntries;
use App\Filament\Administration\Resources\FiscalBooks\Schemas\FiscalBookEntryForm;
use App\Models\FiscalBookEntry;
use App\Support\Administration\FiscalBooks\FiscalAmount;
use App\Support\Administration\FiscalBooks\FiscalBookEntryWriter;
use App\Support\Administration\FiscalBooks\FiscalBookLedger;
use App\Support\Administration\FiscalBooks\FiscalBookPeriods;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Tabla del libro de un mes. El mes lo elige la página (no es un filtro), así
 * la consulta siempre va acotada por el índice (libro, mes).
 */
final class FiscalBookEntriesTable
{
    public static function configure(Table $table, FiscalBook $book): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query, ListFiscalBookEntries $livewire): Builder => FiscalBookLedger::ordered(
                $query->where('period', $livewire->periodDate()->toDateString())
            ))
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->striped()
            ->emptyStateIcon(Heroicon::OutlinedBookOpen)
            ->emptyStateHeading(fn (ListFiscalBookEntries $livewire): string => 'Sin documentos en '.mb_strtolower(FiscalBookPeriods::label($livewire->periodDate())))
            ->emptyStateDescription($book === FiscalBook::Sales
                ? 'Use «Traer facturas de Ventas» para cargar las facturas emitidas en el sistema, o «Registrar documento» para una factura emitida fuera de él.'
                : 'Use «Importar de cuentas por pagar» para traer las facturas de proveedores, o «Registrar documento» para cargar una factura de compra.')
            ->columns([
                TextColumn::make('operation_number')
                    ->label('N°')
                    ->state(fn (FiscalBookEntry $record, ListFiscalBookEntries $livewire): ?int => FiscalBookLedger::operationNumbers($book, $livewire->periodDate())[(int) $record->getKey()] ?? null)
                    ->alignCenter(),
                TextColumn::make('document_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('document_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => FiscalBookEntry::documentTypeOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        FiscalBookEntry::TYPE_CREDIT_NOTE => 'warning',
                        FiscalBookEntry::TYPE_DEBIT_NOTE => 'info',
                        default => 'gray',
                    })
                    ->toggleable(),
                TextColumn::make('document_number')
                    ->label('N° documento')
                    ->searchable()
                    ->weight('semibold')
                    ->description(fn (FiscalBookEntry $record): ?string => $record->affected_document_number ? 'Afecta: '.$record->affected_document_number : null),
                TextColumn::make('control_number')
                    ->label('N° control')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('counterparty_name')
                    ->label($book->counterpartyLabel())
                    ->searchable()
                    ->wrap()
                    ->formatStateUsing(fn (FiscalBookEntry $record, string $state): string => $record->isAnnulled() ? 'ANULADA' : $state)
                    ->color(fn (FiscalBookEntry $record): ?string => $record->isAnnulled() ? 'danger' : null)
                    ->description(fn (FiscalBookEntry $record): ?string => $record->isAnnulled() ? $record->annulment_reason : null),
                TextColumn::make('counterparty_rif')
                    ->label('RIF / C.I.')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('taxpayer_type')
                    ->label('Tipo')
                    ->tooltip(fn (string $state): string => $state === FiscalBookEntry::TAXPAYER ? 'Contribuyente' : 'No contribuyente')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
                self::money('total_amount', 'Total'),
                self::money('exempt_amount', 'Exento'),
                self::money('taxable_base', 'Base imponible'),
                self::money('tax_amount', 'IVA'),
                self::money('withholding_amount', 'IVA retenido')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('source')
                    ->label('Origen')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => FiscalBookEntry::sourceOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        FiscalBookEntry::SOURCE_SALE => 'success',
                        FiscalBookEntry::SOURCE_PAYABLE => 'info',
                        FiscalBookEntry::SOURCE_LEGACY => 'gray',
                        default => 'primary',
                    })
                    ->description(fn (FiscalBookEntry $record): ?string => $record->manually_adjusted && $record->source === FiscalBookEntry::SOURCE_SALE ? 'Ajustada a mano' : null)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('document_type')
                    ->label('Tipo de documento')
                    ->options(FiscalBookEntry::documentTypeOptions()),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        FiscalBookEntry::STATUS_ACTIVE => 'Vigentes',
                        FiscalBookEntry::STATUS_ANNULLED => 'Anuladas',
                    ]),
                SelectFilter::make('taxed')
                    ->label('IVA')
                    ->options(['gravadas' => 'Con IVA', 'exentas' => 'Solo exentas'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'gravadas' => $query->where('tax_amount', '<>', 0),
                        'exentas' => $query->where('tax_amount', 0),
                        default => $query,
                    }),
                SelectFilter::make('source')
                    ->label('Origen')
                    ->options(FiscalBookEntry::sourceOptions()),
            ])
            ->recordActions([
                ActionGroup::make([
                    self::editAction($book),
                    self::annulAction(),
                    self::deleteAction(),
                ])->visible(fn (ListFiscalBookEntries $livewire): bool => ! $livewire->isClosed()),
            ], position: RecordActionsPosition::BeforeColumns);
    }

    private static function money(string $name, string $label): TextColumn
    {
        return TextColumn::make($name)
            ->label($label)
            ->alignEnd()
            ->formatStateUsing(fn (mixed $state): string => FiscalAmount::isZero($state) ? '—' : FiscalAmount::format($state))
            ->color(fn (mixed $state): ?string => FiscalAmount::isNegative($state) ? 'danger' : null)
            ->fontFamily('mono');
    }

    private static function editAction(FiscalBook $book): Action
    {
        return Action::make('editEntry')
            ->label('Editar')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('primary')
            ->visible(fn (FiscalBookEntry $record): bool => ! $record->isAnnulled())
            ->modalHeading(fn (FiscalBookEntry $record): string => 'Editar documento N° '.$record->document_number)
            ->modalDescription(fn (FiscalBookEntry $record): ?string => $record->source === FiscalBookEntry::SOURCE_SALE
                ? 'Viene de Ventas. Al guardar queda marcada como ajustada a mano y la sincronización ya no la cambia.'
                : null)
            ->modalWidth('5xl')
            ->modalSubmitActionLabel('Guardar cambios')
            ->fillForm(fn (FiscalBookEntry $record): array => FiscalBookEntryWriter::currentInput($record))
            ->schema(FiscalBookEntryForm::components($book))
            ->action(function (FiscalBookEntry $record, array $data, ListFiscalBookEntries $livewire): void {
                try {
                    FiscalBookEntryWriter::update($record, [...$data, 'period' => $livewire->periodDate()->format('Y-m')], Auth::user());
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title('No se guardó')->body($exception->getMessage())->danger()->send();

                    throw new Halt;
                }

                Notification::make()->title('Documento actualizado')->success()->send();
            });
    }

    private static function annulAction(): Action
    {
        return Action::make('annulEntry')
            ->label('Anular')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (FiscalBookEntry $record): bool => ! $record->isAnnulled())
            ->modalHeading(fn (FiscalBookEntry $record): string => 'Anular documento N° '.$record->document_number)
            ->modalDescription('El documento queda en el libro con su número y fecha, marcado ANULADO y con todos sus montos en cero. No se puede deshacer desde aquí.')
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalSubmitActionLabel('Anular documento')
            ->schema([
                Textarea::make('reason')
                    ->label('Razón de la anulación')
                    ->required()
                    ->minLength(FiscalBookPeriods::MIN_REASON_LENGTH)
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->action(function (FiscalBookEntry $record, array $data): void {
                try {
                    FiscalBookEntryWriter::annul($record, (string) $data['reason'], Auth::user());
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title('No se anuló')->body($exception->getMessage())->danger()->send();

                    throw new Halt;
                }

                Notification::make()->title('Documento anulado')->success()->send();
            });
    }

    private static function deleteAction(): Action
    {
        return Action::make('deleteEntry')
            ->label('Quitar del libro')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (FiscalBookEntry $record): bool => FiscalBookEntryWriter::canDelete($record))
            ->requiresConfirmation()
            ->modalHeading(fn (FiscalBookEntry $record): string => 'Quitar el documento N° '.$record->document_number)
            ->modalDescription('Úselo solo si se cargó por error. Queda registrado quién lo quitó. Si el documento existe pero se anuló, use «Anular».')
            ->modalSubmitActionLabel('Quitar del libro')
            ->action(function (FiscalBookEntry $record): void {
                try {
                    FiscalBookEntryWriter::delete($record, Auth::user());
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title('No se quitó')->body($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('Documento quitado del libro')->success()->send();
            });
    }
}
