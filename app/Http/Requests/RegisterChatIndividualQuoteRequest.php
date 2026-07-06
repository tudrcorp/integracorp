<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterChatIndividualQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'agent_name' => ['required', 'string', 'max:255'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.plan_id' => ['required', 'integer', 'in:1,2,3'],
            'entries.*.age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'entries.*.age_range_id' => ['required', 'integer', 'min:1'],
            'entries.*.total_persons' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'El nombre y apellido del solicitante es obligatorio.',
            'agent_name.required' => 'El nombre del agente es obligatorio.',
            'entries.required' => 'Debes indicar al menos un plan a cotizar.',
            'entries.*.plan_id.in' => 'El plan debe ser 1 (Inicial), 2 (Ideal) o 3 (Especial).',
            'entries.*.age_range_id.required' => 'No se pudo determinar el rango de edad.',
            'entries.*.total_persons.min' => 'El número de personas debe ser al menos 1.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $validator->errors()->first() ?? 'Los datos enviados no son válidos.',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
