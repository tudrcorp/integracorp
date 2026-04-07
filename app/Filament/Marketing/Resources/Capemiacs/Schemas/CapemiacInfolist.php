<?php

declare(strict_types=1);

namespace App\Filament\Marketing\Resources\Capemiacs\Schemas;

use App\Models\Capemiac;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CapemiacInfolist
{
    /**
     * Vista experimental «Liquid Glass» (vidrio iOS): clases CSS dedicadas en theme.css.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Cliente')
                    ->description('Datos del contacto Capemiac y clasificación.')
                    ->icon(Heroicon::OutlinedBuildingLibrary)
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
                                TextEntry::make('cliente')
                                    ->label('Nombre o razón social')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->weight('bold')
                                    ->size('lg')
                                    ->color('gray')
                                    ->placeholder('—')
                                    ->columnSpan(['md' => 2])
                                    ->wrap(),
                                TextEntry::make('segmento')
                                    ->label('Segmento')
                                    ->icon(Heroicon::OutlinedSquares2x2)
                                    ->iconColor('gray')
                                    ->badge()
                                    ->color('gray')
                                    ->formatStateUsing(fn (?string $state): string => $state ? mb_strtoupper($state) : '—')
                                    ->placeholder('—'),
                                TextEntry::make('rif')
                                    ->label('RIF')
                                    ->icon(Heroicon::OutlinedIdentification)
                                    ->iconColor('gray')
                                    ->badge()
                                    ->color('info')
                                    ->copyable()
                                    ->copyMessage('RIF copiado')
                                    ->placeholder('—'),
                            ]),
                    ]),
                Section::make('Contacto')
                    ->description('Correo y números telefónicos registrados.')
                    ->icon(Heroicon::OutlinedPhone)
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
                                TextEntry::make('email')
                                    ->label('Correo')
                                    ->icon(Heroicon::OutlinedEnvelope)
                                    ->iconColor('gray')
                                    ->copyable()
                                    ->copyMessage('Correo copiado')
                                    ->placeholder('—')
                                    ->columnSpan(['md' => 2]),
                                TextEntry::make('telefonoUno')
                                    ->label('Teléfono principal')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->iconColor('gray')
                                    ->copyable()
                                    ->copyMessage('Teléfono copiado')
                                    ->placeholder('—'),
                                TextEntry::make('telefonoDos')
                                    ->label('Teléfono 2')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->iconColor('gray')
                                    ->copyable()
                                    ->copyMessage('Teléfono copiado')
                                    ->placeholder('—'),
                                TextEntry::make('telefonoTres')
                                    ->label('Teléfono 3')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->iconColor('gray')
                                    ->copyable()
                                    ->copyMessage('Teléfono copiado')
                                    ->placeholder('—'),
                            ]),
                    ]),
                Section::make('Registro')
                    ->description('Fecha declarada y trazas en el sistema.')
                    ->icon(Heroicon::OutlinedClock)
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
                                TextEntry::make('fecha_registro')
                                    ->label('Fecha de registro (dato)')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->iconColor('gray')
                                    ->placeholder('—')
                                    ->columnSpan(['md' => 2]),
                                TextEntry::make('created_at')
                                    ->label('Creado en sistema')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—')
                                    ->helperText(fn (Capemiac $record): ?string => $record->created_at?->diffForHumans()),
                                TextEntry::make('updated_at')
                                    ->label('Última edición')
                                    ->icon(Heroicon::OutlinedArrowPath)
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—')
                                    ->helperText(fn (Capemiac $record): ?string => $record->updated_at?->diffForHumans()),
                            ]),
                    ]),
            ]);
    }
}
