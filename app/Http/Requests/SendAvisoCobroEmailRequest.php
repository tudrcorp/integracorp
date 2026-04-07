<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendAvisoCobroEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
