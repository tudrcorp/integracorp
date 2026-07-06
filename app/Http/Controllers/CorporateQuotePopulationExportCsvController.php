<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CorporateQuote;
use App\Models\CorporateQuoteData;
use App\Support\CsvExportStream;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CorporateQuotePopulationExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'corporate_quote_population_export_csv_';

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
            'Correo corporativo',
            'Teléfono corporativo',
            'Generada el',
            'Estatus',
            'Afiliado #',
            'Nombre',
            'Apellido',
            'Cédula',
            'Fecha de nacimiento',
            'Edad',
            'Sexo',
            'Teléfono afiliado',
            'Correo afiliado',
            'Dirección',
            'Condición médica',
            'Fecha de ingreso',
            'Cargo',
            'Contacto de emergencia',
            'Teléfono de emergencia',
        ];

        $filename = 'cotizaciones_corporativas_poblacion_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($ids, $headers): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            CorporateQuote::query()
                ->with(['accountManager', 'agent'])
                ->whereIn('id', $ids)
                ->orderBy('code')
                ->lazyById(50)
                ->each(function (CorporateQuote $record) use ($handle): void {
                    $affiliates = $this->orderedAffiliates($record);

                    if ($affiliates->isEmpty()) {
                        fputcsv($handle, $this->buildCorporateRow($record));

                        return;
                    }

                    $affiliates->each(function (CorporateQuoteData $affiliate, int $index) use ($handle, $record): void {
                        fputcsv($handle, $this->buildAffiliateRow($record, $affiliate, $index + 1));
                    });
                });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return Collection<int, CorporateQuoteData>
     */
    private function orderedAffiliates(CorporateQuote $record): Collection
    {
        return CorporateQuoteData::query()
            ->where('corporate_quote_id', $record->id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    private function buildCorporateRow(CorporateQuote $record): array
    {
        return array_merge(
            $this->corporateColumns($record),
            [
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
            ],
        );
    }

    /**
     * @return array<int, string>
     */
    private function buildAffiliateRow(CorporateQuote $record, CorporateQuoteData $affiliate, int $affiliateNumber): array
    {
        return array_merge(
            $this->corporateColumns($record),
            [
                (string) $affiliateNumber,
                (string) ($affiliate->first_name ?? ''),
                (string) ($affiliate->last_name ?? ''),
                (string) ($affiliate->nro_identificacion ?? ''),
                (string) ($affiliate->birth_date ?? ''),
                (string) ($affiliate->age ?? ''),
                (string) ($affiliate->sex ?? ''),
                (string) ($affiliate->phone ?? ''),
                (string) ($affiliate->email ?? ''),
                (string) ($affiliate->address ?? ''),
                (string) ($affiliate->condition_medical ?? ''),
                (string) ($affiliate->initial_date ?? ''),
                (string) ($affiliate->position_company ?? ''),
                (string) ($affiliate->full_name_emergency ?? ''),
                (string) ($affiliate->phone_emergency ?? ''),
            ],
        );
    }

    /**
     * @return array<int, string>
     */
    private function corporateColumns(CorporateQuote $record): array
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
