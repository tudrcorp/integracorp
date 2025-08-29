<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineFollowUps\Schemas;

use Dompdf\Adapter\GD;
use Filament\Schemas\Schema;
use App\Models\TelemedicineCase;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Radio;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Fieldset;
use Symfony\Component\Mime\Part\DataPart;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class TelemedicineFollowUpForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Información Principal del Caso')
                        ->schema([
                            Fieldset::make('Información del Paciente')
                                ->schema([
                                    Select::make('telemedicine_case_id')
                                        ->label('Número de Caso')
                                        ->options(TelemedicineCase::all()->pluck('code', 'id'))
                                        ->live()
                                        ->default(fn (): ?int => request()->query('record') ? request()->query('record') : null)
                                        ->searchable()
                                        ->required(),
                                        Hidden::make('code')->default(function (Get $get): ?string {
                                            return TelemedicineCase::find(request()->query('record'))->code ?? '';
                                        }),
                            ])->columnSpanFull()->columns(4),
                            Grid::make()
                                ->schema([
                                    Fieldset::make('Preguntas de Seguimiento')
                                        ->schema([
                                            Textarea::make('cuestion_1')
                                                ->label('1.- ¿COMO SE SIENTE EL DIA DE HOY?')
                                                ->required()
                                                ->live()
                                                ->autosize()
                                                ->afterStateUpdatedJs(<<<'JS'
                                                    $set('cuestion_1', $state.toUpperCase());
                                                JS),
                                            Textarea::make('cuestion_2')
                                                ->label('2.- ¿COMO HA RESPONDIDO AL TRATAMIENTO INDICADO?')
                                                ->required()
                                                ->autosize()
                                                ->afterStateUpdatedJs(<<<'JS'
                                                    $set('cuestion_2', $state.toUpperCase());
                                                JS),
                                            Textarea::make('cuestion_3')
                                                ->label('3. ¿SIENTE QUE HAN MEJORADO LOS SÍNTOMAS?')
                                                ->required()
                                                ->autosize()
                                                ->afterStateUpdatedJs(<<<'JS'
                                                    $set('cuestion_3', $state.toUpperCase());
                                                JS),
                                            Textarea::make('cuestion_4')
                                                ->label('4. ¿SE REALIZO LOS ESTUDIOS SOLICITADOS?')
                                                ->required()
                                                ->autosize()
                                                ->afterStateUpdatedJs(<<<'JS'
                                                    $set('cuestion_4', $state.toUpperCase());
                                                JS),
                                            Textarea::make('cuestion_5')
                                                ->label('5. EN VISTA DE QUE SUS RESULTADOS DE LABORATORIO ESTÁN ALTERADOS, SE MODIFICAN LAS INDICACIONES MEDICAS.')
                                                ->required()
                                                ->autosize()
                                                ->afterStateUpdatedJs(<<<'JS'
                                                    $set('cuestion_5', $state.toUpperCase());
                                                JS),
                                        ])->columnSpanFull()->columns(2),
                                ])->columnSpanFull(),
                            Hidden::make('telemedicine_patient_id'),
                            Hidden::make('telemedicine_doctor_id'),
                            Hidden::make('telemedicine_consultation_patient_id'),
                            Hidden::make('created_by')->default(Auth::user()->name),
                        ])->columns(4),
                    Step::make('Preguntas de Seguimiento')
                        ->schema([
                            Fieldset::make('Estatus del Caso')
                                ->schema([
                                    Radio::make('feedback')
                                        ->label('El paciente ya se encuentra de ALTA?')
                                        ->boolean(falseLabel: 'No, fijar proximo seguimiento!')
                                        ->inline()
                                        ->default(true)
                                        ->live()
                                ])->columnSpanFull(),
                            Fieldset::make('Fecha de Siguiente Seguimiento')
                                ->hidden(fn (Get $get) => $get('feedback') == true)
                                ->schema([
                                    DatePicker::make('next_follow_up')
                                        ->required()
                                        ->live()
                                        ->label('Fecha')
                                        ->displayFormat('d/m/Y'),
                                    TimePicker::make('hour')
                                        ->prefixIcon(Heroicon::Clock)
                                        ->required()
                                        ->live()
                                        ->seconds(false)
                                        ->label('Hora de Seguimiento'),
                                        
                                ])
                            ]),
                        ])
                ->columnSpanFull()
                ->submitAction(new HtmlString(Blade::render(<<<BLADE
                    <x-filament::button
                        type="submit"
                        size="sm"
                    >
                        Registrar Seguimiento
                    </x-filament::button>
                BLADE)))
            ]);
    }
}