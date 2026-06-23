<?php

namespace App\Http\Controllers;

use App\Models\CorporateQuoteRequest;
use App\Support\CsvExportStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CorporateQuoteRequestExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'corporate_quote_request_export_csv_';

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
            'Código agencia',
            'Tipo agencia',
            'Fecha de asignación',
            'Código',
            'Account Manager',
            'Agente',
            'Solicitada por',
            'RIF',
            'Email',
            'Teléfono',
            'Estado',
            'Región',
            'Estatus',
            'Creado por',
            'Documento',
            'Creado',
            'Actualizado',
        ];

        $filename = 'solicitudes_cotizacion_corporativa_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($ids, $headers): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            CorporateQuoteRequest::query()
                ->with(['accountManager', 'state', 'agency.typeAgency'])
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lazyById(100)
                ->each(function (CorporateQuoteRequest $record) use ($handle): void {
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
    private function buildRow(CorporateQuoteRequest $record): array
    {
        $agencyTypeLabel = $record->agency?->typeAgency?->definition ?? 'MASTER';

        return [
            (string) ($record->code_agency ?? ''),
            $agencyTypeLabel,
            (string) ($record->date_asignation ?? ''),
            (string) ($record->code ?? ''),
            (string) ($record->accountManager?->name ?? '-----'),
            $record->agent_id !== null ? 'AGT-000'.(string) $record->agent_id : '',
            (string) ($record->full_name ?? ''),
            (string) ($record->rif ?? ''),
            (string) ($record->email ?? ''),
            (string) ($record->phone ?? ''),
            (string) ($record->state?->definition ?? ''),
            (string) ($record->region ?? ''),
            (string) ($record->status ?? ''),
            (string) ($record->created_by ?? ''),
            $record->document_file ? 'Sí' : 'No',
            (string) ($record->created_at ?? ''),
            (string) ($record->updated_at ?? ''),
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
