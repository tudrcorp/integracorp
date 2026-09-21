<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialStructure\Actions;

use App\Models\Agency;
use App\Models\Agent;
use App\Support\CommercialStructure\CommercialHierarchyStructureExportService;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class DownloadHierarchyStructureAction
{
    /**
     * @param  Agency|(callable(): ?Agency)|null  $agency
     */
    public static function forAgency(Agency|callable|null $agency = null, string $name = 'downloadHierarchyStructure'): Action
    {
        return Action::make($name)
            ->label('Descargar estructura (Excel)')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('success')
            ->tooltip('Descarga un Excel con agencias y agentes bajo esta estructura comercial.')
            ->action(function () use ($agency): BinaryFileResponse {
                $resolved = self::resolveAgency($agency);

                if (! $resolved instanceof Agency) {
                    abort(404, 'No se encontró la agencia para exportar la estructura.');
                }

                return CommercialHierarchyStructureExportService::toXlsxForAgency($resolved);
            });
    }

    /**
     * @param  Agent|(callable(): ?Agent)|null  $agent
     */
    public static function forAgent(Agent|callable|null $agent = null, string $name = 'downloadHierarchyStructure'): Action
    {
        return Action::make($name)
            ->label('Descargar estructura (Excel)')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('success')
            ->tooltip('Descarga un Excel con el agente y los integrantes bajo su estructura.')
            ->action(function () use ($agent): BinaryFileResponse {
                $resolved = self::resolveAgent($agent);

                if (! $resolved instanceof Agent) {
                    abort(404, 'No se encontró el agente para exportar la estructura.');
                }

                return CommercialHierarchyStructureExportService::toXlsxForAgent($resolved);
            });
    }

    /**
     * @param  Agency|(callable(): ?Agency)|null  $agency
     */
    private static function resolveAgency(Agency|callable|null $agency): ?Agency
    {
        if ($agency instanceof Agency) {
            return $agency;
        }

        if (is_callable($agency)) {
            $resolved = $agency();

            return $resolved instanceof Agency ? $resolved : null;
        }

        return null;
    }

    /**
     * @param  Agent|(callable(): ?Agent)|null  $agent
     */
    private static function resolveAgent(Agent|callable|null $agent): ?Agent
    {
        if ($agent instanceof Agent) {
            return $agent;
        }

        if (is_callable($agent)) {
            $resolved = $agent();

            return $resolved instanceof Agent ? $resolved : null;
        }

        return null;
    }
}
