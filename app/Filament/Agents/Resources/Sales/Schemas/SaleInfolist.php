<?php

namespace App\Filament\Agents\Resources\Sales\Schemas;

use App\Models\Sale;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Infolists\Components\TextEntry;

class SaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->description(fn(Sale $record) => 'Venta generada el: ' . $record->created_at->format('d/m/Y h:m A'))
                    ->columnSpanFull()
                    ->icon(Heroicon::Bars3BottomLeft)
                    ->schema([
                        Fieldset::make('Solicitud de cotización individual')
                            ->schema([
                                TextEntry::make('invoice_number')
                                    ->label('Número de solicitud')
                                    ->badge()
                                    ->color('success'),
                                // ...
                                TextEntry::make('code_agency')
                                    ->label('Código de agencia')
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('registrated_by')
                                    ->label('Registrado por:')
                                    ->badge()
                                    ->color('primary')
                                    ->default(fn(Sale $record) => 'AGT-000' . $record->agent_id . ' : ' . $record->full_name),
                                TextEntry::make('date')
                                    ->label('Pagado el:')
                                    ->badge()
                                    ->dateTime('d/m/Y'),
                                TextEntry::make('created_at')
                                    ->label('Fecha de registro')
                                    ->badge()
                                    ->dateTime(),
                            ])->columnSpanFull()->columns(5),

                        Fieldset::make('Afiliación')
                            ->schema([
                                TextEntry::make('type')
                                    ->label('Tipo de afiliación:')
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('plan.description')
                                    ->label('Plan:')
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('coverage.price')
                                    ->label('Cobertura:')
                                    ->prefix('US$ ')
                                    ->badge()
                                    ->color('primary')
                                    ->numeric(),
                                TextEntry::make('persons')
                                    ->label('Poblacion:')
                                    ->suffix(' Persona(s)')
                                    ->badge()
                                    ->color('primary')
                                    ->numeric(),
                                TextEntry::make('affiliation_code')
                                    ->label('Nro. de afiliación:')
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('affiliate_full_name')
                                    ->label('Nombre y Apellido:'),
                                TextEntry::make('affiliate_ci_rif')
                                    ->prefix('J-')
                                    ->label('CI/RIF:')
                                    ->badge(),
                                TextEntry::make('affiliate_contact')
                                    ->label('Contacto:'),
                                TextEntry::make('affiliate_phone')
                                    ->label('Número de teléfono:'),
                                TextEntry::make('affiliate_email')
                                    ->label('Correo electrónico:'),
                                TextEntry::make('created_by')
                                    ->label('Registrado por:')
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('created_at')
                                    ->label('Registrado el:')
                                    ->dateTime(),
                            ])->columnSpanFull()->columns(4),

                        Fieldset::make('Pago Registrado')
                            ->schema([
                                TextEntry::make('total_amount')
                                    ->label('Monto pagado:')
                                    ->prefix('US$ ')
                                    ->badge()
                                    ->color('primary')
                                    ->numeric(),

                                TextEntry::make('payment_method')
                                    ->label('Forma de pago:')
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('payment_frequency')
                                    ->label('Frecuencia de pago:')
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('status_payment_commission')
                                    ->label('Estatus de comisión:')
                                    ->badge()
                                    ->color(function (Sale $record) {
                                        return $record->status_payment_commission == 'POR PAGAR' ? 'warning' : 'success';
                                    }),
                                TextEntry::make('pay_amount_usd')
                                    ->label('Monto pagado USD:')
                                    ->prefix('US$ ')
                                    ->badge()
                                    ->numeric(),
                                TextEntry::make('pay_amount_ves')
                                    ->label('Monto pagado VES:')
                                    ->prefix('VES ')
                                    ->badge()
                                    ->numeric(),
                                TextEntry::make('payment_date')
                                    ->label('Fecha de pago:')
                                    ->badge()
                                    ->dateTime(),

                            ])->columnSpanFull()->columns(4),
                    ])->columnSpanFull(),
            ]);
    }
}
