<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Projects\Concerns;

use App\Models\ProjectManagement\Project;
use App\Models\ProjectManagement\ProjectScrumRole;

trait InteractsWithProjectScrumRoles
{
    /**
     * @var array{product_owner_id: int|null, scrum_master_id: int|null}|null
     */
    private ?array $scrumRolePayload = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->extractScrumRoleData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->extractScrumRoleData($data);
    }

    protected function afterCreate(): void
    {
        $this->syncScrumRoles();
    }

    protected function afterSave(): void
    {
        $this->syncScrumRoles();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function extractScrumRoleData(array $data): array
    {
        $this->scrumRolePayload = [
            'product_owner_id' => filled($data['product_owner_id'] ?? null) ? (int) $data['product_owner_id'] : null,
            'scrum_master_id' => filled($data['scrum_master_id'] ?? null) ? (int) $data['scrum_master_id'] : null,
        ];

        unset($data['product_owner_id'], $data['scrum_master_id']);

        return $data;
    }

    private function syncScrumRoles(): void
    {
        /** @var Project $record */
        $record = $this->getRecord();
        $payload = $this->scrumRolePayload ?? [
            'product_owner_id' => null,
            'scrum_master_id' => null,
        ];

        if ($payload['product_owner_id'] === null && $payload['scrum_master_id'] === null) {
            ProjectScrumRole::query()->where('project_id', $record->getKey())->delete();

            return;
        }

        ProjectScrumRole::query()->updateOrCreate(
            ['project_id' => $record->getKey()],
            $payload,
        );
    }
}
