<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\StatusPago;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

final class TdevStatusPagoCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?StatusPago
    {
        return StatusPago::fromStored($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof StatusPago) {
            return $value->value;
        }

        return StatusPago::fromStored($value)?->value;
    }
}
