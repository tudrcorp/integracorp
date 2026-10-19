<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Errors\ErrorReportTicket;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reporte de un error a Sistemas desde la página de error. El contexto viaja
 * cifrado (ErrorReportContext); lo único que escribe el usuario es la nota.
 */
class StoreErrorReportRequest extends FormRequest
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
            'context' => ['required', 'string', 'max:20000'],
            'note' => ['nullable', 'string', 'max:'.ErrorReportTicket::MAX_NOTE_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'context.required' => 'Faltan los datos del error. Recarga la página e inténtalo de nuevo.',
            'context.string' => 'Los datos del error no son válidos. Recarga la página e inténtalo de nuevo.',
            'context.max' => 'Los datos del error no son válidos. Recarga la página e inténtalo de nuevo.',
            'note.string' => 'El comentario debe ser texto.',
            'note.max' => 'El comentario puede tener hasta '.ErrorReportTicket::MAX_NOTE_LENGTH.' caracteres.',
        ];
    }
}
