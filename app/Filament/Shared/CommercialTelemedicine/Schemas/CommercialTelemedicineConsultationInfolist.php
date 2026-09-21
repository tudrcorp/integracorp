<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialTelemedicine\Schemas;

use App\Support\Telemedicine\TelemedicineDerivedServiceBadge;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class CommercialTelemedicineConsultationInfolist
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10';

    private const INNER = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Consulta')
                    ->description('Estatus y servicios de la consulta. No se muestra historia clínica ni notas médicas.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->extraAttributes(['class' => self::SECTION_CARD])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->extraAttributes(['class' => self::INNER])
                            ->schema([
                                TextEntry::make('telemedicine_case_code')
                                    ->label('N.º de caso')
                                    ->badge()
                                    ->color('success')
                                    ->placeholder('—'),
                                TextEntry::make('status')
                                    ->label('Estatus')
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        'EN SEGUIMIENTO' => 'warning',
                                        'CONSULTA INICIAL' => 'info',
                                        'ALTA MEDICA' => 'success',
                                        default => 'gray',
                                    })
                                    ->placeholder('—'),
                                TextEntry::make('telemedicineServiceList.name')
                                    ->label('Servicio')
                                    ->badge()
                                    ->color('success')
                                    ->placeholder('—'),
                                TextEntry::make('telemedicineGeneralService.name')
                                    ->label('Servicio general')
                                    ->badge()
                                    ->color('warning')
                                    ->placeholder('—'),
                                TextEntry::make('telemedicineServiceListDrift.name')
                                    ->label('Servicio derivado')
                                    ->badge()
                                    ->color(fn (?string $state): string => TelemedicineDerivedServiceBadge::driftNameIsCritical($state) ? 'danger' : 'info')
                                    ->placeholder('—'),
                                TextEntry::make('telemedicineDoctor.full_name')
                                    ->label('Atendido por')
                                    ->prefix('Dr(a). ')
                                    ->placeholder('—'),
                                TextEntry::make('created_at')
                                    ->label('Fecha de registro')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                            ]),
                    ]),
            ]);
    }
}
