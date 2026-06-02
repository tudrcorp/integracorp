<?php

declare(strict_types=1);

namespace App\Support;

final class HelpdeskTeamMembersState
{
    /**
     * Normaliza `team_members` para RepeatableEntry / columnas (JSON string, array o null).
     *
     * @return list<array<string, mixed>>
     */
    public static function normalize(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            return [];
        }

        $members = [];

        foreach ($value as $member) {
            if (! is_array($member)) {
                continue;
            }

            $members[] = $member;
        }

        return $members;
    }
}
