<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\TelemedicineDoctors\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class TelemedicineDoctorForm
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('telemedicineDoctorFormTabs')
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Información personal')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Section::make('Información principal del doctor(a)')
                                    ->description('Datos de identidad y contacto del perfil médico.')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                            ->schema([
                                                FileUpload::make('image')
                                                    ->label('Foto de perfil')
                                                    ->image()
                                                    ->columnSpanFull(),
                                                TextInput::make('full_name')
                                                    ->label('Nombre y apellido')
                                                    ->required(),
                                                TextInput::make('nro_identificacion')
                                                    ->label('Cédula de identidad')
                                                    ->required(),
                                                TextInput::make('phone')
                                                    ->label('Número de teléfono')
                                                    ->tel(),
                                                TextInput::make('email')
                                                    ->label('Correo electrónico')
                                                    ->email()
                                                    ->required(),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Información profesional')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Section::make('Información profesional del doctor(a)')
                                    ->description('Especialidad y registros profesionales.')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                                            ->schema([
                                                TextInput::make('specialty')
                                                    ->label('Especialidad')
                                                    ->required()
                                                    ->default('MÉDICO GENERAL'),
                                                TextInput::make('code_cm')
                                                    ->label('Código CM (Colegio de Médicos)')
                                                    ->mask('99999')
                                                    ->required(),
                                                TextInput::make('code_mpps')
                                                    ->label('Código MPPS (Ministerio de Salud Pública)')
                                                    ->numeric()
                                                    ->mask('999999'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Firma digital')
                            ->icon('heroicon-o-pencil-square')
                            ->schema([
                                Section::make('Firma digital')
                                    ->description('Sello digital para documentos médicos generados en el sistema.')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        FileUpload::make('signature')
                                            ->label('Firma digital del doctor(a)')
                                            ->image()
                                            ->directory('firmas-medicos')
                                            ->required()
                                            ->visibility('public')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
