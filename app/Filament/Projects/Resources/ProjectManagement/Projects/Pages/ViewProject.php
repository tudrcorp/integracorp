<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Projects\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Projects\ProjectResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        /** @var \App\Models\ProjectManagement\Project $record */
        $record = parent::resolveRecord($key);

        return $record->load([
            'scrumRoles.productOwner:id,fullName',
            'scrumRoles.scrumMaster:id,fullName',
            'activeSprint:id,project_id,name,status',
            'subprojects' => fn ($query) => $query
                ->withCount([
                    'activities',
                    'activities as activities_done_count' => fn ($activitiesQuery) => $activitiesQuery->where('status', 'done'),
                    'activities as activities_open_count' => fn ($activitiesQuery) => $activitiesQuery->where('status', '!=', 'done'),
                ])
                ->orderBy('name'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
