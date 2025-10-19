<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

use App\Models\AffiliationCorporate;
use Filament\Support\Icons\Heroicon;
use App\Models\AfilliationCorporatePlan;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Infolists\Components\IconEntry;

class AffiliationCorporateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->description(fn(AffiliationCorporate $record) => 'Afiliación Corporativa generada el: ' . $record->created_at->format('d/m/Y H:ma'))
                    ->columnSpanFull()
                    ->icon(Heroicon::Bars3BottomLeft)
                    ->schema([
                        Fieldset::make('Información Corporativa de la Empresa')
                            ->schema([
                                TextEntry::make('code')
                                    ->label('Código de Afiliación')
                                    ->badge()
                                    ->color('success'),
                                // ...
                                TextEntry::make('name_corporate')
                                    ->label('Nombre de la Empresa')
                                    ->badge()
                                    ->color('primary'),
                                // ...
                                TextEntry::make('registrated_by')
                                    ->label('Registrado por:')
                                    ->badge()
                                    ->color('primary')
                                    ->default(fn(AffiliationCorporate $record) => 'AGT-000' . $record->agent_id . ' : ' . $record->full_name),
                                // ...
                                TextEntry::make('created_at')
                                    ->label('Fecha de solicitud')
                                    ->badge()
                                    ->dateTime(),
                                    
                                TextEntry::make('activated_at')
                                    ->label('Fecha de Emisión:')
                                    ->badge()
                                    ->color('success'),

                            ])->columnSpanFull()->columns(5),

                        Fieldset::make('Información del Solicitante')
                            ->schema([
                                TextEntry::make('full_name')
                                    ->label('Nombre completo'),
                                TextEntry::make('email')
                                    ->label('Correo electrónico'),
                                TextEntry::make('phone')
                                    ->label('Número de teléfono'),
                                TextEntry::make('status')
                                    ->label('Estatus')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('created_by')
                                    ->label('Registrado por:')
                                    ->badge()
                                    ->color('primary'),
                            ])->columnSpanFull()->columns(5),

                        Fieldset::make('Información de Pagos')
                            ->schema([
                                TextEntry::make('payment_frequency')
                                    ->badge()
                                    ->color('warning')
                                    ->label('Frecuencia de Pago:'),
                                TextEntry::make('fee_anual')
                                    ->badge()
                                    ->color('warning')
                                    ->label('Costo Anual:'),
                                TextEntry::make('total_amount')
                                    ->badge()
                                    ->color('warning')
                                    ->label('Total a Pagar:'),
                                TextEntry::make('vaucher_ils')
                                    ->label('Vaucher ILS:')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('date_payment_initial_ils')
                                    ->label('Fecha de Inicio:')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('date_payment_final_ils')
                                    ->label('Fecha de Vencimiento:')
                                    ->badge()
                                    ->color('success'),
                            ])->columnSpanFull()->columns(3),

                    ])->columnSpanFull(),
            ]);
    }
}