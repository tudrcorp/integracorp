<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineDoctors\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Console\View\Components\Secret;

class TelemedicineDoctorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Información Personal')
                        ->schema([
                            Section::make()
                                ->heading('Información principal del Doctor(a)')
                                ->description('...')
                                ->schema([
                                    Grid::make()
                                        ->schema([
                                            FileUpload::make('image')
                                                ->label('Foto de perfil')
                                                ->image(),
                                        ])->columnSpanFull()->columns(4),
                                    TextInput::make('full_name')
                                        ->label('Nombre y Apellido')
                                        ->required(),
                                    TextInput::make('nro_identificacion')
                                    ->label('Cedula de Identidad')
                                        ->required(),
                                    TextInput::make('phone')
                                    ->label('Número de Teléfono')
                                        ->tel(),
                                    TextInput::make('email')
                                        ->label('Correo Electrónico')
                                        ->email()
                                        ->required(),
                                    
                                ])->columnSpanFull()->columns(4)
                        ]),
                    Step::make('Información Profesional')
                        ->schema([
                            Section::make()
                                ->heading('Información profesional del Doctor(a)')
                                ->description('...')
                                ->schema([
                                    TextInput::make('specialty')
                                        ->required()
                                        ->default('MÉDICO GENERAL'),
                                    TextInput::make('code_cm')
                                        ->label('Código CM(Colegio de Medicos)')
                                        ->mask('99999')
                                        ->required(),
                                    TextInput::make('code_mpps')
                                        ->label('Código MPPS(Ministerio de Salud Publica)')
                                        ->numeric()
                                        ->mask('999999'),
                                ])->columnSpanFull()->columns(3)
                        ]),
                    Step::make('Firma Digital')
                        ->schema([
                            FileUpload::make('signature')
                                ->image()
                                ->label('Firma Digital del Doctor(a)')
                                 ->directory('firmas-medicos')
                                ->required()
                                ->visibility('public'),
                        ])->columnSpanFull()->columns(3),
                ])->columnSpanFull(),
                
            ]);
    }
}