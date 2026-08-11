<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CompanyAssociateStatus;
use App\Models\CompanyAssociate;
use App\Support\CsvExportStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyAssociateExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'company_associate_export_csv_';

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
            'ID',
            'Nombre y apellido',
            'Cédula',
            'Edad',
            'Sexo',
            'Correo',
            'Teléfono',
            'Empresa',
            'RIF empresa',
            'Responsable',
            'Cédula responsable',
            'Estatus',
            'Código voucher ILS',
            'Vigencia desde',
            'Vigencia hasta',
            'Fecha de vuelo',
            'Hora de vuelo',
            'Estado',
            'Ciudad',
            'Contacto emergencia',
            'Teléfono contacto',
            'Correo contacto',
            'Razón de anulación',
            'Anulado el',
            'Registrado el',
        ];

        $filename = 'asociados_nuevos_negocios_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($ids, $headers): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            CompanyAssociate::query()
                ->with(['company', 'responsible', 'state', 'city'])
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lazyById(100)
                ->each(function (CompanyAssociate $record) use ($handle): void {
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
    private function buildRow(CompanyAssociate $record): array
    {
        return [
            (string) $record->getKey(),
            (string) $record->full_name,
            (string) $record->identity_card,
            (string) ($record->age ?? ''),
            (string) ($record->sex ?? ''),
            (string) ($record->email ?? ''),
            (string) ($record->phone ?? ''),
            (string) ($record->company?->name ?? ''),
            (string) ($record->company?->rif ?? ''),
            (string) ($record->responsible?->full_name ?? ''),
            (string) ($record->responsible?->identity_card ?? ''),
            CompanyAssociateStatus::labelFromMixed($record->status),
            (string) ($record->vaucher_ils ?? ''),
            (string) ($record->date_init ?? ''),
            (string) ($record->date_end ?? ''),
            $record->flight_date?->format('d/m/Y') ?? '',
            filled($record->flight_time) ? substr((string) $record->flight_time, 0, 5) : '',
            (string) ($record->state?->definition ?? ''),
            (string) ($record->city?->definition ?? ''),
            (string) ($record->contact_full_name ?? ''),
            (string) ($record->contact_phone ?? ''),
            (string) ($record->contact_email ?? ''),
            (string) ($record->annulment_reason ?? ''),
            $record->annulled_at?->format('d/m/Y H:i:s') ?? '',
            $record->registered_at?->format('d/m/Y H:i:s') ?? '',
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
