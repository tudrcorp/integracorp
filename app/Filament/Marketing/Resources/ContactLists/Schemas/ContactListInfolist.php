<?php

declare(strict_types=1);

namespace App\Filament\Marketing\Resources\ContactLists\Schemas;

use App\Models\ContactList;
use Filament\Infolists\Components\ColorEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ContactListInfolist
{
    /**
     * Vista experimental «Liquid Glass» (vidrio iOS): mismas clases CSS que Capemiac en theme.css.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Datos del contacto')
                    ->description('Información principal. Puede copiar correo, teléfono o color desde el valor.')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->iconColor('primary')
                    ->extraAttributes([
                        'class' => 'fi-capemiac-liquid-glass-section',
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->extraAttributes([
                                'class' => 'fi-capemiac-liquid-glass-inset',
                            ])
                            ->schema([
                                TextEntry::make('full_name')
                                    ->label('Nombre completo')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->weight('bold')
                                    ->size('lg')
                                    ->color('gray')
                                    ->placeholder('—')
                                    ->wrap()
                                    ->columnSpan(['md' => 2]),
                                TextEntry::make('email')
                                    ->label('Correo electrónico')
                                    ->icon(Heroicon::OutlinedEnvelope)
                                    ->iconColor('gray')
                                    ->placeholder('—')
                                    ->copyable()
                                    ->copyMessage('Correo copiado'),
                                TextEntry::make('phone')
                                    ->label('Teléfono')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->iconColor('gray')
                                    ->placeholder('—')
                                    ->copyable()
                                    ->copyMessage('Teléfono copiado'),
                                TextEntry::make('group')
                                    ->label('Grupo')
                                    ->icon(Heroicon::OutlinedSquares2x2)
                                    ->iconColor('gray')
                                    ->badge()
                                    ->color(fn (ContactList $record): string => self::groupBadgeColor($record))
                                    ->placeholder('—'),
                                ColorEntry::make('group_color')
                                    ->label('Color del grupo')
                                    ->placeholder('—')
                                    ->copyable()
                                    ->copyMessage('Color copiado')
                                    ->copyMessageDuration(1500)
                                    ->helperText('Clic en la muestra para copiar el valor (p. ej. hex) al portapapeles.')
                                    ->columnSpan(['md' => 2]),
                            ]),
                    ]),
                Section::make('Datos del propietario')
                    ->description('Persona responsable o asignada al contacto.')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->iconColor('gray')
                    ->extraAttributes([
                        'class' => 'fi-capemiac-liquid-glass-section',
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->extraAttributes([
                                'class' => 'fi-capemiac-liquid-glass-inset',
                            ])
                            ->schema([
                                TextEntry::make('owner__full_name')
                                    ->label('Nombre del propietario')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->iconColor('gray')
                                    ->placeholder('—')
                                    ->wrap()
                                    ->columnSpan(['md' => 2]),
                                TextEntry::make('owner_phone')
                                    ->label('Teléfono del propietario')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->iconColor('gray')
                                    ->placeholder('—')
                                    ->copyable()
                                    ->copyMessage('Teléfono copiado'),
                                TextEntry::make('owner_email')
                                    ->label('Correo del propietario')
                                    ->icon(Heroicon::OutlinedEnvelope)
                                    ->iconColor('gray')
                                    ->placeholder('—')
                                    ->copyable()
                                    ->copyMessage('Correo copiado')
                                    ->columnSpan(['md' => 2]),
                            ]),
                    ]),
                Section::make('Estado y auditoría')
                    ->description('Estado del registro y trazas de creación o edición.')
                    ->icon(Heroicon::OutlinedFlag)
                    ->iconColor('gray')
                    ->extraAttributes([
                        'class' => 'fi-capemiac-liquid-glass-section',
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->extraAttributes([
                                'class' => 'fi-capemiac-liquid-glass-inset',
                            ])
                            ->schema([
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->icon(Heroicon::OutlinedCheckCircle)
                                    ->iconColor('gray')
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        'activo' => 'success',
                                        'inactivo' => 'gray',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                                        'activo' => 'Activo',
                                        'inactivo' => 'Inactivo',
                                        default => $state ? ucfirst($state) : '—',
                                    })
                                    ->placeholder('—'),
                                TextEntry::make('created_by')
                                    ->label('Creado por')
                                    ->icon(Heroicon::OutlinedUserPlus)
                                    ->iconColor('gray')
                                    ->placeholder('—'),
                                TextEntry::make('updated_by')
                                    ->label('Actualizado por')
                                    ->icon(Heroicon::OutlinedArrowPath)
                                    ->iconColor('gray')
                                    ->placeholder('—'),
                                TextEntry::make('created_at')
                                    ->label('Fecha de creación')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->iconColor('gray')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—')
                                    ->helperText(fn (ContactList $record): ?string => $record->created_at?->diffForHumans()),
                                TextEntry::make('updated_at')
                                    ->label('Última actualización')
                                    ->icon(Heroicon::OutlinedClock)
                                    ->iconColor('gray')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—')
                                    ->helperText(fn (ContactList $record): ?string => $record->updated_at?->diffForHumans()),
                            ]),
                    ]),
            ]);
    }

    private static function groupBadgeColor(ContactList $record): string
    {
        $color = $record->group_color;

        if (! is_string($color) || $color === '') {
            return 'gray';
        }

        if (str_starts_with($color, '#')) {
            return 'gray';
        }

        return $color;
    }
}
