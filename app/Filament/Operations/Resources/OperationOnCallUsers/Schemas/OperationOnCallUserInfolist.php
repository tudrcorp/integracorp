<?php

namespace App\Filament\Operations\Resources\OperationOnCallUsers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class OperationOnCallUserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Colaborador y contacto')
                    ->description('Persona asignada a la guardia y datos de contacto guardados en este registro.')
                    ->icon(Heroicon::UserGroup)
                    ->schema([
                        Fieldset::make('Identificación')
                            ->schema([
                                TextEntry::make('rrhh_colaborador.fullName')
                                    ->label('Colaborador (RRHH)')
                                    ->placeholder('—')
                                    ->weight(FontWeight::SemiBold),
                                TextEntry::make('name')
                                    ->label('Nombre en el registro')
                                    ->placeholder('—')
                                    ->copyable()
                                    ->icon('heroicon-m-user'),
                                TextEntry::make('email')
                                    ->label('Correo corporativo')
                                    ->placeholder('—')
                                    ->copyable()
                                    ->icon('heroicon-m-envelope'),
                                TextEntry::make('phone')
                                    ->label('Teléfono')
                                    ->placeholder('—')
                                    ->copyable()
                                    ->icon('heroicon-m-phone'),
                            ])
                            ->columns(['default' => 1, 'md' => 2]),
                    ])
                    ->columnSpanFull(),

                Section::make('Turno de guardia')
                    ->description('Fecha, horario de referencia y estado del turno.')
                    ->icon(Heroicon::CalendarDays)
                    ->schema([
                        Fieldset::make('Detalle del turno')
                            ->schema([
                                TextEntry::make('date_OnCall')
                                    ->label('Fecha de guardia')
                                    ->placeholder('—')
                                    ->icon('heroicon-m-calendar-days'),
                                TextEntry::make('hrs_init')
                                    ->label('Hora inicio')
                                    ->placeholder('—')
                                    ->icon('heroicon-m-clock'),
                                TextEntry::make('hrs_end')
                                    ->label('Hora fin')
                                    ->placeholder('—')
                                    ->icon('heroicon-m-clock'),
                                TextEntry::make('status')
                                    ->label('Estado del turno')
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => filled($state) ? $state : '—')
                                    ->color(fn (?string $state): string => match (strtoupper(trim((string) ($state ?? '')))) {
                                        'DE GUARDIA' => 'success',
                                        'PROGRAMADA' => 'warning',
                                        default => 'gray',
                                    })
                                    ->icon(fn (?string $state): string => match (strtoupper(trim((string) ($state ?? '')))) {
                                        'DE GUARDIA' => 'heroicon-m-check-circle',
                                        'PROGRAMADA' => 'heroicon-m-clock',
                                        default => 'heroicon-m-question-mark-circle',
                                    }),
                            ])
                            ->columns(['default' => 1, 'sm' => 2, 'lg' => 3]),
                    ])
                    ->columnSpanFull(),

                Section::make('Auditoría')
                    ->description('Alta y última modificación en el sistema.')
                    ->icon(Heroicon::Clock)
                    ->collapsed()
                    ->schema([
                        Fieldset::make('Registro')
                            ->schema([
                                TextEntry::make('created_by')
                                    ->label('Creado por')
                                    ->placeholder('—')
                                    ->icon('heroicon-m-user'),
                                TextEntry::make('updated_by')
                                    ->label('Actualizado por')
                                    ->placeholder('—')
                                    ->icon('heroicon-m-user-circle'),
                                TextEntry::make('created_at')
                                    ->label('Fecha de creación')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('updated_at')
                                    ->label('Última actualización')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                            ])
                            ->columns(['default' => 1, 'md' => 2]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
