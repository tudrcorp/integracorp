<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\StatusVaucher;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

final class TdevStatusVaucherCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?StatusVaucher
    {
        return StatusVaucher::fromStored($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof StatusVaucher) {
            return $value->value;
        }

        return StatusVaucher::fromStored($value)?->value;
    }
}
