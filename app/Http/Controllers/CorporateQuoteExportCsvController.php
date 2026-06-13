<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CorporateQuote;
use App\Support\CsvExportStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CorporateQuoteExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'corporate_quote_export_csv_';

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
            'Código',
            'Account Manager',
            'Agente',
            'Solicitante',
            'RIF',
            'Tipo de plan',
            'Correo',
            'Teléfono',
            'Generada el',
            'Estatus',
            'Creado',
            'Actualizado',
        ];

        $filename = 'cotizaciones_corporativas_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($ids, $headers): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            CorporateQuote::query()
                ->with(['accountManager', 'agent'])
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lazyById(100)
                ->each(function (CorporateQuote $record) use ($handle): void {
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
    private function buildRow(CorporateQuote $record): array
    {
        return [
            (string) ($record->code ?? ''),
            (string) ($record->accountManager?->name ?? '—'),
            (string) ($record->agent?->name ?? '—'),
            (string) ($record->full_name ?? ''),
            (string) ($record->rif ?? ''),
            self::planLabel($record->plan),
            (string) ($record->email ?? ''),
            (string) ($record->phone ?? ''),
            $record->created_at !== null ? $record->created_at->format('d/m/Y H:i') : '',
            (string) ($record->status ?? ''),
            (string) ($record->created_at ?? ''),
            (string) ($record->updated_at ?? ''),
        ];
    }

    private static function planLabel(mixed $plan): string
    {
        return match (true) {
            $plan === '1' || $plan === 1 => 'Plan Inicial',
            $plan === '2' || $plan === 2 => 'Plan Ideal',
            $plan === '3' || $plan === 3 => 'Plan Especial',
            $plan === 'CM' => 'MultiPlan',
            $plan === null, $plan === '' => '—',
            default => (string) $plan,
        };
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
