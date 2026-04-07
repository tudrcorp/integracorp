<?php

declare(strict_types=1);

namespace App\Filament\Marketing\Resources\InfoFrees\Schemas;

use App\Models\InfoFree;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class InfoFreeInfolist
{
    /**
     * Vista experimental «Liquid Glass» (vidrio iOS): mismas clases CSS que Capemiac en theme.css.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Contacto')
                    ->description('Datos principales del lead o contacto externo.')
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
                                TextEntry::make('fullName')
                                    ->label('Nombre completo')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->weight('bold')
                                    ->size('lg')
                                    ->color('gray')
                                    ->placeholder('—'),
                                TextEntry::make('sex')
                                    ->label('Sexo')
                                    ->icon(Heroicon::OutlinedIdentification)
                                    ->badge()
                                    ->color(fn (?string $state): string => self::sexBadgeColor($state))
                                    ->formatStateUsing(fn (?string $state): string => $state ? mb_strtoupper($state) : '—')
                                    ->placeholder('—'),
                                TextEntry::make('email')
                                    ->label('Correo')
                                    ->icon(Heroicon::OutlinedEnvelope)
                                    ->iconColor('gray')
                                    ->copyable()
                                    ->copyMessage('Correo copiado')
                                    ->placeholder('—')
                                    ->columnSpan(['md' => 2]),
                                TextEntry::make('phone')
                                    ->label('Teléfono')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->iconColor('gray')
                                    ->copyable()
                                    ->copyMessage('Teléfono copiado')
                                    ->placeholder('—'),
                            ]),
                    ]),
                Section::make('Ubicación')
                    ->description('Dirección y referencia geográfica.')
                    ->icon(Heroicon::OutlinedMapPin)
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
                                TextEntry::make('address')
                                    ->label('Dirección')
                                    ->icon(Heroicon::OutlinedHome)
                                    ->columnSpanFull()
                                    ->wrap()
                                    ->placeholder('—'),
                                TextEntry::make('city')
                                    ->label('Ciudad')
                                    ->icon(Heroicon::OutlinedBuildingOffice2)
                                    ->placeholder('—'),
                                TextEntry::make('region')
                                    ->label('Región')
                                    ->icon(Heroicon::OutlinedGlobeAmericas)
                                    ->placeholder('—'),
                                TextEntry::make('state')
                                    ->label('Estado / provincia')
                                    ->icon(Heroicon::OutlinedMap)
                                    ->placeholder('—'),
                                TextEntry::make('country')
                                    ->label('País')
                                    ->icon(Heroicon::OutlinedFlag)
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('—'),
                            ]),
                    ]),
                Section::make('Registro en sistema')
                    ->description('Auditoría de creación y última modificación.')
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
                                TextEntry::make('created_at')
                                    ->label('Creado')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—')
                                    ->helperText(fn (InfoFree $record): ?string => $record->created_at?->diffForHumans()),
                                TextEntry::make('updated_at')
                                    ->label('Última edición')
                                    ->icon(Heroicon::OutlinedArrowPath)
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—')
                                    ->helperText(fn (InfoFree $record): ?string => $record->updated_at?->diffForHumans()),
                            ]),
                    ]),
            ]);
    }

    private static function sexBadgeColor(?string $state): string
    {
        $normalized = mb_strtoupper((string) $state);

        return match (true) {
            in_array($normalized, ['M', 'MASCULINO', 'H', 'HOMBRE', 'MALE'], true) => 'info',
            in_array($normalized, ['F', 'FEMENINO', 'MUJER', 'FEMALE'], true) => 'warning',
            $normalized === '' => 'gray',
            default => 'gray',
        };
    }
}
