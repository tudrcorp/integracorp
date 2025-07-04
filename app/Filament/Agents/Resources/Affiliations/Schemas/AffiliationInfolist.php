<?php

namespace App\Filament\Agents\Resources\Affiliations\Schemas;

use App\Models\Affiliation;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;

class AffiliationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
            Section::make()
                ->description(fn(Affiliation $record) => 'Pre-afiliación generada el: ' . $record->created_at->format('d/m/Y H:ma'))
                ->columnSpanFull()
                ->icon(Heroicon::Bars3BottomLeft)
                ->schema([
                    Fieldset::make('Información')
                        ->schema([
                            TextEntry::make('code')
                                ->label('Nro. de solicitud:')
                                ->badge()
                                ->color('success'),
                            TextEntry::make('individual_quote.code')
                                ->label('Nro. de cotización:')
                                ->badge()
                                ->color('success'),
                            // ...
                            TextEntry::make('created_by')
                                ->label('Registrado por:')
                                ->badge()
                                ->color('primary')
                                ->default(fn(Affiliation $record) => 'AGT-000' . $record->agent_id . ' : ' . $record->full_name),
                            TextEntry::make('created_at')
                                ->label('Fecha:')
                                ->badge()
                                ->icon(Heroicon::CalendarDays)
                                ->dateTime(),
                        ])->columnSpanFull()->columns(5),

                    Fieldset::make('Información del Contratante')
                        ->schema([
                            TextEntry::make('full_name_con')
                                ->label('Nombre completo:'),
                            TextEntry::make('birth_date_con')
                                ->badge()
                                ->icon(Heroicon::CalendarDays)
                                ->dateTime('d/m/Y')
                                ->label('Fecha de nacimiento:'),
                            TextEntry::make('email_con:')
                                ->label('Correo electrónico:'),
                            TextEntry::make('phone_con')
                                ->label('Teléfono:'),
                        ])->columnSpanFull()->columns(4),

                    Fieldset::make('Información del Titular')
                        ->schema([
                            TextEntry::make('full_name_ti')
                                ->label('Nombre completo:'),
                            TextEntry::make('birth_date_ti')
                                ->badge()
                                ->icon(Heroicon::CalendarDays)
                                ->dateTime('d/m/Y')
                                ->label('Fecha de nacimiento:'),
                            TextEntry::make('email_ti')
                                ->label('Correo electrónico:'),
                            TextEntry::make('phone_ti')
                                ->label('Teléfono:'),
                        ])->columnSpanFull()->columns(4),

                    Fieldset::make('Plan y Frecuencia de pago')
                        ->schema([
                            TextEntry::make('plan.description')
                                ->label('Plan')
                                ->badge()
                                ->color('primary')
                                ->numeric(),
                            TextEntry::make('coverage.price')
                                ->label('Precio')
                                ->badge()
                                ->color('primary'),
                            TextEntry::make('fee_anual')
                                ->label('Tarifa anual')
                                ->prefix('US$ ')
                                ->badge()
                                ->color('primary')
                                ->numeric(),
                            TextEntry::make('payment_frequency')
                                ->label('Frecuencia de pago')
                                ->badge()
                                ->color('primary'),
                            TextEntry::make('total_amount')
                                ->label('Monto total')
                                ->prefix('US$ ')
                                ->badge()
                                ->color('success')
                                ->numeric(),
                            TextEntry::make('family_members')
                                ->label('Miembros de la familia')
                                ->suffix(' Persona(s)')
                                ->badge()
                                ->color('primary')
                                ->numeric(),
                        ])->columnSpanFull()->columns(4),

                    // Fieldset::make('ILS')
                    //     ->schema([
                    //         TextEntry::make('vaucher_ils')
                    //             ->label('Voucher')
                    //             ->prefix('ILS-')
                    //             ->badge()
                    //             ->color('primary')
                    //             ->numeric(),
                    //         TextEntry::make('date_payment_initial_ils')
                    //             ->label('Fecha de inicio')
                    //             ->badge()
                    //             ->icon(Heroicon::CalendarDays)
                    //             ->dateTime('d/m/Y'),
                    //         TextEntry::make('date_payment_final_ils')
                    //             ->label('Fecha de final')
                    //             ->badge()
                    //             ->icon(Heroicon::CalendarDays)
                    //             ->dateTime('d/m/Y'),
                    //         ImageEntry::make('document_ils')
                    //             ->label('Comprobante ILS')
                    //     ])->columnSpanFull()->columns(3),
                ])->columnSpanFull(),
            ]);
    }
}