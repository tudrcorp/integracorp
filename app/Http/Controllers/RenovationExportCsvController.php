<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Renovation;
use App\Support\CsvExportStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RenovationExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'renovation_export_csv_';

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
            'Código afiliación',
            'Titular',
            'Días restantes',
            'Estatus',
            'Fecha vencimiento',
            'Negociación Plan Especial',
            'Plan',
            'Plan anterior',
            'Cobertura',
            'Subtotal anual',
            'Personas',
            'Frecuencia pago',
            'Agencia',
            'Agente',
            'Jerarquía',
            'Actualizado',
        ];

        $filename = 'renovaciones_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($ids, $headers): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            Renovation::query()
                ->with([
                    'affiliation',
                    'plan',
                    'previousPlan',
                    'coverage',
                ])
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lazyById(100)
                ->each(function (Renovation $record) use ($handle): void {
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
    private function buildRow(Renovation $record): array
    {
        $coverageLabel = $record->coverage_id
            ? 'US$ '.number_format((float) ($record->coverage?->price ?? 0), 2)
            : 'Inicial';

        return [
            (string) ($record->code_affiliation ?? ''),
            (string) ($record->affiliation?->full_name_ti ?? ''),
            $record->remaining_days !== null ? (string) $record->remaining_days : '',
            self::statusLabel($record->status),
            $record->date_renewal !== null ? $record->date_renewal->format('d/m/Y') : '',
            $record->is_negotiation_candidate ? 'Plan Especial' : '—',
            (string) ($record->plan?->description ?? ''),
            (string) ($record->previousPlan?->description ?? ''),
            $coverageLabel,
            (string) ($record->subtotal_anual ?? ''),
            (string) ($record->total_persons ?? ''),
            (string) ($record->payment_frequency ?? ''),
            (string) ($record->code_agency ?? ''),
            (string) ($record->agent_id ?? ''),
            (string) ($record->owner_code ?? ''),
            (string) ($record->updated_at ?? ''),
        ];
    }

    private static function statusLabel(?string $status): string
    {
        return match ($status) {
            'PERIODO DE RENOVACION' => 'En renovación',
            'VIGENTE' => 'Vigente',
            default => (string) ($status ?? ''),
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
