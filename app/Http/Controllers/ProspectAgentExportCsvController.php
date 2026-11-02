<?php

namespace App\Http\Controllers;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;
use App\Models\ProspectAgent;
use App\Support\CsvExportStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProspectAgentExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'prospect_agent_export_csv_';

    private const TOKEN_TTL_SECONDS = 120;

    /**
     * Orden de columnas acordado con Negocios; {@see buildRow()} debe seguirlo campo por campo.
     */
    public const HEADERS = [
        'Estado',
        'Ciudad',
        'Nombre del Prospecto',
        'Tipo',
        'Clasificación',
        'Estatus',
        'Teléfono principal',
        'Teléfono alternativo',
        'Correo',
        'Instagram',
        'Fecha Creado',
        'Fecha Actualizado',
        'Creado por',
        'Actualizado por',
        'Observaciones iniciales',
    ];

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

        $headers = self::HEADERS;

        $filename = 'prospectos_agentes_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($ids, $headers): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            ProspectAgent::query()
                ->withTrashed()
                ->with(['state', 'city'])
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lazyById(100)
                ->each(function (ProspectAgent $record) use ($handle): void {
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
    public function buildRow(ProspectAgent $record): array
    {
        return [
            (string) ($record->state?->definition ?? ''),
            (string) ($record->city?->definition ?? ''),
            (string) $record->name,
            ProspectAgentLabels::typeLabel($record->type),
            (string) ($record->classification ?? ''),
            ProspectAgentLabels::statusLabel($record->status),
            (string) $record->phone_1,
            (string) $record->phone_2,
            (string) $record->email,
            (string) ($record->instagram ?? ''),
            (string) $record->created_at?->format('d/m/Y H:i'),
            (string) $record->updated_at?->format('d/m/Y H:i'),
            (string) $record->created_by,
            (string) $record->updated_by,
            (string) ($record->initial_observ ?? ''),
        ];
    }

    /**
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
