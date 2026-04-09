<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\BusinessAppointments\Schemas;

use App\Filament\Business\Resources\BusinessAppointments\BusinessAppointmentLabels;
use App\Models\BusinessAppointments;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class BusinessAppointmentsInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumen de la cita')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->description('Datos de contacto y estado de la solicitud.')
                    ->schema([
                        Grid::make()
                            ->columns(['default' => 1, 'lg' => 2])
                            ->schema([
                                TextEntry::make('legal_name')
                                    ->label('Nombre o razón social')
                                    ->weight('semibold')
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => BusinessAppointmentLabels::statusLabel($state))
                                    ->color(fn (?string $state): string => BusinessAppointmentLabels::statusColor($state))
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                TextEntry::make('phone')
                                    ->label('Teléfono')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->copyable()
                                    ->copyMessage('Copiado')
                                    ->url(fn (BusinessAppointments $record): ?string => filled($record->phone) ? 'tel:'.$record->phone : null)
                                    ->openUrlInNewTab(false)
                                    ->placeholder('—'),
                                TextEntry::make('email')
                                    ->label('Correo electrónico')
                                    ->icon(Heroicon::OutlinedEnvelope)
                                    ->copyable()
                                    ->copyMessage('Correo copiado')
                                    ->url(fn (BusinessAppointments $record): ?string => filled($record->email) ? 'mailto:'.$record->email : null)
                                    ->openUrlInNewTab(false)
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),
                Section::make('Ubicación')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->description('Referencia geográfica de la cita.')
                    ->schema([
                        Grid::make()
                            ->columns(['default' => 1, 'md' => 3])
                            ->schema([
                                TextEntry::make('country.name')
                                    ->label('País')
                                    ->icon(Heroicon::OutlinedGlobeAmericas)
                                    ->placeholder('—'),
                                TextEntry::make('state.definition')
                                    ->label('Estado')
                                    ->icon(Heroicon::OutlinedMap)
                                    ->placeholder('—'),
                                TextEntry::make('city.definition')
                                    ->label('Ciudad')
                                    ->icon(Heroicon::OutlinedBuildingOffice2)
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),
                Section::make('Auditoría')
                    ->icon(Heroicon::OutlinedClock)
                    ->description('Registro de altas y últimas modificaciones.')
                    ->collapsed()
                    ->schema([
                        Grid::make()
                            ->columns(['default' => 1, 'lg' => 2])
                            ->schema([
                                TextEntry::make('created_by')
                                    ->label('Creado por')
                                    ->placeholder('—'),
                                TextEntry::make('updated_by')
                                    ->label('Actualizado por')
                                    ->placeholder('—'),
                                TextEntry::make('created_at')
                                    ->label('Fecha de creación')
                                    ->dateTime('d/m/Y H:i')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->placeholder('—'),
                                TextEntry::make('updated_at')
                                    ->label('Última actualización')
                                    ->dateTime('d/m/Y H:i')
                                    ->icon(Heroicon::OutlinedArrowPath)
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),
                Section::make('Observaciones')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->description('Notas asociadas a esta cita.')
                    ->schema([
                        RepeatableEntry::make('businessAppointmentObservations')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Nota')->width('50%'),
                                TableColumn::make('Autor')->width('20%'),
                                TableColumn::make('Fecha')->width('30%'),
                            ])
                            ->schema([
                                TextEntry::make('observation')
                                    ->limit(200)
                                    ->tooltip(fn ($record): ?string => is_string($record->observation ?? null) ? $record->observation : null),
                                TextEntry::make('created_by')
                                    ->placeholder('—'),
                                TextEntry::make('created_at')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
