<?php

declare(strict_types=1);

namespace App\Http\Requests\ApiPartners;

use App\Support\ApiPartners\NonCoveredMedicationFeed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros del listado de medicamentos no cubiertos. La autenticación del aliado
 * ya la hizo el middleware; aquí solo se valida la entrada.
 */
class NonCoveredMedicationIndexRequest extends FormRequest
{
    /** Parámetros admitidos: cualquier otro se rechaza para no aceptar filtros que no hacen nada. */
    public const ALLOWED_PARAMETERS = ['estatus', 'desde', 'hasta', 'actualizado_desde', 'caso', 'coordinacion', 'por_pagina', 'pagina'];

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
                    array_map(fn (mixed $value): mixed => is_string($value) ? mb_strtoupper(trim($value)) : $value, $status),
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
            'estatus' => ['sometimes', 'array', 'max:4'],
            'estatus.*' => ['string', Rule::in(NonCoveredMedicationFeed::STATUSES)],
            'desde' => ['sometimes', 'date_format:Y-m-d'],
            'hasta' => array_values(array_filter([
                'sometimes',
                'date_format:Y-m-d',
                $this->filled('desde') ? 'after_or_equal:desde' : null,
            ])),
            'actualizado_desde' => ['sometimes', 'date'],
            'caso' => ['sometimes', 'string', 'max:50'],
            'coordinacion' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:'.NonCoveredMedicationFeed::MAX_PER_PAGE],
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
            'estatus.*.in' => 'Estatus no válido. Valores admitidos: '.implode(', ', NonCoveredMedicationFeed::STATUSES).'.',
            'desde.date_format' => 'La fecha «desde» debe tener el formato AAAA-MM-DD.',
            'hasta.date_format' => 'La fecha «hasta» debe tener el formato AAAA-MM-DD.',
            'hasta.after_or_equal' => 'La fecha «hasta» no puede ser anterior a «desde».',
            'actualizado_desde.date' => '«actualizado_desde» debe ser una fecha y hora válida (ISO 8601).',
            'coordinacion.integer' => '«coordinacion» debe ser un número entero.',
            'por_pagina.max' => 'Se permiten como máximo '.NonCoveredMedicationFeed::MAX_PER_PAGE.' registros por página.',
            'por_pagina.integer' => '«por_pagina» debe ser un número entero.',
            'pagina.integer' => '«pagina» debe ser un número entero.',
        ];
    }

    /**
     * @return array{estatus?: list<string>, desde?: string, hasta?: string, actualizado_desde?: string, caso?: string, coordinacion?: int|string, por_pagina?: int|string, pagina?: int|string}
     */
    public function filters(): array
    {
        $validated = $this->validated();
        unset($validated['parametros_desconocidos']);

        return $validated;
    }
}
