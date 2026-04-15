<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\FormaPago;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

final class TdevFormaPagoCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?FormaPago
    {
        return FormaPago::fromStored($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof FormaPago) {
            return $value->value;
        }

        return FormaPago::fromStored($value)?->value;
    }
}
