<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Support\CsvExportStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgentExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'agent_export_csv_';

    private const TOKEN_TTL_SECONDS = 120;

    /**
     * Exporta los agentes seleccionados a CSV.
     * Requiere ?token=xxx (token generado por la acción de la tabla con los IDs en cache).
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $token = $request->query('token');

        if (! is_string($token) || $token === '') {
            abort(400, 'Token de exportación no válido o expirado.');
        }

        $ids = Cache::pull(self::CACHE_PREFIX.$token);

        if (! is_array($ids) || empty($ids)) {
            abort(400, 'Token de exportación no válido o expirado.');
        }

        $headers = [
            'ID',
            'PERTENECE A:',
            'NOMBRE COMPLETO',
            'CÉDULA',
            'RIF',
            'FECHA DE NACIMIENTO',
            'DIRECCIÓN',
            'CORREO ELECTRÓNICO',
            'TELÉFONO PRINCIPAL',
            'INSTAGRAM',
            'PAÍS',
            'REGIÓN',
            'ESTADO',
            'CIUDAD',
            'SEXO',
            'ESTADO CIVIL',
            'NOMBRE CONTACTO',
            'CORREO CONTACTO',
            'TELÉFONO CONTACTO',
            'ESTATUS',
            'CREADO POR',
            'FECHA DE CREACIÓN',
            'FECHA DE ACTUALIZACIÓN',
            'USUARIO TDEV',
        ];

        $filename = 'agentes_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($ids, $headers): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            Agent::query()
                ->with(['country', 'state', 'city'])
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lazyById(100)
                ->each(function (Agent $record) use ($handle): void {
                    fputcsv($handle, $this->buildRow($record));
                });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function buildRow(Agent $record): array
    {
        return [
            (string) $record->id,
            (string) ($record->owner_code ?? ''),
            (string) ($record->name ?? ''),
            (string) ($record->ci ?? ''),
            (string) ($record->rif ?? ''),
            (string) ($record->birth_date ?? ''),
            (string) ($record->address ?? ''),
            (string) ($record->email ?? ''),
            (string) ($record->phone ?? ''),
            (string) ($record->user_instagram ?? ''),
            (string) ($record->country?->name ?? ''),
            (string) ($record->region ?? ''),
            (string) ($record->state?->definition ?? ''),
            (string) ($record->city?->definition ?? ''),
            (string) ($record->sex ?? ''),
            (string) ($record->marital_status ?? ''),
            (string) ($record->name_contact_2 ?? ''),
            (string) ($record->email_contact_2 ?? ''),
            (string) ($record->phone_contact_2 ?? ''),
            (string) ($record->status ?? ''),
            (string) ($record->created_by ?? ''),
            (string) ($record->created_at ?? ''),
            (string) ($record->updated_at ?? ''),
            (string) ($record->user_tdev ?? ''),
        ];
    }

    /**
     * Genera un token y guarda los IDs en cache. Útil para la acción de la tabla.
     *
     * @param  array<int|string>  $ids
     */
    public static function storeIdsAndGetToken(array $ids): string
    {
        $ids = array_values(array_map('intval', $ids));
        $token = bin2hex(random_bytes(16));
        Cache::put(self::CACHE_PREFIX.$token, $ids, self::TOKEN_TTL_SECONDS);

        return $token;
    }
}
