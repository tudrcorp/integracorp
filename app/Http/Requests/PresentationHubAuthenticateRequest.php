<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PresentationHubAuthenticateRequest extends FormRequest
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
            'method' => ['required', 'string', Rule::in(['cedula', 'telefono'])],
            'credential' => ['required', 'string', 'min:6', 'max:32'],
            'intended' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'method.required' => 'Selecciona cédula o teléfono.',
            'method.in' => 'El método de acceso no es válido.',
            'credential.required' => 'Ingresa tu cédula o teléfono.',
            'credential.min' => 'La credencial es demasiado corta.',
        ];
    }
}
