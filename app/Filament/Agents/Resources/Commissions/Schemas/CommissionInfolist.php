<?php

namespace App\Filament\Agents\Resources\Commissions\Schemas;

use App\Models\Sale;
use App\Models\Commission;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;

class CommissionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
            Section::make()
                ->description(fn(Commission $record) => 'Comision pagada el: ' . $record->created_at->format('d/m/Y h:m A'))
                ->columnSpanFull()
                ->icon(Heroicon::Bars3BottomLeft)
                ->schema([
                    Fieldset::make()
                        ->schema([
                            TextEntry::make('range')
                                ->label('Periodo calculado:')
                                ->default(function (Commission $record) {
                                    return $record->date_ini . ' - ' . $record->date_end;
                                })
                                ->badge()
                                ->color('success'),
                            TextEntry::make('invoice_number')
                                ->label('Nro. de factura')
                                ->badge()
                                ->color('success'),
                            // ...
                            TextEntry::make('code_agency')
                                ->label('Código de agencia')
                                ->badge()
                                ->color('primary'),
                            TextEntry::make('created_at')
                                ->label('Pagada el:')
                                ->badge()
                                ->dateTime(),
                        ])->columnSpanFull()->columns(5),

                    Fieldset::make()
                        ->schema([
                            TextEntry::make('amount')
                                ->label('Importe pagado por el afiliado:')
                                ->prefix('US$ ')
                                ->badge()
                                ->color('primary')
                                ->numeric(),
                            TextEntry::make('commission_agent')
                                ->label('Porcentaje del agente:')
                                ->suffix('%')
                                ->badge()
                                ->color('warning')
                                ->numeric(),
                            TextEntry::make('total_payment_commission')
                                ->label('Total pagado:')
                                ->prefix('US$ ')
                                ->color('warning')
                                ->numeric(),
                            TextEntry::make('created_by')
                                ->label('Pagado por:')
                                ->badge()
                                ->color('primary'),
                            TextEntry::make('created_at')
                                ->label('Pagada el:')
                                ->badge()
                                ->color('primary')
                                ->dateTime(),
                            ImageEntry::make('document')
                                ->visibility('public')
                        ])
                        ->columnSpanFull()
                        ->columns(4),
                    Fieldset::make('Comprobante de pago')
                        ->schema([
                            ImageEntry::make('document')
                                ->label('Comprobante de pago')
                                // ->visibility('public')
                                ->imageWidth(200)
                                ->defaultImageUrl(url('storage/01JY4PYWWM9EA2SBGNC47070HX.png'))
                        ])
                        ->columnSpanFull()
                        ->columns(4),
                ])->columnSpanFull(),
            ]);
    }
}