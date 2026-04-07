<?php

namespace App\Filament\Administration\Resources\AnnualCollections\Schemas;

use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AnnualCollectionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación y venta')
                    ->collapsed()
                    ->icon('heroicon-o-document-text')
                    ->description('Datos de la venta y cobranza asociada')
                    ->schema([
                        Fieldset::make('Datos principales')
                            ->schema([
                                TextEntry::make('sale_id')
                                    ->label('ID Venta')
                                    ->badge()
                                    ->color('primary')
                                    ->placeholder('-'),
                                TextEntry::make('collection_invoice_number')
                                    ->label('Nro. factura')
                                    ->badge()
                                    ->color('success')
                                    ->placeholder('-'),
                                TextEntry::make('quote_number')
                                    ->label('Nro. cotización')
                                    ->placeholder('-'),
                                TextEntry::make('include_date')
                                    ->label('Fecha inclusión')
                                    ->placeholder('-'),
                                TextEntry::make('code_agency')
                                    ->label('Cód. agencia')
                                    ->placeholder('-'),
                                TextEntry::make('owner_code')
                                    ->label('Cód. propietario')
                                    ->placeholder('-'),
                                TextEntry::make('agent.name')
                                    ->label('Agente')
                                    ->placeholder('-'),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Datos del afiliado')
                    ->collapsed()
                    ->icon('heroicon-o-user')
                    ->description('Información del afiliado')
                    ->schema([
                        Fieldset::make('Contacto y identificación')
                            ->schema([
                                TextEntry::make('affiliate_full_name')
                                    ->label('Afiliado')
                                    ->placeholder('-'),
                                TextEntry::make('affiliation_code')
                                    ->label('Cód. afiliación')
                                    ->placeholder('-'),
                                TextEntry::make('affiliate_ci_rif')
                                    ->label('C.I./R.I.F.')
                                    ->placeholder('-'),
                                TextEntry::make('affiliate_contact')
                                    ->label('Contacto')
                                    ->placeholder('-'),
                                TextEntry::make('affiliate_phone')
                                    ->label('Teléfono')
                                    ->placeholder('-'),
                                TextEntry::make('affiliate_email')
                                    ->label('Correo')
                                    ->placeholder('-'),
                                TextEntry::make('affiliate_status')
                                    ->label('Est. afiliación')
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        'ACTIVA' => 'success',
                                        'INACTIVA' => 'danger',
                                        default => 'gray',
                                    })
                                    ->placeholder('-'),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Plan y cobertura')
                    ->collapsed()
                    ->icon('heroicon-o-clipboard-document-list')
                    ->description('Plan, cobertura y tipo de afiliación')
                    ->schema([
                        Fieldset::make('Plan')
                            ->schema([
                                TextEntry::make('plan.description')
                                    ->label('Plan')
                                    ->placeholder('-'),
                                TextEntry::make('coverage.price')
                                    ->label('Cobertura (US$)')
                                    ->numeric(decimalPlaces: 2)
                                    ->suffix(' US$')
                                    ->placeholder('-'),
                                TextEntry::make('persons')
                                    ->label('Población')
                                    ->placeholder('-'),
                                TextEntry::make('type')
                                    ->label('Tipo')
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        'AFILIACION INDIVIDUAL' => 'primary',
                                        'AFILIACION CORPORATIVA' => 'success',
                                        default => 'gray',
                                    })
                                    ->placeholder('-'),
                                TextEntry::make('service')
                                    ->label('Servicio')
                                    ->placeholder('-'),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Cobranzas asociadas (collections)')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->description('Registros de la tabla collections vinculados por el ID de la Venta')
                    ->schema([
                        Fieldset::make('Listado de cobranzas')
                            ->schema([
                                RepeatableEntry::make('collections')
                                    ->label('')
                                    ->table([
                                        TableColumn::make('Nro. factura'),
                                        TableColumn::make('Cotización'),
                                        TableColumn::make('Próx. pago'),
                                        TableColumn::make('Monto total'),
                                        TableColumn::make('Estado'),
                                        TableColumn::make('Tipo'),
                                    ])
                                    ->schema([
                                        TextEntry::make('collection_invoice_number')
                                            ->label('Nro. factura')
                                            ->placeholder('-'),
                                        TextEntry::make('quote_number')
                                            ->label('Cotización')
                                            ->placeholder('-'),
                                        TextEntry::make('next_payment_date')
                                            ->label('Próx. pago')
                                            ->placeholder('-'),
                                        TextEntry::make('total_amount')
                                            ->label('Monto total')
                                            ->numeric(decimalPlaces: 2)
                                            ->suffix(' US$')
                                            ->placeholder('-'),
                                        TextEntry::make('status')
                                            ->label('Estado')
                                            ->badge()
                                            ->color(fn (?string $state): string => match ($state) {
                                                'PAGADO' => 'success',
                                                'POR PAGAR' => 'warning',
                                                'CANCELADO' => 'danger',
                                                default => 'gray',
                                            })
                                            ->placeholder('-'),
                                        TextEntry::make('type')
                                            ->label('Tipo')
                                            ->placeholder('-'),
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
