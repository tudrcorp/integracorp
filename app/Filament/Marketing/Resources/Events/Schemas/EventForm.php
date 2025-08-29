<?php

namespace App\Filament\Marketing\Resources\Events\Schemas;

use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Wizard\Step;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Formulario Principal')
                        ->schema([
                            Fieldset::make('Flayer del Evento')
                                ->schema([
                                    FileUpload::make('image')
                                        ->required()
                                        ->image()
                                        ->visibility('public'),
                                ])->columnSpanFull()->columns(3),
                            Fieldset::make('Información Principal')
                                ->schema([
                                    TextInput::make('title')
                                        ->label('Título del Evento')
                                        ->required(),
                                    DatePicker::make('dateInit')
                                        ->required()
                                        ->label('Fecha de inicio'),
                                    DatePicker::make('dateEnd')
                                        ->required()
                                        ->label('Fecha de Culminación'),
                                    TextInput::make('total_guest')
                                        ->required()
                                        ->numeric()
                                        ->label('Total de invitados'),
                                    TextInput::make('status')
                                        ->required()
                                        ->default('ACTIVO'),
                                    Hidden::make('created_by')->default(Auth::user()->name),
                                    Textarea::make('description')
                                        ->label('Descripción del Evento. (opcional)')
                                        ->autosize()
                                        ->columnSpanFull(),
                                ])->columnSpanFull()->columns(5),
                        ])
                ])->columnSpanFull(), 
            ]);
    }
}