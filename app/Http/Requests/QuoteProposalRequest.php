<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cotización rápida de la Propuesta Económica.
 *
 * Los afiliados llegan persona a persona porque es una vista previa del
 * formulario; la cotización corporativa, con miles de asegurados, se arma por
 * rangos de edad y no pasa por aquí.
 */
class QuoteProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'titular' => ['required', 'string', 'max:255'],
            'afiliados' => ['required', 'array', 'min:1', 'max:20'],
            'afiliados.*.nombre' => ['nullable', 'string', 'max:255'],
            'afiliados.*.edad' => ['required', 'integer', 'min:0', 'max:120'],
            'planes' => ['nullable', 'array'],
            'planes.*' => [Rule::in(['inicial', 'ideal', 'especial', 'todos'])],
            'cobertura' => ['nullable', 'integer', 'min:0'],
            'code' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'titular.required' => 'Indique el nombre del titular.',
            'afiliados.required' => 'Agregue al menos un afiliado.',
            'afiliados.min' => 'Agregue al menos un afiliado.',
            'afiliados.max' => 'La vista previa admite hasta 20 afiliados; para una población mayor use la cotización corporativa.',
            'afiliados.*.edad.required' => 'Cada afiliado necesita su edad.',
            'afiliados.*.edad.integer' => 'La edad debe ser un número entero.',
            'afiliados.*.edad.min' => 'La edad no puede ser negativa.',
            'afiliados.*.edad.max' => 'La edad máxima admitida es 120 años.',
            'planes.*.in' => 'Los planes válidos son: inicial, ideal, especial o todos.',
        ];
    }

    /**
     * @return list<array{nombre?: string, edad: int}>
     */
    public function afiliados(): array
    {
        $afiliados = [];

        /** @var array<int, array{nombre?: string|null, edad: int|string}> $input */
        $input = $this->validated('afiliados', []);

        foreach ($input as $afiliado) {
            $nombre = trim((string) ($afiliado['nombre'] ?? ''));

            $afiliados[] = $nombre === ''
                ? ['edad' => (int) $afiliado['edad']]
                : ['nombre' => $nombre, 'edad' => (int) $afiliado['edad']];
        }

        return $afiliados;
    }

    /**
     * @return string|list<string>
     */
    public function planes(): string|array
    {
        /** @var list<string>|null $planes */
        $planes = $this->validated('planes');

        if ($planes === null || $planes === [] || in_array('todos', $planes, true)) {
            return 'todos';
        }

        return $planes;
    }
}
