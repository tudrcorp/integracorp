<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Sales\SaleReceiptEmailAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class SendReciboPagoEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return SaleReceiptEmailAccess::canSend($this->user());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No tiene permiso para enviar recibos de pago.');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'affiliate_template_id' => filled($this->input('affiliate_template_id')) ? $this->input('affiliate_template_id') : null,
            'responsible_template_id' => filled($this->input('responsible_template_id')) ? $this->input('responsible_template_id') : null,
            'test_email' => filled($this->input('test_email')) ? trim((string) $this->input('test_email')) : null,
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
