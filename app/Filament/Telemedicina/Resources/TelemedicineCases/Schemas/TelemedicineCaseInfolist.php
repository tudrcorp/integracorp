<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineCases\Schemas;

use Filament\Schemas\Schema;
use App\Models\TelemedicineCase;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Infolists\Components\TextEntry;

class TelemedicineCaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->description('Información detallada del caso')
                    ->columnSpanFull()
                    ->icon(Heroicon::Bars3BottomLeft)
                    ->schema([
                        Fieldset::make('INFORMACIÓN PRINCIPAL')
                            ->schema([
                                TextEntry::make('code')
                                    ->label('NÚMERO DE CASO:')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('patient_name')
                                    ->label('NOMBRE COMPLETO:')
                                    ->badge()
                                    ->default(fn(TelemedicineCase $record) => strtoupper($record->patient_name))
                                    ->color('success'),
                                TextEntry::make('patient_age')
                                    ->label('EDAD:')
                                    ->suffix(' AÑOS')
                                    ->badge()
                                    ->default(fn(TelemedicineCase $record) => strtoupper($record->patient_age))
                                    ->color('success'),
                                TextEntry::make('patient_sex')
                                    ->label('SEXO:')
                                    ->badge()
                                    ->default(fn(TelemedicineCase $record) => strtoupper($record->patient_sex))
                                    ->color('success'),
                                
                                
                                TextEntry::make('created_at')
                                    ->label('FECHA DE REGISTRO:')
                                    ->badge()
                                    ->color('info')
                                    ->date('d/m/Y'),
                                TextEntry::make('status')
                                    ->label('ESTATUS DEL CASO:')
                                    ->badge()
                                    ->color(function (TelemedicineCase $record) {
                                        if ($record->status == 'EN SEGUIMIENTO') {
                                            return 'warning';
                                        } elseif ($record->status == 'CONSULTA INICIAL') {
                                            return 'info';
                                        } elseif ($record->status == 'ALTA MEDICA') {
                                            return 'success';
                                        }
                                    }),
                                TextEntry::make('updated_at')
                                    ->label('Ultima Actualización')
                                    ->default(fn(TelemedicineCase $record): string => $record->updated_at->diffForHumans()),

                            ])->columnSpanFull()->columns(5),

                        Fieldset::make('DIRECCIÓN Y UBICACIÓN DEL PACIENTE')
                            ->schema([
                                TextEntry::make('patient_address')
                                    ->label('DIRECCIÓN:')
                                    ->badge()
                                    ->color('success')
                                    ->columnSpanFull(),
                                TextEntry::make('patient_phone')
                                    ->label('NUMERO DE TELÉFONO:')
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('city.definition')
                                    ->label('CIUDAD:')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('state.definition')
                                    ->label('ESTADO:')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('country.name')
                                    ->label('PAÍS:')
                                    ->badge()
                                    ->color('success'),

                            ])->columnSpanFull()->columns(5),
                    ])->columnSpanFull(),
            ]);
    }
}