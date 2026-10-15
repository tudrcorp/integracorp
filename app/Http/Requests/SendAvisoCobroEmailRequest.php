<?php

namespace App\Http\Requests;

use App\Support\Collections\CollectionNoticeAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class SendAvisoCobroEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CollectionNoticeAccess::allows($this->user());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No tiene permiso para enviar avisos de cobro.');
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
