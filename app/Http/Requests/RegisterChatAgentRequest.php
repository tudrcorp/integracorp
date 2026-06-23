<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class RegisterChatAgentRequest extends FormRequest
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
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[0-9]+$/'],
            'owner_code' => ['nullable', 'string', 'max:100'],
            'classification' => ['nullable', 'string', Rule::in(['agent', 'subagent'])],
            'selected_agency_id' => ['nullable', 'integer'],
        ];

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'email')) {
            $rules['email'][] = Rule::unique('users', 'email');
        }

        if (Schema::hasTable('agents') && Schema::hasColumn('agents', 'email')) {
            $rules['email'][] = Rule::unique('agents', 'email');
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre y apellido es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'email.unique' => 'Este correo electrónico ya está registrado en el sistema.',
            'phone.required' => 'El teléfono es obligatorio.',
            'phone.regex' => 'El teléfono solo debe contener números.',
            'owner_code.required' => 'No se pudo determinar la agencia asociada al registro.',
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
