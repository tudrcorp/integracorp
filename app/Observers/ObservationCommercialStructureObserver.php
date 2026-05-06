<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\ObservationCommercialStructure;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ObservationCommercialStructureObserver
{
    public function saving(ObservationCommercialStructure $observationCommercialStructure): void
    {
        if (! $observationCommercialStructure->exists) {
            return;
        }

        if (! $observationCommercialStructure->isDirty('observation')) {
            return;
        }

        $observationCommercialStructure->updated_by = Auth::user()?->name;
    }

    public function created(ObservationCommercialStructure $observationCommercialStructure): void
    {
        if ($this->shouldSkipAudit()) {
            return;
        }

        if (! $this->isCommercialAgencyOrAgent($observationCommercialStructure)) {
            return;
        }

        $this->logCreated($observationCommercialStructure);
    }

    public function updated(ObservationCommercialStructure $observationCommercialStructure): void
    {
        if ($this->shouldSkipAudit()) {
            return;
        }

        if (! $observationCommercialStructure->wasChanged('observation')) {
            return;
        }

        if (! $this->isCommercialAgencyOrAgent($observationCommercialStructure)) {
            return;
        }

        $this->logUpdated($observationCommercialStructure);
    }

    private function shouldSkipAudit(): bool
    {
        if (app()->runningInConsole()) {
            return true;
        }

        return ! Auth::check();
    }

    private function isCommercialAgencyOrAgent(ObservationCommercialStructure $model): bool
    {
        return filled($model->agency_id) || filled($model->agent_id);
    }

    private function logCreated(ObservationCommercialStructure $model): void
    {
        $preview = Str::limit((string) $model->observation, 120);

        if (filled($model->agency_id)) {
            $agency = Agency::query()->find($model->agency_id);

            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_COMMERCIAL_OBSERVATION_CREATED', 'business.agencies.edit', [
                'observation_commercial_structure_id' => $model->id,
                'agency_id' => $model->agency_id,
                'agency_code' => $agency?->code,
                'registered_by' => $model->created_by,
                'observation_preview' => $preview,
            ]);

            return;
        }

        if (filled($model->agent_id)) {
            $agent = Agent::query()->find($model->agent_id);

            SecurityAudit::log('AUDIT_BUSINESS_AGENT_COMMERCIAL_OBSERVATION_CREATED', 'business.agents.edit', [
                'observation_commercial_structure_id' => $model->id,
                'agent_id' => $model->agent_id,
                'agent_code' => $agent?->code_agent,
                'registered_by' => $model->created_by,
                'observation_preview' => $preview,
            ]);
        }
    }

    private function logUpdated(ObservationCommercialStructure $model): void
    {
        $preview = Str::limit((string) $model->observation, 120);

        if (filled($model->agency_id)) {
            $agency = Agency::query()->find($model->agency_id);

            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_COMMERCIAL_OBSERVATION_UPDATED', 'business.agencies.edit', [
                'observation_commercial_structure_id' => $model->id,
                'agency_id' => $model->agency_id,
                'agency_code' => $agency?->code,
                'edited_by' => $model->updated_by,
                'observation_preview' => $preview,
            ]);

            return;
        }

        if (filled($model->agent_id)) {
            $agent = Agent::query()->find($model->agent_id);

            SecurityAudit::log('AUDIT_BUSINESS_AGENT_COMMERCIAL_OBSERVATION_UPDATED', 'business.agents.edit', [
                'observation_commercial_structure_id' => $model->id,
                'agent_id' => $model->agent_id,
                'agent_code' => $agent?->code_agent,
                'edited_by' => $model->updated_by,
                'observation_preview' => $preview,
            ]);
        }
    }
}
