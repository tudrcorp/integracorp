<?php

namespace App\Filament\Telemedicina\Resources\TelemedicinePatients\Schemas;

use Filament\Schemas\Schema;
use App\Models\TelemedicinePatient;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Infolists\Components\TextEntry;

class TelemedicinePatientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->description(fn(TelemedicinePatient $record) => 'PACIENTE: ' . $record->full_name . ' | ' . 'EDAD: ' . $record->age . ' años | ' . 'SEXO: ' . $record->sex)
                    ->columnSpanFull()
                    ->icon(Heroicon::Bars3BottomLeft)
                    ->schema([
                        Fieldset::make('INFORMACIÓN PRINCIPAL')
                            ->schema([
                                TextEntry::make('full_name')
                                    ->label('Nombre Completo:')
                                    ->badge()
                                    ->default(fn(TelemedicinePatient $record) => strtoupper($record->full_name))
                                    ->color('success'),
                                TextEntry::make('nro_identificacion')
                                    ->label('Número de Identificación:')
                                    ->prefix('V-')
                                    ->badge()
                                    ->color('success'),
                                TextEntry::make('birth_date')
                                    ->label('Fecha de Nacimiento:'),
                                TextEntry::make('age')
                                    ->label('Edad:')
                                    ->suffix(' años'),
                                TextEntry::make('sex')
                                    ->label('Sexo:'),
                                TextEntry::make('phone')
                                    ->label('Teléfono:'),
                                TextEntry::make('email')
                                    ->label('Correo Electrónico:'),
                                TextEntry::make('address')
                                    ->label('Dirección:'),
                                TextEntry::make('city.definition')
                                    ->label('Ciudad:'),
                                TextEntry::make('country.name')
                                    ->label('País:'),
                                TextEntry::make('state.definition')
                                    ->label('Estado:'),
                                TextEntry::make('region')
                                    ->label('Región:'),
                                            
                                TextEntry::make('created_at')
                                    ->label('Fecha de Registro:')
                                    ->badge()
                                    ->dateTime(),

                                
                            ])->columnSpanFull()->columns(5),

                        Fieldset::make('INFORMACIÓN DE LA AFILIACIÓN')
                            ->hidden(fn(TelemedicinePatient $record) => $record->type == 'PACIENTE')
                            ->schema([
                                TextEntry::make('plan.description')
                                    ->badge()
                                    ->color('info')
                                    ->icon('fluentui-person-available-16')
                                    ->label('Plan:'),
                                TextEntry::make('coverage.price')
                                    ->badge()
                                    ->color('info')
                                    ->icon('fluentui-money-hand-20')
                                    ->label('Cobertura:'),
                                TextEntry::make('code_affiliation')
                                    ->badge()
                                    ->color('info')
                                    ->icon('fluentui-money-hand-20')
                                    ->label('Numero de Afiliación:'),
                                TextEntry::make('type_affiliation')
                                    ->badge()
                                    ->color('info')
                                    ->icon('fluentui-money-hand-20')
                                    ->label('Tipo de Afiliación:'),
                                TextEntry::make('status_affiliation')
                                    ->badge()
                                    ->color('info')
                                    ->icon('fluentui-money-hand-20')
                                    ->label('Estado de Afiliación:'),
                                
                            ])->columnSpanFull()->columns(5),
                    ])->columnSpanFull(),
                ]);
    }
}