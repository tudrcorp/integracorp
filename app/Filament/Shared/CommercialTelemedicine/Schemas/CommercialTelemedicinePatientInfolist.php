<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialTelemedicine\Schemas;

use App\Models\TelemedicinePatient;
use App\Support\FilamentDateDisplay;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

final class CommercialTelemedicinePatientInfolist
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const INNER = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Ficha del paciente')
                    ->description('Identidad y contacto. Esta vista es solo consulta.')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->extraAttributes(['class' => self::SECTION_CARD])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->extraAttributes(['class' => self::INNER])
                            ->schema([
                                TextEntry::make('full_name')
                                    ->label('Nombre completo')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->weight('bold')
                                    ->size(TextSize::Large)
                                    ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : null)
                                    ->placeholder('—'),
                                TextEntry::make('nro_identificacion')
                                    ->label('Identificación')
                                    ->icon(Heroicon::OutlinedIdentification)
                                    ->prefix('V-')
                                    ->badge()
                                    ->color('success')
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('birth_date')
                                    ->label('Fecha de nacimiento')
                                    ->icon(Heroicon::OutlinedCalendar)
                                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                                    ->placeholder('—'),
                                TextEntry::make('age')
                                    ->label('Edad')
                                    ->icon(Heroicon::OutlinedClock)
                                    ->suffix(' años')
                                    ->placeholder('—'),
                                TextEntry::make('sex')
                                    ->label('Sexo')
                                    ->badge()
                                    ->color('info')
                                    ->placeholder('—'),
                                TextEntry::make('phone')
                                    ->label('Teléfono')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('email')
                                    ->label('Correo')
                                    ->icon(Heroicon::OutlinedEnvelope)
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('address')
                                    ->label('Dirección')
                                    ->icon(Heroicon::OutlinedMapPin)
                                    ->columnSpanFull()
                                    ->wrap()
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),
                Section::make('Afiliación y plan')
                    ->description('Datos comerciales del afiliado vinculado a este paciente.')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->extraAttributes(['class' => self::SECTION_CARD])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->extraAttributes(['class' => self::INNER])
                            ->schema([
                                TextEntry::make('code_affiliation')
                                    ->label('Código de afiliación')
                                    ->badge()
                                    ->color('success')
                                    ->placeholder('—'),
                                TextEntry::make('type_affiliation')
                                    ->label('Tipo')
                                    ->badge()
                                    ->color('info')
                                    ->placeholder('—'),
                                TextEntry::make('status_affiliation')
                                    ->label('Estatus')
                                    ->badge()
                                    ->color(fn (?string $state): string => match (mb_strtoupper((string) $state)) {
                                        'ACTIVO', 'ACTIVA' => 'success',
                                        'SUSPENDIDO', 'SUSPENDIDA' => 'warning',
                                        default => 'gray',
                                    })
                                    ->placeholder('—'),
                                TextEntry::make('plan.description')
                                    ->label('Plan')
                                    ->placeholder('—'),
                                TextEntry::make('name_corporate')
                                    ->label('Empresa / corporativo')
                                    ->placeholder('—')
                                    ->visible(fn (TelemedicinePatient $record): bool => filled($record->name_corporate)),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
