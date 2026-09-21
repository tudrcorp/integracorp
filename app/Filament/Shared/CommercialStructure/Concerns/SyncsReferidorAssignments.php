<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialStructure\Concerns;

use App\Filament\Shared\CommercialStructure\ReferidorPercentageField;
use App\Models\Agency;
use App\Models\Agent;
use App\Support\CommercialStructure\ReferidorAccess;
use App\Support\CommercialStructure\ReferidorAssignmentService;

trait SyncsReferidorAssignments
{
    /**
     * @var array{general_agency_ids: list<int>, agent_ids: list<int>}
     */
    private array $pendingReferidorAssignments = [
        'general_agency_ids' => [],
        'agent_ids' => [],
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillReferidorAssignmentState(array $data): array
    {
        $record = method_exists($this, 'getRecord') ? $this->getRecord() : null;

        if ($record instanceof Agency || $record instanceof Agent) {
            return ReferidorAssignmentService::mergeFormState($record, $data);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function captureReferidorAssignments(array $data): array
    {
        if (! ReferidorAccess::userCanManage()) {
            unset($data['referidor_percentage']);

            return ReferidorAssignmentService::strip($data);
        }

        $data = ReferidorPercentageField::normalizeFormData($data);

        $this->pendingReferidorAssignments = ReferidorAssignmentService::capture($data);

        return ReferidorAssignmentService::strip($data);
    }

    protected function persistCapturedReferidorAssignments(): void
    {
        if (! ReferidorAccess::userCanManage()) {
            return;
        }

        $record = method_exists($this, 'getRecord') ? $this->getRecord() : null;

        if (! $record instanceof Agency && ! $record instanceof Agent) {
            return;
        }

        ReferidorAssignmentService::sync($record, $this->pendingReferidorAssignments);
    }
}
