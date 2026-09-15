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

            // datos bancarios nacionales (moneda local)
            'BENEFICIARIO NACIONAL: NOMBRE/RAZÓN SOCIAL',
            'BENEFICIARIO NACIONAL: CI/RIF',
            'BENEFICIARIO NACIONAL: Nº DE CUENTA',
            'BENEFICIARIO NACIONAL: BANCO',
            'BENEFICIARIO NACIONAL: TIPO DE CUENTA',
            'BENEFICIARIO NACIONAL: TELÉFONO PAGO MÓVIL',
            'BENEFICIARIO NACIONAL: Nº DE CUENTA (MONEDA INTERNACIONAL)',
            'BENEFICIARIO NACIONAL: BANCO (MONEDA INTERNACIONAL)',
            'BENEFICIARIO NACIONAL: TIPO DE CUENTA (MONEDA INTERNACIONAL)',

            // datos bancarios internacionales (moneda extranjera)
            'BENEFICIARIO INTERNACIONAL: NOMBRE/RAZÓN SOCIAL',
            'BENEFICIARIO INTERNACIONAL: CI/RIF/ID/PASAPORTE',
            'BENEFICIARIO INTERNACIONAL: Nº DE CUENTA',
            'BENEFICIARIO INTERNACIONAL: BANCO',
            'BENEFICIARIO INTERNACIONAL: TIPO DE CUENTA',
            'BENEFICIARIO INTERNACIONAL: RUTA',
            'BENEFICIARIO INTERNACIONAL: ZELLE',
            'BENEFICIARIO INTERNACIONAL: ACH',
            'BENEFICIARIO INTERNACIONAL: SWIFT',
            'BENEFICIARIO INTERNACIONAL: ABA',
            'BENEFICIARIO INTERNACIONAL: DIRECCIÓN',
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

            // datos bancarios nacionales (moneda local)
            (string) ($record->local_beneficiary_name ?? ''),
            (string) ($record->local_beneficiary_rif ?? ''),
            CsvExportStream::forceTextForExcel($record->local_beneficiary_account_number),
            (string) ($record->local_beneficiary_account_bank ?? ''),
            (string) ($record->local_beneficiary_account_type ?? ''),
            CsvExportStream::forceTextForExcel($record->local_beneficiary_phone_pm),
            CsvExportStream::forceTextForExcel($record->local_beneficiary_account_number_mon_inter),
            (string) ($record->local_beneficiary_account_bank_mon_inter ?? ''),
            (string) ($record->local_beneficiary_account_type_mon_inter ?? ''),

            // datos bancarios internacionales (moneda extranjera)
            (string) ($record->extra_beneficiary_name ?? ''),
            (string) ($record->extra_beneficiary_ci_rif ?? ''),
            CsvExportStream::forceTextForExcel($record->extra_beneficiary_account_number),
            (string) ($record->extra_beneficiary_account_bank ?? ''),
            (string) ($record->extra_beneficiary_account_type ?? ''),
            CsvExportStream::forceTextForExcel($record->extra_beneficiary_route),
            (string) ($record->extra_beneficiary_zelle ?? ''),
            CsvExportStream::forceTextForExcel($record->extra_beneficiary_ach),
            (string) ($record->extra_beneficiary_swift ?? ''),
            CsvExportStream::forceTextForExcel($record->extra_beneficiary_aba),
            (string) ($record->extra_beneficiary_address ?? ''),
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
