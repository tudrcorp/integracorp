<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\HelpdeskTeamMembersState;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<list<array<string, mixed>>|null, list<array<string, mixed>>|null>
 */
final class HelpdeskTeamMembersCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return HelpdeskTeamMembersState::normalize($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $normalized = HelpdeskTeamMembersState::normalize($value);

        if ($normalized === []) {
            return null;
        }

        return json_encode($normalized, JSON_THROW_ON_ERROR);
    }
}
