<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Collections\CollectionNoticeAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Regenerar un aviso de cobro. Sin `notice` se regenera con los datos por defecto (emisión hoy y
 * período según la frecuencia); con `notice`, con lo que editó el analista.
 */
class RegenerateAvisoCobroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return CollectionNoticeAccess::allows($this->user());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No tiene permiso para regenerar avisos de cobro.');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'notice' => ['sometimes', 'array'],
            'notice.full_name' => ['required_with:notice', 'string', 'max:200'],
            'notice.document' => ['nullable', 'string', 'max:40'],
            'notice.address' => ['nullable', 'string', 'max:500'],
            'notice.phone' => ['nullable', 'string', 'max:60'],
            'notice.email' => ['nullable', 'email', 'max:190'],
            'notice.emission_date' => ['required_with:notice', 'date_format:d/m/Y'],
            'notice.period_from' => ['required_with:notice', 'date_format:d/m/Y'],
            'notice.period_to' => ['required_with:notice', 'date_format:d/m/Y', 'after_or_equal:notice.period_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'notice.full_name.required_with' => 'Escriba el nombre del cliente.',
            'notice.full_name.max' => 'El nombre no puede pasar de 200 caracteres.',
            'notice.document.max' => 'El documento no puede pasar de 40 caracteres.',
            'notice.address.max' => 'La dirección no puede pasar de 500 caracteres.',
            'notice.phone.max' => 'El teléfono no puede pasar de 60 caracteres.',
            'notice.email.email' => 'Escriba un correo válido.',
            'notice.emission_date.required_with' => 'Escriba la fecha de emisión.',
            'notice.emission_date.date_format' => 'La fecha de emisión debe tener el formato dd/mm/aaaa.',
            'notice.period_from.required_with' => 'Escriba el inicio del período de cobro.',
            'notice.period_from.date_format' => 'El inicio del período debe tener el formato dd/mm/aaaa.',
            'notice.period_to.required_with' => 'Escriba el fin del período de cobro.',
            'notice.period_to.date_format' => 'El fin del período debe tener el formato dd/mm/aaaa.',
            'notice.period_to.after_or_equal' => 'El fin del período de cobro debe ser igual o posterior al inicio.',
        ];
    }

    /**
     * @return array<string, string>|null
     */
    public function edits(): ?array
    {
        $notice = $this->validated()['notice'] ?? null;

        return is_array($notice) ? array_map(static fn (mixed $value): string => (string) ($value ?? ''), $notice) : null;
    }
}
