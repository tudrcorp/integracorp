<?php

declare(strict_types=1);

namespace App\Http\Requests\ApiPartners;

use App\Support\ApiPartners\FarmadocOrderFeed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros del listado de pedidos a Farmadoc. La autenticación del aliado ya la hizo el middleware.
 */
class FarmadocOrderIndexRequest extends FormRequest
{
    /** Parámetros admitidos: cualquier otro se rechaza para no aceptar filtros que no hacen nada. */
    public const ALLOWED_PARAMETERS = ['estatus', 'actualizado_desde', 'por_pagina', 'pagina'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $status = $this->query('estatus');

        if (is_string($status)) {
            $status = array_map('trim', explode(',', $status));
        }

        if (is_array($status)) {
            $this->merge([
                'estatus' => array_values(array_filter(
                    array_map(fn (mixed $value): mixed => is_string($value) ? mb_strtolower(trim($value)) : $value, $status),
                    fn (mixed $value): bool => $value !== '',
                )),
            ]);
        }

        $this->merge(['parametros_desconocidos' => array_values(array_diff(array_keys($this->query()), self::ALLOWED_PARAMETERS))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parametros_desconocidos' => ['array', 'max:0'],
            'estatus' => ['sometimes', 'array', 'max:10'],
            'estatus.*' => ['string', Rule::in(FarmadocOrderFeed::statuses())],
            'actualizado_desde' => ['sometimes', 'date'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:'.FarmadocOrderFeed::MAX_PER_PAGE],
            'pagina' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parametros_desconocidos.max' => 'Hay parámetros no admitidos. Usa solo: '.implode(', ', self::ALLOWED_PARAMETERS).'.',
            'estatus.*.in' => 'Estatus no válido. Valores admitidos: '.implode(', ', FarmadocOrderFeed::statuses()).'.',
            'actualizado_desde.date' => '«actualizado_desde» debe ser una fecha y hora válida (ISO 8601).',
            'por_pagina.max' => 'Se permiten como máximo '.FarmadocOrderFeed::MAX_PER_PAGE.' registros por página.',
            'por_pagina.integer' => '«por_pagina» debe ser un número entero.',
            'pagina.integer' => '«pagina» debe ser un número entero.',
        ];
    }

    /**
     * @return array{estatus?: list<string>, actualizado_desde?: string, por_pagina?: int|string, pagina?: int|string}
     */
    public function filters(): array
    {
        $validated = $this->validated();
        unset($validated['parametros_desconocidos']);

        return $validated;
    }
}
