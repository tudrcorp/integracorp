<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialTelemedicine\Schemas;

use App\Support\Telemedicine\TelemedicinePriorityFilamentBadge;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

final class CommercialTelemedicineCaseInfolist
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const INNER = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Estatus del caso')
                    ->description('Información operativa del caso. Solo consulta; no incluye historia clínica.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->extraAttributes(['class' => self::SECTION_CARD])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->extraAttributes(['class' => self::INNER])
                            ->schema([
                                TextEntry::make('code')
                                    ->label('N.º de caso')
                                    ->badge()
                                    ->color('primary')
                                    ->size(TextSize::Large)
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('status')
                                    ->label('Estatus')
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        'EN SEGUIMIENTO' => 'warning',
                                        'CONSULTA INICIAL' => 'info',
                                        'ASIGNADO' => 'primary',
                                        default => 'gray',
                                    })
                                    ->placeholder('—'),
                                TextEntry::make('priority.name')
                                    ->label('Prioridad')
                                    ->badge()
                                    ->color(fn (?string $state): string => filled($state)
                                        ? TelemedicinePriorityFilamentBadge::color($state)
                                        : 'gray')
                                    ->placeholder('—'),
                                TextEntry::make('assigned_by')
                                    ->label('Asignado por')
                                    ->placeholder('—'),
                                TextEntry::make('created_at')
                                    ->label('Apertura')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('updated_at')
                                    ->label('Última actualización')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),
                Section::make('Paciente')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->extraAttributes(['class' => self::SECTION_CARD])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->extraAttributes(['class' => self::INNER])
                            ->schema([
                                TextEntry::make('patient_name')
                                    ->label('Nombre')
                                    ->weight('bold')
                                    ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : null)
                                    ->placeholder('—'),
                                TextEntry::make('telemedicinePatient.nro_identificacion')
                                    ->label('Identificación')
                                    ->prefix('V-')
                                    ->badge()
                                    ->color('success')
                                    ->placeholder('—'),
                                TextEntry::make('patient_age')
                                    ->label('Edad')
                                    ->suffix(' años')
                                    ->placeholder('—'),
                                TextEntry::make('patient_sex')
                                    ->label('Sexo')
                                    ->badge()
                                    ->color('info')
                                    ->placeholder('—'),
                                TextEntry::make('patient_phone')
                                    ->label('Teléfono')
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('telemedicineDoctor.full_name')
                                    ->label('Médico asignado')
                                    ->prefix('Dr(a). ')
                                    ->placeholder('Sin médico asignado'),
                                TextEntry::make('telemedicinePatient.code_affiliation')
                                    ->label('Afiliación')
                                    ->badge()
                                    ->color('success')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
