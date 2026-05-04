<?php

namespace App\Filament\Operations\Resources\OperationOnCallUsers\Schemas;

use App\Models\RrhhColaborador;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class OperationOnCallUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Colaborador en guardia')
                    ->description('Elige a la persona de RRHH; el contacto se copia a este registro para coordinación y reportes.')
                    ->icon(Heroicon::UserCircle)
                    ->schema([
                        Fieldset::make('Persona asignada')
                            ->schema([
                                Select::make('rrhh_colaborador_id')
                                    ->label('Colaborador')
                                    ->relationship('rrhh_colaborador', 'fullName', fn ($query) => $query->orderBy('fullName'))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->columnSpanFull()
                                    ->helperText('Búsqueda por nombre. El listado sale del catálogo de colaboradores.')
                                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                                        $colaborador = RrhhColaborador::find($state);
                                        $set('name', $colaborador?->fullName);
                                        $set('email', $colaborador?->emailCorporativo);
                                        $set('phone', $colaborador?->telefono);
                                    }),
                                TextInput::make('name')
                                    ->label('Nombre en el registro')
                                    ->disabled()
                                    ->dehydrated()
                                    ->placeholder('—')
                                    ->helperText('Sincronizado al elegir colaborador.')
                                    ->columnSpan(['default' => 1, 'lg' => 1]),
                                TextInput::make('email')
                                    ->label('Correo corporativo')
                                    ->disabled()
                                    ->dehydrated()
                                    ->placeholder('—')
                                    ->prefixIcon('heroicon-m-envelope')
                                    ->helperText('Desde el correo corporativo en RRHH.')
                                    ->columnSpan(['default' => 1, 'lg' => 1]),
                                TextInput::make('phone')
                                    ->label('Teléfono de contacto')
                                    ->disabled()
                                    ->dehydrated()
                                    ->placeholder('—')
                                    ->prefixIcon('heroicon-m-phone')
                                    ->helperText('Teléfono principal del colaborador en RRHH.')
                                    ->columnSpan(['default' => 1, 'lg' => 1]),
                            ])
                            ->columns(['default' => 1, 'lg' => 3]),
                    ])
                    ->columnSpanFull(),

                Section::make('Turno y estado')
                    ->description('Fecha de la guardia, franja horaria (referencia) y estado según el día de hoy.')
                    ->icon(Heroicon::CalendarDays)
                    ->schema([
                        Fieldset::make('Programación')
                            ->schema([
                                DatePicker::make('date_OnCall')
                                    ->label('Fecha de guardia')
                                    ->native(false)
                                    ->prefixIcon('heroicon-m-calendar-days')
                                    ->format('d/m/Y')
                                    ->displayFormat('d/m/Y')
                                    ->required()
                                    ->live()
                                    ->helperText('Si la fecha es hoy, el estado pasa a «De guardia». En otro caso, «Programada».')
                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                        self::syncStatusFromDate($set, $state);
                                    }),
                                TextInput::make('hrs_init')
                                    ->label('Hora inicio')
                                    ->placeholder('00:00')
                                    ->maxLength(5)
                                    ->mask('99:99')
                                    ->helperText('Opcional. Formato 24 h (ej. 08:00). Coincide con el campo «Horario» en la tabla.'),
                                TextInput::make('hrs_end')
                                    ->label('Hora fin')
                                    ->placeholder('00:00')
                                    ->maxLength(5)
                                    ->mask('99:99')
                                    ->helperText('Opcional. Franja de referencia del turno (ej. 18:00).'),
                                TextInput::make('status')
                                    ->label('Estado del turno')
                                    ->disabled()
                                    ->dehydrated()
                                    ->required()
                                    ->default('PROGRAMADA')
                                    ->helperText('Se actualiza al cambiar la fecha: si es hoy → De guardia; si no → Programada.')
                                    ->prefixIcon('heroicon-m-flag')
                                    ->placeholder('—'),
                            ])
                            ->columns(['default' => 1, 'sm' => 2, 'xl' => 4]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function syncStatusFromDate(Set $set, mixed $state): void
    {
        if ($state === null || $state === '') {
            return;
        }

        try {
            if ($state instanceof Carbon) {
                $date = $state->copy()->startOfDay();
            } else {
                $str = (string) $state;
                $date = str_contains($str, '/')
                    ? Carbon::createFromFormat('d/m/Y', $str)->startOfDay()
                    : Carbon::parse($str)->startOfDay();
            }
        } catch (\Throwable) {
            return;
        }

        $set('status', $date->isSameDay(now()->startOfDay()) ? 'DE GUARDIA' : 'PROGRAMADA');
    }
}
