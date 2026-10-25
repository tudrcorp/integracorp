<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Telemedicine\PanelLog\TelemedicinePanelClientEvent;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Fallo que el navegador del médico reporta a la bitácora del panel de
 * Telemedicina. El cliente puede mentir: todo va acotado y nunca se guarda el
 * cuerpo de una respuesta de error.
 */
class TelemedicinePanelClientEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string', 'in:'.implode(',', TelemedicinePanelClientEvent::EVENTS)],
            'path' => ['required', 'string', 'max:500'],
            'status' => ['nullable', 'integer', 'min:0', 'max:999'],
            'message' => ['nullable', 'string', 'max:1000'],
            'source' => ['nullable', 'string', 'max:300'],
            'components' => ['nullable', 'array', 'max:10'],
            'components.*' => ['string', 'max:190'],
            'methods' => ['nullable', 'array', 'max:10'],
            'methods.*' => ['string', 'max:120'],
            'online' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Ruta sin consulta ni fragmento: pueden llevar tokens.
     */
    public function pagePath(): string
    {
        return '/'.ltrim((string) parse_url((string) $this->validated('path'), PHP_URL_PATH), '/');
    }
}
