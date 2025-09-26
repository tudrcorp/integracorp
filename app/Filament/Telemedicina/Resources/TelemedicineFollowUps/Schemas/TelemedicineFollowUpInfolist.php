<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineFollowUps\Schemas;

use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;
use App\Models\TelemedicineFollowUp;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Infolists\Components\TextEntry;
use App\Models\TelemedicineConsultationPatient;
use Filament\Infolists\Components\RepeatableEntry;

class TelemedicineFollowUpInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make()
                    ->description('Información Principal del Seguimiento')
                    ->columnSpanFull()
                    ->icon(Heroicon::Bars3BottomLeft)
                    ->schema([
                        Fieldset::make('INFORMACIÓN PRINCIPAL')
                            ->schema([
                                TextEntry::make('telemedicineCase.code')
                                    ->label('Numero de Caso:')
                                    ->icon(Heroicon::Hashtag)
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('telemedicinePatient.full_name')
                                    ->label('Paciente:')
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('telemedicineDoctor.full_name')
                                    ->label('Caso Atenido por:')
                                    ->prefix('Dr(a). ')
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('created_at')
                                    ->label('Fecha de creación:')
                                    ->badge()
                                    ->color('primary')
                                    ->icon(Heroicon::CalendarDays),
                                TextEntry::make('status')
                                    ->label('Estatus de Caso:')
                                    ->badge()
                                    ->color('warning')
                                    ->icon(Heroicon::CalendarDays),
                            ])->columnSpanFull()->columns(5),
                    ])->columnSpanFull(),

                //...Preguntas de seguimiento
                Section::make()
                    ->collapsed(true)
                    ->description('Resouestas de Seguimiento del paciente')
                    ->columnSpanFull()
                    ->icon(Heroicon::Bars3BottomLeft)
                    ->schema([
                        Fieldset::make('1.- ¿COMO SE SIENTE EL DIA DE HOY?')
                            ->schema([
                                TextEntry::make('cuestion_1')
                                    ->label('Detalle:'),
                            ])->columnSpanFull()->columns(1),

                        Fieldset::make('2.- ¿Cómo HA RESPONDIDO AL TRATAMIENTO INDICADO?')
                            ->schema([
                                TextEntry::make('cuestion_1')
                                    ->label('Detalle:'),
                            ])->columnSpanFull()->columns(1),

                        Fieldset::make('3. ¿SIENTE QUE HAN MEJORADO LOS SÍNTOMAS?')
                            ->schema([
                                TextEntry::make('cuestion_1')
                                    ->label('Detalle:'),
                            ])->columnSpanFull()->columns(1),

                        Fieldset::make('4. ¿SE REALIZO LOS ESTUDIOS SOLICITADOS?')
                            ->schema([
                                TextEntry::make('cuestion_1')
                                    ->label('Detalle:'),
                            ])->columnSpanFull()->columns(1),

                        Fieldset::make('5. EN VISTA DE QUE SUS RESULTADOS DE LABORATORIO ESTÁN ALTERADOS, SE MODIFICAN LAS INDICACIONES MEDICAS.')
                            ->schema([
                                TextEntry::make('cuestion_1')
                                    ->label('Detalle:'),
                            ])->columnSpanFull()->columns(1),
                    ])->columnSpanFull(),

                //...Preguntas de seguimiento
                Section::make()
                    ->collapsed(true)
                    ->description('Servicio Asignado durante el seguimiento')
                    ->columnSpanFull()
                    ->icon(Heroicon::Bars3BottomLeft)
                    ->schema([
                        
                        Fieldset::make('Servicio')
                            ->schema([
                                TextEntry::make('telemedicineServiceList.name')
                                    ->label('Tipo de Servicio:'),
                            ])->columnSpanFull()->columns(1),

                        Fieldset::make('Tratamiento Medico')
                            ->schema([
                                RepeatableEntry::make('TelemedicinePatientMedications')
                                    ->label('Medicamentos Indicados')
                                    ->schema([
                                        TextEntry::make('medicine')
                                            ->label('Medicamento'),
                                        TextEntry::make('indications')
                                            ->label('Indicaciones'),
                                    ])
                                    ->columns(2)
                                    ->grid(1),
                            ])->columnSpanFull()->columns(1),
                            
                    ])->columnSpanFull(),

        ]);
    }
}