<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\IndividualQuote;
use App\Support\CsvExportStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IndividualQuoteExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'individual_quote_export_csv_';

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
            'Agencia',
            'Tipo agencia',
            'Código de cotización',
            'Account Manager',
            'Agente',
            'Solicitante',
            'Tipo de plan',
            'Correo',
            'Teléfono',
            'Generada el',
            'Estatus',
            'Creado',
            'Actualizado',
        ];

        $filename = 'cotizaciones_individuales_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($ids, $headers): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            /** @var array<string, string> $agencyTypeLabels */
            $agencyTypeLabels = [];

            IndividualQuote::query()
                ->with(['accountManager', 'agent'])
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lazyById(100)
                ->each(function (IndividualQuote $record) use ($handle, &$agencyTypeLabels): void {
                    fputcsv($handle, $this->buildRow($record, $agencyTypeLabels));
                });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param  array<string, string>  $agencyTypeLabels
     * @return array<int, string>
     */
    private function buildRow(IndividualQuote $record, array &$agencyTypeLabels): array
    {
        $codeAgency = (string) ($record->code_agency ?? '');
        $agencyTypeLabel = $this->resolveAgencyTypeLabel($codeAgency, $agencyTypeLabels);

        return [
            $codeAgency,
            $agencyTypeLabel,
            (string) ($record->code ?? ''),
            (string) ($record->accountManager?->name ?? '—'),
            (string) ($record->agent?->name ?? '—'),
            (string) ($record->full_name ?? ''),
            self::planLabel($record->plan),
            (string) ($record->email ?? ''),
            (string) ($record->phone ?? ''),
            $record->created_at !== null ? $record->created_at->format('d/m/Y H:i') : '',
            (string) ($record->status ?? ''),
            (string) ($record->created_at ?? ''),
            (string) ($record->updated_at ?? ''),
        ];
    }

    /**
     * @param  array<string, string>  $agencyTypeLabels
     */
    private function resolveAgencyTypeLabel(string $codeAgency, array &$agencyTypeLabels): string
    {
        if ($codeAgency === '') {
            return 'MASTER';
        }

        if (! array_key_exists($codeAgency, $agencyTypeLabels)) {
            $agency = Agency::query()
                ->with('typeAgency')
                ->where('code', $codeAgency)
                ->first();

            $agencyTypeLabels[$codeAgency] = $agency?->typeAgency?->definition ?? 'MASTER';
        }

        return $agencyTypeLabels[$codeAgency];
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
