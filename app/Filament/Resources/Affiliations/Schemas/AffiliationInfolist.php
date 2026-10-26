<?php

namespace App\Filament\Resources\Affiliations\Schemas;

use App\Filament\Shared\MedicalQuestionnaires\MedicalQuestionnaireAnswersEntry;
use App\Models\Affiliation;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AffiliationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make()
                    ->description(fn (Affiliation $record) => 'Pre-afiliación generada el: '.$record->created_at->format('d/m/Y H:ma').' - Creada por: '.$record->created_by)
                    ->columnSpanFull()
                    ->icon(Heroicon::Bars3BottomLeft)
                    ->schema([
                        Fieldset::make('PREAFILIACION')
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
                                    ->default(fn (Affiliation $record) => 'AGT-000'.$record->agent_id.' : '.$record->full_name),
                                TextEntry::make('created_at')
                                    ->label('Fecha:')
                                    ->badge()
                                    ->icon(Heroicon::CalendarDays)
                                    ->dateTime(),
                                TextEntry::make('status')
                                    ->label('Estatus de la Afiliación:')
                                    ->badge()
                                    ->color('warning')
                                    ->icon(Heroicon::InformationCircle),
                                TextEntry::make('activation_date')
                                    ->label('Fecha de Activación:')
                                    ->badge()
                                    ->color('warning')
                                    ->icon(Heroicon::InformationCircle),

                            ])->columnSpanFull()->columns(5),

                        Fieldset::make('TITULAR DE LA PÓLIZA')
                            ->schema([
                                TextEntry::make('full_name_ti')
                                    ->badge()
                                    ->color('info')
                                    ->icon('fluentui-person-available-16')
                                    ->label('Nombre y Apellido:'),
                                TextEntry::make('nro_identificacion_ti')
                                    ->label('Nro. de Identificación:'),
                                TextEntry::make('email_ti')
                                    ->label('Correo electrónico:'),
                                TextEntry::make('phone_ti')
                                    ->label('Número de teléfono:'),
                            ])->columnSpanFull()->columns(4),

                        Fieldset::make('RESPONSABLE DE PAGO (PAGADOR)')
                            ->schema([
                                TextEntry::make('full_name_payer')
                                    ->badge()
                                    ->color('info')
                                    ->icon('fluentui-money-hand-20')
                                    ->label('Nombre y Apellido:'),
                                TextEntry::make('nro_identificacion_payer')
                                    ->label('Nro. de Identificación:'),
                                TextEntry::make('email_payer')
                                    ->label('Correo electrónico:'),
                                TextEntry::make('phone_payer')
                                    ->label('Número de teléfono:'),
                            ])->columnSpanFull()->columns(4),

                        Fieldset::make('PLAN Y FRECUENCIA DE PAGO')
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

                        Fieldset::make('INFORMACIÓN ILS')
                            ->schema([
                                TextEntry::make('vaucher_ils')
                                    ->label('Voucher ILS')
                                    ->badge()
                                    ->color('primary')
                                    ->icon(Heroicon::DocumentText),
                                TextEntry::make('date_payment_initial_ils')
                                    ->label('Fecha de inicio de cobro:')
                                    ->badge()
                                    ->color('primary')
                                    ->icon(Heroicon::CalendarDays),
                                TextEntry::make('date_payment_final_ils')
                                    ->label('Fecha de fin de cobro:')
                                    ->badge()
                                    ->color('primary')
                                    ->icon(Heroicon::CalendarDays),
                            ])->columnSpanFull()->columns(4),

                        Fieldset::make('DECLARACIÓN MÉDICA')
                            ->schema([
                                MedicalQuestionnaireAnswersEntry::make(),
                            ])->columnSpanFull()->columns(3),
                    ])->columnSpanFull(),

                TextEntry::make('activation_date'),
            ]);
    }
}
