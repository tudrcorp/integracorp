<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class RegisterChatAgencyMasterRequest extends FormRequest
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
            'name_corporative' => ['required', 'string', 'min:3', 'max:255'],
            'tax_id' => ['required', 'string', 'min:6', 'max:20'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[0-9]+$/'],
        ];

        if (Schema::hasTable('agencies') && Schema::hasColumn('agencies', 'email')) {
            $rules['email'][] = Rule::unique('agencies', 'email');
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'email')) {
            $rules['email'][] = Rule::unique('users', 'email');
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name_corporative.required' => 'La razón social es obligatoria.',
            'tax_id.required' => 'El RIF o número de cédula del representante es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'email.unique' => 'Este correo electrónico ya está registrado en el sistema.',
            'phone.required' => 'El teléfono es obligatorio.',
            'phone.regex' => 'El teléfono solo debe contener números.',
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
