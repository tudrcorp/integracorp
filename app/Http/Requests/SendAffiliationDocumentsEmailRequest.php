<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendAffiliationDocumentsEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }
}
