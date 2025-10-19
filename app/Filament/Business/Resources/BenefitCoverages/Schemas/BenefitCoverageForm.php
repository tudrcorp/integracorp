<?php

namespace App\Filament\Business\Resources\BenefitCoverages\Schemas;

use App\Models\Plan;
use App\Models\Coverage;
use App\Models\BenefitPlan;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class BenefitCoverageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Asociacion de los Beneficios a las Coberturas')
                    ->schema([
                        Select::make('plan_id')
                            ->label('Seleccione el Plan')
                            ->options(Plan::all()->pluck('description', 'id'))
                            ->live(),
                        Select::make('benefit_id')
                            ->label('Seleccione el Beneficio asocido al Plan')
                            ->options(fn (Get $get) => BenefitPlan::where('plan_id', $get('plan_id'))->pluck('description', 'benefit_id'))
                            ->live(),
                        Select::make('coverage_id')
                            ->label('Seleccione la Cobertura asociada al Plan')
                            ->options(fn (Get $get) => Coverage::where('plan_id', $get('plan_id'))->pluck('price', 'id'))
                            ->live(),
                        TextInput::make('limit')
                            ->label('Límite de Uso por Cobertura')
                            ->helperText('Este dato es numero. Ejemplo: 30.50, 50, 100')
                                ->required()
                                ->numeric()
                                ->default(0),
                    ])->columnSpanFull()->columns(4),
            ]);
    }
}