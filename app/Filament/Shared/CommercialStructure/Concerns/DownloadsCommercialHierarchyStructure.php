<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialStructure\Concerns;

use App\Filament\Shared\CommercialStructure\Actions\DownloadHierarchyStructureAction;
use App\Models\Agency;
use App\Models\Agent;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;

trait DownloadsCommercialHierarchyStructure
{
    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->downloadHierarchyStructureAction(),
        ];
    }

    protected function downloadHierarchyStructureAction(): Action
    {
        if ($this->hierarchyExportSubjectIsAgent()) {
            return DownloadHierarchyStructureAction::forAgent(
                fn (): ?Agent => $this->resolveHierarchyAgentForExport(),
            );
        }

        return DownloadHierarchyStructureAction::forAgency(
            fn (): ?Agency => $this->resolveHierarchyAgencyForExport(),
        );
    }

    protected function hierarchyExportSubjectIsAgent(): bool
    {
        return false;
    }

    protected function resolveHierarchyAgencyForExport(): ?Agency
    {
        $agencyCode = trim((string) (Auth::user()?->code_agency ?? ''));

        if ($agencyCode === '') {
            return null;
        }

        return Agency::query()
            ->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper($agencyCode)])
            ->first();
    }

    protected function resolveHierarchyAgentForExport(): ?Agent
    {
        $agentId = (int) (Auth::user()?->agent_id ?? 0);

        if ($agentId <= 0) {
            return null;
        }

        return Agent::query()->find($agentId);
    }
}
