<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

final class TelemedicineAmdInformFormSchema
{
    /**
     * @return array<int, \Filament\Forms\Components\Component|\Filament\Schemas\Components\Component>
     */
    public static function components(): array
    {
        return [
            Fieldset::make('Signos vitales')
                ->schema([
                    TextInput::make('pa')
                        ->label('Presión Arterial')
                        ->helperText('Presión Arterial (mmHg)')
                        ->numeric(),
                    TextInput::make('fc')
                        ->label('Frecuencia Cardíaca')
                        ->helperText('Frecuencia Cardíaca (lpm)')
                        ->numeric(),
                    TextInput::make('fr')
                        ->label('Frecuencia Respiratoria')
                        ->helperText('Frecuencia Respiratoria (rpm)')
                        ->numeric(),
                    TextInput::make('temp')
                        ->label('Temperatura')
                        ->helperText('Temperatura (°C)')
                        ->numeric(),
                    TextInput::make('saturacion')
                        ->label('Saturación')
                        ->helperText('Saturación (% de oxígeno en sangre)')
                        ->numeric(),
                ])
                ->columns(5)
                ->columnSpanFull(),
            Fieldset::make('Índice de Masa Corporal (IMC)')
                ->schema([
                    TextInput::make('peso')
                        ->label('Peso')
                        ->helperText('Peso (kg)')
                        ->numeric()
                        ->live(onBlur: true)
                        ->required(),
                    TextInput::make('estatura')
                        ->label('Estatura')
                        ->helperText('Metros (mts), Ej: 1.70')
                        ->numeric()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                            $peso = (float) ($get('peso') ?? 0);
                            $estatura = (float) ($state ?? 0);
                            if ($peso > 0 && $estatura > 0) {
                                $set('imc', round($peso / ($estatura * $estatura), 2));
                            }
                        })
                        ->required(),
                    TextInput::make('imc')
                        ->label('Índice de Masa Corporal (IMC)')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(),
                ])
                ->columns(3)
                ->columnSpanFull(),
            Grid::make(1)
                ->schema([
                    Textarea::make('reason_consultation')
                        ->label('Motivo de Consulta')
                        ->autosize()
                        ->required()
                        ->afterStateUpdatedJs(<<<'JS'
                            $set('reason_consultation', $state.toUpperCase());
                        JS),
                    Textarea::make('actual_phatology')
                        ->label('Enfermedad Actual')
                        ->autosize()
                        ->required()
                        ->afterStateUpdatedJs(<<<'JS'
                            $set('actual_phatology', $state.toUpperCase());
                        JS),
                    Textarea::make('background')
                        ->label('Antecedentes Asociados')
                        ->autosize()
                        ->afterStateUpdatedJs(<<<'JS'
                            $set('background', $state.toUpperCase());
                        JS),
                    Textarea::make('diagnostic_impression')
                        ->label('Impresión Diagnóstica')
                        ->autosize()
                        ->required()
                        ->afterStateUpdatedJs(<<<'JS'
                            $set('diagnostic_impression', $state.toUpperCase());
                        JS),
                ])
                ->columnSpanFull(),
        ];
    }
}
