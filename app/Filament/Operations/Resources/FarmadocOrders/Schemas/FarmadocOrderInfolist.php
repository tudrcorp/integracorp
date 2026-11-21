<?php

namespace App\Filament\Operations\Resources\FarmadocOrders\Schemas;

use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceItems;
use App\Models\FarmadocOrder;
use App\Support\Farmadoc\FarmadocOrderItems;
use App\Support\Farmadoc\FarmadocReceiptLabels;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class FarmadocOrderInfolist
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pedido')
                    ->icon(Heroicon::Truck)
                    ->extraAttributes(['class' => self::SECTION_CARD])
                    ->schema([
                        TextEntry::make('order_number')->label('Número de pedido')->weight(FontWeight::SemiBold)->copyable(),
                        TextEntry::make('service_order_number')
                            ->label('Orden de servicio')
                            ->placeholder('Sin orden de servicio')
                            ->weight(FontWeight::SemiBold)
                            ->copyable(),
                        TextEntry::make('status')
                            ->label('Estatus en Farmadoc')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => FarmadocOrder::statusLabel($state))
                            ->color(fn (?string $state): string => FarmadocOrder::statusColor($state)),
                        TextEntry::make('delivery_note_number')
                            ->label('Nota de entrega')
                            ->weight(FontWeight::SemiBold)
                            ->copyable()
                            ->helperText(fn (FarmadocOrder $record): ?string => $record->accepted_at !== null
                                ? 'Aceptado el '.$record->accepted_at->format('d/m/Y H:i')
                                : null)
                            ->visible(fn (FarmadocOrder $record): bool => filled($record->delivery_note_number)),
                        TextEntry::make('created_by_name')
                            ->label('Enviado por')
                            ->helperText(fn (FarmadocOrder $record): ?string => $record->created_at?->format('d/m/Y H:i'))
                            ->placeholder('—'),
                        TextEntry::make('status_checked_at')
                            ->label('Última consulta a Farmadoc')
                            ->dateTime('d/m/Y H:i')
                            ->helperText(fn (FarmadocOrder $record): ?string => $record->farmadoc_updated_at !== null
                                ? 'Farmadoc lo actualizó el '.$record->farmadoc_updated_at->format('d/m/Y H:i')
                                : null)
                            ->placeholder('—'),
                        TextEntry::make('status_check_error')
                            ->label('Última consulta fallida')
                            ->color('danger')
                            ->visible(fn (FarmadocOrder $record): bool => filled($record->status_check_error))
                            ->columnSpanFull(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
                Section::make('Origen')
                    ->icon(Heroicon::UserGroup)
                    ->extraAttributes(['class' => self::SECTION_CARD])
                    ->schema([
                        TextEntry::make('coordination_reference')
                            ->label('Coordinación')
                            ->placeholder(FarmadocOrder::DEFAULT_ORIGIN_LABEL)
                            ->url(fn (FarmadocOrder $record): ?string => $record->operation_coordination_service_id !== null
                                ? ManageCoordinationServiceItems::getUrl(['record' => $record->operation_coordination_service_id])
                                : null)
                            ->color('primary'),
                        TextEntry::make('patient_name')->label('Paciente')->placeholder('—'),
                        TextEntry::make('ubication.name')
                            ->label('Pedido para')
                            ->placeholder('Sin ubicación')
                            ->badge()
                            ->color('info'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('Entrada a inventario')
                    ->icon(Heroicon::ArchiveBoxArrowDown)
                    ->description('El pedido ya está en el inventario. Farmadoc ve este estatus y la nota de recibido en el API de aliados.')
                    ->extraAttributes(['class' => self::SECTION_CARD])
                    ->visible(fn (FarmadocOrder $record): bool => $record->isReceived())
                    ->schema([
                        TextEntry::make('receipt_number')->label('Nota de recibido')->weight(FontWeight::SemiBold)->copyable(),
                        TextEntry::make('receiptUbication.name')->label('Recibido en')->placeholder('—'),
                        TextEntry::make('received_by_name')
                            ->label('Recibido por')
                            ->helperText(fn (FarmadocOrder $record): ?string => $record->received_at?->format('d/m/Y H:i'))
                            ->placeholder('—'),
                        TextEntry::make('receipt_notes')
                            ->label('Observaciones')
                            ->placeholder('Sin observaciones'),
                        RepeatableEntry::make('receipt_items')
                            ->hiddenLabel()
                            ->state(fn (FarmadocOrder $record): array => FarmadocReceiptLabels::lines($record))
                            ->schema([
                                TextEntry::make('name')->label('Medicamento de Farmadoc')->weight(FontWeight::SemiBold)->columnSpan(2),
                                TextEntry::make('received_label')->label('Recibido'),
                                TextEntry::make('action')
                                    ->label('Qué se hizo')
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => FarmadocReceiptLabels::action($state))
                                    ->color(fn (?string $state): string => FarmadocReceiptLabels::actionColor($state)),
                                TextEntry::make('product_name')
                                    ->label('Producto en inventario')
                                    ->placeholder('—')
                                    ->columnSpan(2),
                            ])
                            ->columns(6)
                            ->columnSpanFull(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
                Section::make('Medicamentos')
                    ->icon(Heroicon::Beaker)
                    ->description(fn (FarmadocOrder $record): string => $record->items_count.' '.($record->items_count === 1 ? 'medicamento' : 'medicamentos')
                        .' · '.$record->units_count.' '.($record->units_count === 1 ? 'unidad' : 'unidades'))
                    ->extraAttributes(['class' => self::SECTION_CARD])
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->state(fn (FarmadocOrder $record): array => FarmadocOrderItems::forDisplay($record))
                            ->schema([
                                TextEntry::make('name')->label('Medicamento')->weight(FontWeight::SemiBold)->columnSpan(2),
                                TextEntry::make('barcode')
                                    ->label('Código de barras')
                                    ->placeholder('—')
                                    ->fontFamily(FontFamily::Mono)
                                    ->copyable()
                                    ->copyMessage('Código de barras copiado')
                                    ->copyMessageDuration(1500),
                                TextEntry::make('sku')->label('SKU')->placeholder('—'),
                                TextEntry::make('quantity')->label('Cantidad'),
                            ])
                            ->columns(5)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
