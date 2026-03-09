<?php

namespace App\Filament\Operations\Resources\OperationOnCallUsers\Schemas;

use App\Models\RrhhColaborador;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class OperationOnCallUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Colaborador')
                    ->description('Colaborador que será asignado a la guardia.')
                    ->icon('heroicon-s-user')
                    ->schema([
                        Select::make('rrhh_colaborador_id')
                            ->label('Colaborador')
                            ->options(RrhhColaborador::all()->pluck('fullName', 'id'))
                            ->afterStateUpdated(function (Set $set, $state) {
                                $set('name', RrhhColaborador::find($state)?->fullName);
                                $set('email', RrhhColaborador::find($state)?->emailCorporativo);
                                $set('phone', RrhhColaborador::find($state)?->telefono);
                            })
                            ->live()
                            ->searchable()
                            ->preload()
                            ->required(),
                        Hidden::make('name'),
                        Hidden::make('email'),
                        Hidden::make('phone'),
                        DatePicker::make('date_OnCall')
                            ->label('Fecha de Guardia')
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state == now()->format('d/m/Y')) {
                                    $set('status', 'DE GUARDIA');
                                } else {
                                    $set('status', 'PROGRAMADA');
                                }
                            })
                            ->required()
                            ->format('d/m/Y'),
                        TextInput::make('status')
                            ->label('Estatus')
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255),
                        Hidden::make('created_by')
                            ->default(Auth::user()->name),
                        Hidden::make('updated_by')
                            ->default(Auth::user()->name),
                    ])->columnSpanFull()->columns(4),
            ]);
    }
}
