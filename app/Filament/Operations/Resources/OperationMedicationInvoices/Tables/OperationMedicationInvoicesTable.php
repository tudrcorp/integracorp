<?php

namespace App\Filament\Operations\Resources\OperationMedicationInvoices\Tables;

use App\Filament\Operations\Resources\OperationMedicationInvoices\Actions\ExportMedicationInvoicesBulkAction;
use App\Filament\Operations\Resources\OperationMedicationInvoices\Actions\MedicationInvoiceActions;
use App\Models\OperationMedicationInvoice;
use App\Support\Operations\MedicationInvoiceLifecycle;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OperationMedicationInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Facturas a pacientes')
            ->description('Lo no cubierto que se factura al paciente: medicamentos del inventario, laboratorios, estudios y consultas con especialista. Factura interna, no fiscal.')
            ->defaultSort('issued_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('items'))
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('N.º factura')
                    ->badge()
                    ->color('primary')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('invoice_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => OperationMedicationInvoice::typeLabel($state))
                    ->color(fn (?string $state): string => match ($state) {
                        OperationMedicationInvoice::TYPE_LABORATORIES => 'info',
                        OperationMedicationInvoice::TYPE_IMAGING => 'primary',
                        OperationMedicationInvoice::TYPE_SPECIALIST => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('issued_at')
                    ->label('Emisión')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('patient_name')
                    ->label('Paciente')
                    ->description(fn (OperationMedicationInvoice $record): ?string => $record->patient_identification)
                    ->searchable(['patient_name', 'patient_identification'])
                    ->wrap(),
                TextColumn::make('case_code')
                    ->label('Caso')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('items_count')
                    ->label('Líneas')
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('total_usd')
                    ->label('Total (US$)')
                    ->money('USD')
                    ->alignEnd()
                    ->weight(FontWeight::SemiBold)
                    ->sortable(),
                TextColumn::make('total_ves')
                    ->label('Total (Bs.)')
                    ->formatStateUsing(fn (mixed $state): string => 'Bs. '.number_format((float) $state, 2, '.', ','))
                    ->description(fn (OperationMedicationInvoice $record): string => 'Tasa '.number_format((float) $record->bcv_rate, 2, '.', ','))
                    ->alignEnd()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => MedicationInvoiceLifecycle::statusLabel($state))
                    ->color(fn (?string $state): string => MedicationInvoiceLifecycle::statusColor($state))
                    ->sortable(),
                TextColumn::make('issued_by_name')
                    ->label('Emitida por')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('paid_at')
                    ->label('Cobrada el')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('invoice_type')
                    ->label('Tipo')
                    ->options(OperationMedicationInvoice::typeOptions()),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        OperationMedicationInvoice::STATUS_PENDING_PAYMENT => 'Pendiente por cobrar',
                        OperationMedicationInvoice::STATUS_PAID => 'Cobrada',
                        OperationMedicationInvoice::STATUS_ANNULLED => 'Anulada',
                    ]),
                Filter::make('issued_at')
                    ->label('Fecha de emisión')
                    ->schema([
                        DatePicker::make('from')->label('Desde')->native(false)->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label('Hasta')->native(false)->displayFormat('d/m/Y'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('issued_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('issued_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
                ActionGroup::make([
                    MedicationInvoiceActions::previewPdf(),
                    MedicationInvoiceActions::emailPdf(),
                    MedicationInvoiceActions::whatsAppPdf(),
                    MedicationInvoiceActions::registerPayment(),
                    MedicationInvoiceActions::annul(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportMedicationInvoicesBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Aún no hay facturas a pacientes')
            ->emptyStateDescription('Se generan en «Gestionar servicio» al facturar al paciente un ítem no cubierto.');
    }
}
