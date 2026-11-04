<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Filament\Business\Resources\CapitationPlans\CapitationPlanResource;
use App\Services\CapitationPlanPdfService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PDF de planes de capitado. Los planes viajan como `ids=1,2,3` para que la URL de la
 * vista previa siga siendo corta con la selección máxima.
 */
class CapitationPlanPdfRequest extends FormRequest
{
    /** Mismo permiso que el menú Planes de Capitado: el PDF no lleva permiso propio. */
    public function authorize(): bool
    {
        return CapitationPlanResource::canAccess();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'string', 'max:2000', 'regex:/^\d{1,10}(,\d{1,10})*$/'],
            'download' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Indique al menos un plan de capitado.',
            'ids.regex' => 'La lista de planes no es válida.',
            'ids.max' => 'La lista de planes es demasiado larga.',
        ];
    }

    /**
     * @return list<int>
     */
    public function planIds(): array
    {
        return array_values(array_unique(array_map('intval', explode(',', (string) $this->validated('ids')))));
    }

    public function wantsDownload(): bool
    {
        return $this->boolean('download');
    }

    public function exceedsLimit(): bool
    {
        return count($this->planIds()) > CapitationPlanPdfService::MAX_PLANS;
    }
}
