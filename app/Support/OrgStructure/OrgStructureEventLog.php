<?php

declare(strict_types=1);

namespace App\Support\OrgStructure;

use App\Models\OrgStructureEvent;
use App\Models\User;

/**
 * Escribe la bitácora de la estructura organizacional: quién hizo qué, cuándo y con
 * qué antes y después.
 */
final class OrgStructureEventLog
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function record(
        int $versionId,
        string $action,
        string $summary,
        ?User $actor,
        ?string $positionKey = null,
        ?array $before = null,
        ?array $after = null,
    ): void {
        OrgStructureEvent::query()->create([
            'version_id' => $versionId,
            'action' => $action,
            'position_key' => $positionKey,
            'summary' => mb_substr($summary, 0, 255),
            'before' => $before,
            'after' => $after,
            'user_id' => $actor?->getKey(),
            'user_name' => $actor?->name ?? 'Sistema',
        ]);
    }
}
