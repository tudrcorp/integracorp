<?php

namespace App\Filament\Operations\Resources\OperationMedicationInvoices\Schemas;

use App\Models\OperationMedicationInvoice;
use App\Support\Operations\AccountsReceivableManager;
use App\Support\Operations\AccountsReceivablePresenter;
use App\Support\Operations\MedicationInvoiceLifecycle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class OperationMedicationInvoiceInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('operationMedicationInvoiceTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes(['class' => self::TABS_CONTAINER])
                    ->tabs([
                        Tab::make('Factura')
                            ->icon(Heroicon::DocumentText)
                            ->schema([
                                Section::make('Datos de la factura')
                                    ->description('Factura interna, no fiscal. Los montos quedaron congelados al emitirla.')
                                    ->icon(Heroicon::ReceiptPercent)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Identificación')
                                            ->schema([
                                                TextEntry::make('invoice_number')->label('N.º factura')->badge()->color('primary')->copyable(),
                                                TextEntry::make('invoice_type')->label('Tipo')->badge()->formatStateUsing(fn (?string $state): string => OperationMedicationInvoice::typeLabel($state)),
                                                TextEntry::make('status')
                                                    ->label('Estado')
                                                    ->badge()
                                                    ->formatStateUsing(fn (?string $state): string => MedicationInvoiceLifecycle::statusLabel($state))
                                                    ->color(fn (?string $state): string => MedicationInvoiceLifecycle::statusColor($state)),
                                                TextEntry::make('issued_at')->label('Emisión')->dateTime('d/m/Y H:i'),
                                                TextEntry::make('issued_by_name')->label('Emitida por')->placeholder('—'),
                                            ])
                                            ->columns(5),
                                        Fieldset::make('Paciente y caso')
                                            ->schema([
                                                TextEntry::make('patient_name')->label('Paciente')->weight(FontWeight::SemiBold),
                                                TextEntry::make('patient_identification')->label('Cédula')->placeholder('—'),
                                                TextEntry::make('case_code')->label('Caso')->badge()->color('info')->placeholder('—'),
                                                TextEntry::make('operation_coordination_service_id')->label('Servicio médico')->prefix('#')->placeholder('—'),
                                            ])
                                            ->columns(4),
                                        Fieldset::make('Montos')
                                            ->schema([
                                                TextEntry::make('total_usd')
                                                    ->label('Total')
                                                    ->money('USD')
                                                    ->size(TextSize::Large)
                                                    ->weight(FontWeight::Bold)
                                                    ->color('success'),
                                                TextEntry::make('total_ves')
                                                    ->label('Equivalente')
                                                    ->formatStateUsing(fn (mixed $state): string => 'Bs. '.number_format((float) $state, 2, '.', ',')),
                                                TextEntry::make('bcv_rate')
                                                    ->label('Tasa BCV de emisión')
                                                    ->formatStateUsing(fn (mixed $state): string => number_format((float) $state, 4, '.', ',')),
                                                TextEntry::make('receivable')
                                                    ->label('Cuenta por cobrar')
                                                    ->state(fn (OperationMedicationInvoice $record): string => $record->accountsReceivable !== null
                                                        ? AccountsReceivableManager::formatReceivableNumber((int) $record->accountsReceivable->id).' · '.AccountsReceivablePresenter::statusLabel($record->accountsReceivable->status)
                                                        : '—'),
                                            ])
                                            ->columns(4),
                                    ])
                                    ->columnSpanFull(),
                                Section::make('Detalle')
                                    ->icon(Heroicon::ArchiveBox)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        RepeatableEntry::make('items')
                                            ->hiddenLabel()
                                            ->schema([
                                                TextEntry::make('prescribed_name')
                                                    ->label('Recetado')
                                                    ->helperText(fn ($record): ?string => $record->prescribed_indications)
                                                    ->columnSpan(3),
                                                TextEntry::make('product_name')
                                                    ->label('Concepto facturado')
                                                    ->weight(FontWeight::SemiBold)
                                                    ->helperText(fn ($record): ?string => filled($record->product_code) ? 'Cód. '.$record->product_code : null)
                                                    ->columnSpan(3),
                                                TextEntry::make('ubication_name')->label('Almacén')->placeholder('—')->columnSpan(2),
                                                TextEntry::make('quantity')->label('Cant.')->numeric()->columnSpan(1),
                                                TextEntry::make('unit_price_usd')
                                                    ->label('Precio unit.')
                                                    ->money('USD')
                                                    ->helperText(fn ($record): string => 'Costo US$ '.number_format((float) $record->unit_cost_usd, 2, '.', ',').' + '.number_format((float) $record->profit_percentage, 2, ',', '.').' %')
                                                    ->columnSpan(2),
                                                TextEntry::make('line_total_usd')->label('Subtotal')->money('USD')->weight(FontWeight::SemiBold)->columnSpan(1),
                                            ])
                                            ->columns(12)
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Pago')
                            ->icon(Heroicon::Banknotes)
                            ->schema([
                                Section::make('Pago')
                                    ->icon(Heroicon::Banknotes)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        TextEntry::make('paid_at')->label('Fecha del pago')->date('d/m/Y')->placeholder('Sin pago registrado'),
                                        TextEntry::make('payment_method')
                                            ->label('Forma de pago')
                                            ->formatStateUsing(fn (?string $state): string => MedicationInvoiceLifecycle::paymentMethodLabel($state))
                                            ->placeholder('—'),
                                        TextEntry::make('payment_reference')->label('Referencia')->placeholder('—')->copyable(),
                                        TextEntry::make('payment_registered_by_name')
                                            ->label('Registrado por')
                                            ->helperText(fn (OperationMedicationInvoice $record): ?string => $record->payment_registered_at?->format('d/m/Y H:i'))
                                            ->placeholder('—'),
                                        TextEntry::make('payment_notes')->label('Observaciones')->placeholder('—')->columnSpanFull(),
                                    ])
                                    ->columns(4)
                                    ->columnSpanFull(),
                                Section::make('Anulación')
                                    ->icon(Heroicon::NoSymbol)
                                    ->visible(fn (OperationMedicationInvoice $record): bool => $record->status === OperationMedicationInvoice::STATUS_ANNULLED)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        TextEntry::make('annulled_at')->label('Fecha')->dateTime('d/m/Y H:i'),
                                        TextEntry::make('annulled_by_name')->label('Anulada por'),
                                        TextEntry::make('annulment_reason')->label('Motivo')->columnSpanFull(),
                                    ])
                                    ->columns(2)
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Bitácora')
                            ->icon(Heroicon::Clock)
                            ->badge(fn (OperationMedicationInvoice $record): int => $record->events->count())
                            ->schema([
                                RepeatableEntry::make('events')
                                    ->hiddenLabel()
                                    ->schema([
                                        TextEntry::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i')->columnSpan(1),
                                        TextEntry::make('event')
                                            ->label('Evento')
                                            ->badge()
                                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                                'EMITIDA' => 'Emitida',
                                                'PAGO_REGISTRADO' => 'Pago registrado',
                                                'ANULADA' => 'Anulada',
                                                'PDF_ENVIADO' => 'PDF enviado',
                                                default => $state,
                                            })
                                            ->color(fn (string $state): string => match ($state) {
                                                'EMITIDA' => 'info',
                                                'PAGO_REGISTRADO' => 'success',
                                                'ANULADA' => 'danger',
                                                'PDF_ENVIADO' => 'primary',
                                                default => 'gray',
                                            })
                                            ->columnSpan(1),
                                        TextEntry::make('user_name')->label('Usuario')->placeholder('—')->columnSpan(1),
                                        TextEntry::make('description')->label('Detalle')->columnSpanFull(),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
