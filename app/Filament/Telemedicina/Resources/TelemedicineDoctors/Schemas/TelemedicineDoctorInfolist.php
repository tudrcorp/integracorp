<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineDoctors\Schemas;

use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;

class TelemedicineDoctorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make()
                    ->description('PERFIL DEL DOCTOR(A)')
                    ->columnSpanFull()
                    ->icon(Heroicon::Bars3BottomLeft)
                    ->schema([
                        Fieldset::make('INFORMACIÓN PRINCIPAL')
                            ->schema([
                                ImageEntry::make('image')
                                    ->label('Foto de Perfil:')
                                    ->imageHeight(200)
                                    ->circular(),
                                    Fieldset::make()
                                        ->schema([
                                            TextEntry::make('full_name')
                                                ->label('Nombre Completo:'),
                                            TextEntry::make('nro_identificacion')
                                                ->label('Número de Identificación:'),
                                            TextEntry::make('email')
                                                ->label('Correo Electrónico:'),
                                            TextEntry::make('phone')
                                                ->label('Teléfono:'),
                                        ])->columnSpanFull()->columns(4),
                                    Fieldset::make()
                                        ->schema([
                                            TextEntry::make('specialty')
                                                ->badge()
                                                ->color('primary')
                                                ->label('Especialidad:'),
                                            TextEntry::make('code_mpps')
                                                ->badge()
                                                ->color('primary')
                                                ->label('Código MPPS:'),
                                            TextEntry::make('code_cm')
                                                ->badge()
                                                ->color('primary')
                                                ->label('Código CM:'),
                                            ImageEntry::make('signature')
                                                ->label('Firma:')
                                        ])->columnSpanFull()->columns(4),
                            ])->columnSpanFull()->columns(1),
                    ])->columnSpanFull(),

            ]);
    }
}