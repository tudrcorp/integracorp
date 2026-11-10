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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'affiliate_template_id' => filled($this->input('affiliate_template_id')) ? $this->input('affiliate_template_id') : null,
            'responsible_template_id' => filled($this->input('responsible_template_id')) ? $this->input('responsible_template_id') : null,
            'test_email' => filled($this->input('test_email')) ? trim((string) $this->input('test_email')) : null,
            'attach_payment_methods' => filter_var($this->input('attach_payment_methods', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'affiliate_template_id' => ['nullable', 'integer', 'exists:document_email_templates,id', 'required_without:responsible_template_id'],
            'responsible_template_id' => ['nullable', 'integer', 'exists:document_email_templates,id'],
            'attach_payment_methods' => ['boolean'],
            'test_email' => ['nullable', 'email', 'max:190'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'affiliate_template_id.required_without' => 'Elija el copy de al menos un destinatario: el afiliado o el agente / agencia.',
            'affiliate_template_id.exists' => 'El copy elegido para el afiliado ya no existe.',
            'responsible_template_id.exists' => 'El copy elegido para el agente / agencia ya no existe.',
            'affiliate_template_id.integer' => 'El copy elegido para el afiliado no es válido.',
            'responsible_template_id.integer' => 'El copy elegido para el agente / agencia no es válido.',
            'test_email.email' => 'Escriba un correo de prueba válido.',
        ];
    }
}
