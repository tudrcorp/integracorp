<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationAccountsPayables\Schemas;

use App\Enums\StatusCuentaPorPagar;
use App\Models\OperationAccountsPayable;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OperationAccountsPayableInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Factura')
                    ->icon('heroicon-o-document-text')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('invoice_number')->label('Número de factura')->badge()->color('primary'),
                        TextEntry::make('invoice_control_number')->label('Número de control')->placeholder('—')->badge()->color('warning'),
                        TextEntry::make('invoice_date')->label('Fecha de la factura')->date('d/m/Y'),
                        TextEntry::make('invoice_registration_date')->label('Fecha de registro')->date('d/m/Y'),
                        TextEntry::make('invoice_amount')
                            ->label('Monto de la factura')
                            ->state(fn (OperationAccountsPayable $record): string => self::money($record->invoice_amount, $record->invoice_currency))
                            ->weight('bold'),
                        TextEntry::make('invoice_currency')->label('Moneda'),
                    ]),

                Section::make('Proveedor')
                    ->icon('heroicon-o-building-storefront')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('supplier_name')->label('Nombre del proveedor'),
                        TextEntry::make('supplier_rif')->label('RIF')->copyable(),
                        TextEntry::make('businessUnit.definition')->label('Unidad de negocio específica')->placeholder('—')->badge()->color('gray'),
                        TextEntry::make('operationServiceOrder.order_number')
                            ->label('Orden de servicio de origen')
                            ->placeholder('Carga manual')
                            ->badge()
                            ->color(fn (OperationAccountsPayable $record): string => $record->operation_service_order_id !== null ? 'info' : 'gray'),
                    ]),

                Section::make('Pago')
                    ->icon('heroicon-o-banknotes')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('payment_status')
                            ->label('Estatus de pago')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => StatusCuentaPorPagar::labelFromMixed($state))
                            ->color(fn (mixed $state): string => StatusCuentaPorPagar::filamentColorFromMixed($state))
                            ->icon(fn (mixed $state): ?string => StatusCuentaPorPagar::fromStored($state)?->filamentIcon()),
                        TextEntry::make('payment_reference')->label('Referencia de pago')->placeholder('—')->copyable(),
                        TextEntry::make('payment_date')->label('Fecha del pago')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('national_bank')->label('Banco nacional')->placeholder('—'),
                        TextEntry::make('international_bank')->label('Banco internacional')->placeholder('—'),
                        TextEntry::make('payment_amount_usd')
                            ->label('Monto del pago en US$')
                            ->state(fn (OperationAccountsPayable $record): string => $record->payment_amount_usd !== null
                                ? self::money($record->payment_amount_usd, 'USD')
                                : '—'),
                        TextEntry::make('payment_amount_ves')
                            ->label('Monto del pago en Bs.')
                            ->state(fn (OperationAccountsPayable $record): string => $record->payment_amount_ves !== null
                                ? self::money($record->payment_amount_ves, 'VES')
                                : '—'),
                    ]),

                Section::make('Registro')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->columns(2)
                    ->collapsed()
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('observations')->label('Observaciones')->placeholder('Sin observaciones')->columnSpanFull(),
                        TextEntry::make('created_by')->label('Registrado por'),
                        TextEntry::make('created_at')->label('Fecha de registro')->dateTime('d/m/Y H:i'),
                        TextEntry::make('updated_by')->label('Última actualización por')->placeholder('—'),
                        TextEntry::make('updated_at')->label('Última actualización')->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }

    private static function money(mixed $amount, ?string $currency): string
    {
        $symbol = $currency === 'VES' ? 'Bs. ' : 'US$ ';

        return $symbol.number_format((float) $amount, 2, ',', '.');
    }
}
