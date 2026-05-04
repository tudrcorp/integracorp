<?php

namespace App\Http\Controllers;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;
use App\Models\ProspectAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProspectAgentExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'prospect_agent_export_csv_';

    private const TOKEN_TTL_SECONDS = 120;

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
            'Nombre',
            'Tipo',
            'Clasificación',
            'Estatus',
            'Observaciones iniciales',
            'Teléfono principal',
            'Teléfono alternativo',
            'Correo',
            'Instagram',
            'Estado',
            'Ciudad',
            'País',
            'Referido por',
            'Creado',
            'Actualizado',
            'Creado por',
            'Actualizado por',
        ];

        $filename = 'prospectos_agentes_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($ids, $headers): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            ProspectAgent::query()
                ->with(['state', 'city', 'country'])
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
    private function buildRow(ProspectAgent $record): array
    {
        return [
            (string) $record->name,
            ProspectAgentLabels::typeLabel($record->type),
            (string) ($record->classification ?? ''),
            ProspectAgentLabels::statusLabel($record->status),
            (string) ($record->initial_observ ?? ''),
            (string) $record->phone_1,
            (string) $record->phone_2,
            (string) $record->email,
            (string) ($record->instagram ?? ''),
            (string) ($record->state?->definition ?? ''),
            (string) ($record->city?->definition ?? ''),
            (string) ($record->country?->name ?? ''),
            ProspectAgentLabels::referenceLabel($record->reference_by),
            (string) $record->created_at,
            (string) $record->updated_at,
            (string) $record->created_by,
            (string) $record->updated_by,
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
