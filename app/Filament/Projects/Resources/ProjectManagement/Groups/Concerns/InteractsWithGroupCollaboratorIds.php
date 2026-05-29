<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Groups\Concerns;

trait InteractsWithGroupCollaboratorIds
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeGroupCollaboratorIds(array $data): array
    {
        $data['collaborator_ids'] = collect($data['collaborator_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        return $data;
    }
}
