<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\StatusComision;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

final class TdevStatusComisionCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?StatusComision
    {
        return StatusComision::fromStored($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof StatusComision) {
            return $value->value;
        }

        return StatusComision::fromStored($value)?->value;
    }
}
