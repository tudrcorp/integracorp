<?php

namespace App\Filament\Marketing\Resources\ContactLists\Schemas;

use App\Models\ContactList;
use Filament\Infolists\Components\ColorEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactListInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del contacto')
                    ->description('Información principal del contacto. Puede copiar correo, teléfono o color haciendo clic en el valor.')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Fieldset::make()
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('full_name')
                                            ->label('Nombre completo')
                                            ->weight('semibold')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                        TextEntry::make('email')
                                            ->label('Correo electrónico')
                                            ->placeholder('-')
                                            ->copyable()
                                            ->copyMessage('Correo copiado')
                                            ->columnSpan(1),
                                        TextEntry::make('phone')
                                            ->label('Teléfono')
                                            ->placeholder('-')
                                            ->copyable()
                                            ->copyMessage('Teléfono copiado')
                                            ->columnSpan(1),
                                        TextEntry::make('group')
                                            ->label('Grupo')
                                            ->badge()
                                            ->color(fn ($record): string => $record->group_color ?? 'gray')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                        ColorEntry::make('group_color')
                                            ->label('Color del grupo')
                                            ->placeholder('-')
                                            ->copyable()
                                            ->copyMessage('Color copiado al portapapeles')
                                            ->copyMessageDuration(1500)
                                            ->helperText('Clic en la muestra de color para copiar el valor (ej. código hex) al portapapeles.')
                                            ->columnSpan(1),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Datos del propietario')
                    ->description('Persona responsable o asignada del contacto')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Fieldset::make()
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('owner__full_name')
                                            ->label('Nombre del propietario')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                        TextEntry::make('owner_phone')
                                            ->label('Teléfono del propietario')
                                            ->placeholder('-')
                                            ->copyable()
                                            ->columnSpan(1),
                                        TextEntry::make('owner_email')
                                            ->label('Correo del propietario')
                                            ->placeholder('-')
                                            ->copyable()
                                            ->copyMessage('Correo copiado')
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Estado y auditoría')
                    ->description('Estado del contacto y registro de cambios')
                    ->icon('heroicon-o-flag')
                    ->schema([
                        Fieldset::make()
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('status')
                                            ->label('Estado')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'activo' => 'success',
                                                'inactivo' => 'gray',
                                                default => 'gray',
                                            })
                                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                                'activo' => 'Activo',
                                                'inactivo' => 'Inactivo',
                                                default => $state,
                                            })
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                        TextEntry::make('created_by')
                                            ->label('Creado por')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                        TextEntry::make('updated_by')
                                            ->label('Actualizado por')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                        TextEntry::make('created_at')
                                            ->label('Fecha de creación')
                                            ->dateTime('d/m/Y H:i')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                        TextEntry::make('updated_at')
                                            ->label('Última actualización')
                                            ->dateTime('d/m/Y H:i')
                                            ->placeholder('-')
                                            ->columnSpan(1),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
