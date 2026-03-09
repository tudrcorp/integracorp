<?php

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class OperationCoordinationServiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Solicitud')
                    ->description('Datos generales de la coordinación')
                    ->icon(Heroicon::DocumentText)
                    ->schema([
                        Fieldset::make('Datos de la solicitud')
                            ->schema([
                                TextEntry::make('date_solicitud')
                                    ->label('Fecha solicitud')
                                    ->placeholder('-'),
                                TextEntry::make('date_service')
                                    ->label('Fecha servicio')
                                    ->placeholder('-'),
                                TextEntry::make('businessLine.definition')
                                    ->label('Línea de negocio')
                                    ->placeholder('-'),
                                TextEntry::make('businessUnit.definition')
                                    ->label('Unidad de negocio')
                                    ->placeholder('-'),
                                TextEntry::make('reference_number')
                                    ->label('Nº referencia')
                                    ->placeholder('-'),
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->placeholder('-'),
                            ])->columns(2),
                    ])->columnSpanFull(),

                Section::make('Titular y paciente')
                    ->collapsed(true)
                    ->description('Información del titular y del paciente')
                    ->icon(Heroicon::UserGroup)
                    ->schema([
                        Fieldset::make('Titular')
                            ->schema([
                                TextEntry::make('holder')
                                    ->label('Titular')
                                    ->placeholder('-'),
                                TextEntry::make('ci_holder')
                                    ->label('C.I. titular')
                                    ->placeholder('-'),
                                TextEntry::make('phone_holder')
                                    ->label('Teléfono titular')
                                    ->placeholder('-'),
                            ])->columns(2),
                        Fieldset::make('Paciente')
                            ->schema([
                                TextEntry::make('patient')
                                    ->label('Paciente')
                                    ->placeholder('-'),
                                TextEntry::make('ci_patient')
                                    ->label('C.I. paciente')
                                    ->placeholder('-'),
                                TextEntry::make('birth_date_patient')
                                    ->label('Fecha nacimiento')

                                    ->placeholder('-'),
                                TextEntry::make('age_patient')
                                    ->label('Edad')
                                    ->suffix(' años')
                                    ->placeholder('-'),
                                TextEntry::make('relationship_patient')
                                    ->label('Parentesco')
                                    ->placeholder('-'),
                            ])->columns(2),
                    ])->columnSpanFull(),

                Section::make('Ubicación')
                    ->collapsed(true)
                    ->description('Dirección y ubicación')
                    ->icon(Heroicon::MapPin)
                    ->schema([
                        Fieldset::make('Dirección y contacto')
                            ->schema([
                                TextEntry::make('contractor')
                                    ->label('Contratante')
                                    ->placeholder('-'),
                                TextEntry::make('state.definition')
                                    ->label('Estado')
                                    ->placeholder('-'),
                                TextEntry::make('city.definition')
                                    ->label('Ciudad')
                                    ->placeholder('-'),
                                TextEntry::make('address')
                                    ->label('Dirección')
                                    ->placeholder('-')
                                    ->columnSpanFull(),
                            ])->columns(2),
                    ])->columnSpanFull(),

                Section::make('Servicio')
                    ->collapsed(true)
                    ->description('Detalle del servicio solicitado')
                    ->icon(Heroicon::WrenchScrewdriver)
                    ->schema([
                        Fieldset::make('Detalle del servicio')
                            ->schema([
                                TextEntry::make('symptoms_diagnosis')
                                    ->label('Síntomas / diagnóstico')
                                    ->placeholder('-')
                                    ->columnSpanFull(),
                                TextEntry::make('servicie')
                                    ->label('Servicio')
                                    ->placeholder('-'),
                                TextEntry::make('specific_service')
                                    ->label('Servicio específico')
                                    ->placeholder('-'),
                                TextEntry::make('type_service')
                                    ->label('Tipo de servicio')
                                    ->placeholder('-'),
                                TextEntry::make('supplier_service')
                                    ->label('Proveedor del servicio')
                                    ->placeholder('-'),
                                TextEntry::make('farmadoc')
                                    ->label('Farmadoc')
                                    ->placeholder('-'),
                            ])->columns(2),
                    ])->columnSpanFull(),

                Section::make('Negociación y precios')
                    ->collapsed(true)
                    ->description('Montos y negociación')
                    ->icon(Heroicon::CurrencyDollar)
                    ->schema([
                        Fieldset::make('Negociación')
                            ->schema([
                                TextEntry::make('type_negotiation')
                                    ->label('Tipo negociación')
                                    ->placeholder('-'),
                                TextEntry::make('status_negotiation')
                                    ->label('Estado negociación')
                                    ->placeholder('-'),
                                TextEntry::make('negotiation')
                                    ->label('Negociación')
                                    ->placeholder('-'),
                                TextEntry::make('neto')
                                    ->label('Neto')
                                    ->numeric()
                                    ->placeholder('-'),
                                TextEntry::make('porcen_tdec')
                                    ->label('% TDEC')
                                    ->numeric()
                                    ->suffix('%')
                                    ->placeholder('-'),
                                TextEntry::make('quote_price')
                                    ->label('Precio cotizado')
                                    ->money()
                                    ->placeholder('-'),
                                TextEntry::make('porcen_discount')
                                    ->label('% descuento')
                                    ->numeric()
                                    ->suffix('%')
                                    ->placeholder('-'),
                                TextEntry::make('price_discount')
                                    ->label('Precio con descuento')
                                    ->numeric()
                                    ->placeholder('-'),
                            ])->columns(2),
                    ])->columnSpanFull(),

                Section::make('Documentos y facturación')
                    ->collapsed(true)
                    ->description('Números de cotización, orden y factura')
                    ->icon(Heroicon::DocumentDuplicate)
                    ->schema([
                        Fieldset::make('Números y factura')
                            ->schema([
                                TextEntry::make('quote_number')
                                    ->label('Nº cotización')
                                    ->placeholder('-'),
                                TextEntry::make('approved_number')
                                    ->label('Nº aprobación')
                                    ->placeholder('-'),
                                TextEntry::make('service_order_number')
                                    ->label('Nº orden de servicio')
                                    ->placeholder('-'),
                                TextEntry::make('bill_number')
                                    ->label('Nº factura')
                                    ->placeholder('-'),
                                TextEntry::make('bill_price')
                                    ->label('Monto factura')
                                    ->money()
                                    ->placeholder('-'),
                                TextEntry::make('bill_date')
                                    ->label('Fecha factura')

                                    ->placeholder('-'),
                            ])->columns(2),
                    ])->columnSpanFull(),

                Section::make('Incidencias y observaciones')
                    ->collapsed(true)
                    ->description('Notas e incidencias')
                    ->icon(Heroicon::ChatBubbleLeftRight)
                    ->schema([
                        Fieldset::make('Observaciones')
                            ->schema([
                                TextEntry::make('incidence')
                                    ->label('Incidencia')
                                    ->placeholder('-')
                                    ->columnSpanFull(),
                                TextEntry::make('negotiation_description')
                                    ->label('Descripción negociación')
                                    ->placeholder('-')
                                    ->columnSpanFull(),
                                TextEntry::make('qc_description')
                                    ->label('Descripción QC')
                                    ->placeholder('-')
                                    ->columnSpanFull(),
                                TextEntry::make('observations')
                                    ->label('Observaciones')
                                    ->placeholder('-')
                                    ->columnSpanFull(),
                            ]),
                    ])->columnSpanFull(),

                Section::make('Vinculación telemedicina')
                    ->collapsed(true)
                    ->description('Identificadores de telemedicina')
                    ->icon(Heroicon::Signal)
                    ->collapsible()
                    ->schema([
                        Fieldset::make('IDs telemedicina')
                            ->schema([
                                TextEntry::make('telemedicine_patient_id')
                                    ->label('ID paciente')
                                    ->placeholder('-'),
                                TextEntry::make('telemedicine_case_id')
                                    ->label('ID caso')
                                    ->placeholder('-'),
                                TextEntry::make('telemedicine_doctor_id')
                                    ->label('ID doctor')
                                    ->placeholder('-'),
                                TextEntry::make('telemedicine_consultation_patient_id')
                                    ->label('ID consulta paciente')
                                    ->placeholder('-'),
                            ])->columns(2),
                    ])->columnSpanFull(),

                Section::make('Auditoría')
                    ->collapsed(true)
                    ->description('Registro de cambios')
                    ->icon(Heroicon::Clock)
                    ->schema([
                        Fieldset::make('Registro')
                            ->schema([
                                TextEntry::make('created_by')
                                    ->label('Creado por')
                                    ->placeholder('-'),
                                TextEntry::make('updated_by')
                                    ->label('Actualizado por')
                                    ->placeholder('-'),
                                TextEntry::make('created_at')
                                    ->label('Fecha creación')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('-'),
                                TextEntry::make('updated_at')
                                    ->label('Última actualización')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('-'),
                            ])->columns(2),
                    ])->columnSpanFull(),
            ]);
    }
}
