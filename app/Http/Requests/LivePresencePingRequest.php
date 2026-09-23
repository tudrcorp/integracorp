<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Latido del navegador para el monitor en vivo. Todo es opcional y acotado:
 * el cliente puede mentir, así que solo se aceptan valores con forma y rango
 * razonables y nunca se guarda texto largo.
 */
class LivePresencePingRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'in:load,heartbeat,navigate,visibility'],
            'path' => ['nullable', 'string', 'max:300'],
            'title' => ['nullable', 'string', 'max:150'],
            'rtt' => ['nullable', 'integer', 'min:0', 'max:120000'],
            'visible' => ['nullable', 'boolean'],
            'effective_type' => ['nullable', 'string', 'max:12'],
            'downlink' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'conn_rtt' => ['nullable', 'integer', 'min:0', 'max:120000'],
            'standalone' => ['nullable', 'boolean'],
            'load' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'ttfb' => ['nullable', 'integer', 'min:0', 'max:600000'],
            'screen' => ['nullable', 'string', 'max:20'],
            'memory' => ['nullable', 'numeric', 'min:0', 'max:1024'],
            'cores' => ['nullable', 'integer', 'min:0', 'max:512'],
            'lang' => ['nullable', 'string', 'max:20'],
            'tz' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * Ruta sin consulta ni fragmento: pueden llevar tokens.
     *
     * @return array<string, mixed>
     */
    public function clientData(): array
    {
        $data = $this->validated();
        $path = (string) ($data['path'] ?? '');
        $data['path'] = (string) (parse_url($path, PHP_URL_PATH) ?? '');

        return $data;
    }
}
