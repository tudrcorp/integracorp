<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Support\CsvExportStream;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AffiliationCorporatePopulationExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'affiliation_corporate_population_export_csv_';

    private const TOKEN_TTL_SECONDS = 120;

    private const EMPTY_AFFILIATE_COLUMNS = 18;

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
            'Cliente corporativo',
            'RIF',
            'Agencia',
            'Correo contratante',
            'Teléfono contratante',
            'Frecuencia de pago',
            'Estatus',
            'Creada el',
            'Afiliado #',
            'Plan',
            'Cobertura',
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
            'Estatus afiliado',
        ];

        $filename = 'afiliaciones_corporativas_poblacion_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($ids, $headers): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            AffiliationCorporate::query()
                ->with(['accountManager', 'agent', 'agency'])
                ->whereIn('id', $ids)
                ->orderBy('code')
                ->lazyById(50)
                ->each(function (AffiliationCorporate $record) use ($handle): void {
                    $affiliates = $this->orderedAffiliates($record);

                    if ($affiliates->isEmpty()) {
                        fputcsv($handle, $this->buildCorporateRow($record));

                        return;
                    }

                    $affiliates->each(function (AffiliateCorporate $affiliate, int $index) use ($handle, $record): void {
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
     * @return Collection<int, AffiliateCorporate>
     */
    private function orderedAffiliates(AffiliationCorporate $record): Collection
    {
        return AffiliateCorporate::query()
            ->with(['plan', 'coverage'])
            ->where('affiliation_corporate_id', $record->id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    private function buildCorporateRow(AffiliationCorporate $record): array
    {
        return array_merge(
            $this->corporateColumns($record),
            array_fill(0, self::EMPTY_AFFILIATE_COLUMNS, ''),
        );
    }

    /**
     * @return array<int, string>
     */
    private function buildAffiliateRow(AffiliationCorporate $record, AffiliateCorporate $affiliate, int $affiliateNumber): array
    {
        return array_merge(
            $this->corporateColumns($record),
            [
                (string) $affiliateNumber,
                (string) ($affiliate->plan?->description ?? ''),
                $affiliate->coverage?->price !== null ? (string) $affiliate->coverage->price : '',
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
                (string) ($affiliate->status ?? ''),
            ],
        );
    }

    /**
     * @return array<int, string>
     */
    private function corporateColumns(AffiliationCorporate $record): array
    {
        return [
            (string) ($record->code ?? ''),
            (string) ($record->accountManager?->name ?? '—'),
            self::agentLabel($record),
            (string) ($record->name_corporate ?? ''),
            (string) ($record->rif ?? ''),
            self::agencyLabel($record),
            (string) ($record->email ?? ''),
            (string) ($record->phone ?? ''),
            (string) ($record->payment_frequency ?? ''),
            (string) ($record->status ?? ''),
            $record->created_at !== null ? $record->created_at->format('d/m/Y H:i') : '',
        ];
    }

    private static function agencyLabel(AffiliationCorporate $record): string
    {
        if ($record->code_agency === 'TDG-100') {
            return 'TuDrEnCasa';
        }

        return (string) ($record->agency?->name_corporative ?? '—');
    }

    private static function agentLabel(AffiliationCorporate $record): string
    {
        if ($record->agent_id === null) {
            return 'TuDrEnCasa';
        }

        return (string) ($record->agent?->name ?? '—');
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
