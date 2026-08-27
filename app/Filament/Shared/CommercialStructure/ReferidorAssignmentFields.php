<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialStructure;

use App\Models\Agency;
use App\Models\Agent;
use App\Support\CommercialStructure\ReferidorAccess;
use App\Support\CommercialStructure\ReferidorAssignmentService;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

final class ReferidorAssignmentFields
{
    public static function section(?string $sectionClass = null): Section
    {
        $section = Section::make('Red de referidor')
            ->description('Seleccione las agencias generales y los agentes o subagentes que este referidor cubre. Cada uno queda con el ID de este referidor.')
            ->icon('heroicon-o-user-group')
            ->schema([
                self::generalAgenciesSelect(),
                self::agentsSelect(),
            ])
            ->columns(1)
            ->columnSpanFull()
            ->visible(fn (Get $get): bool => self::isVisible($get));

        if ($sectionClass !== null && $sectionClass !== '') {
            $section->extraAttributes(['class' => $sectionClass]);
        }

        return $section;
    }

    public static function generalAgenciesSelect(): Select
    {
        return Select::make(ReferidorAssignmentService::GENERAL_AGENCY_IDS_FIELD)
            ->label('Agencias generales')
            ->helperText('Busque por código, razón social o RIF. Solo aparecen agencias generales libres o ya asignadas a este referidor.')
            ->multiple()
            ->searchable()
            ->native(false)
            ->dehydrated()
            ->default([])
            ->getSearchResultsUsing(
                function (string $search, mixed $record): array {
                    return ReferidorAssignmentService::searchGeneralAgencies(
                        $search,
                        self::referrerFromRecord($record),
                    );
                }
            )
            ->getOptionLabelsUsing(
                fn (array $values): array => ReferidorAssignmentService::generalAgencyLabels($values)
            );
    }

    public static function agentsSelect(): Select
    {
        return Select::make(ReferidorAssignmentService::AGENT_IDS_FIELD)
            ->label('Agentes y subagentes')
            ->helperText('Busque por nombre, cédula, correo o código. Solo aparecen agentes o subagentes libres o ya asignados a este referidor.')
            ->multiple()
            ->searchable()
            ->native(false)
            ->dehydrated()
            ->default([])
            ->getSearchResultsUsing(
                function (string $search, mixed $record): array {
                    return ReferidorAssignmentService::searchAgents(
                        $search,
                        self::referrerFromRecord($record),
                    );
                }
            )
            ->getOptionLabelsUsing(
                fn (array $values): array => ReferidorAssignmentService::agentLabels($values)
            );
    }

    public static function isVisible(Get $get): bool
    {
        return (bool) $get('is_referidor') && ReferidorAccess::userCanManage();
    }

    private static function referrerFromRecord(mixed $record): Agency|Agent|null
    {
        if (($record instanceof Agency || $record instanceof Agent) && $record->exists) {
            return $record;
        }

        return null;
    }
}
