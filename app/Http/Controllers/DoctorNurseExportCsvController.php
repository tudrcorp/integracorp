<?php

namespace App\Http\Controllers;

use App\Models\DoctorNurse;
use App\Support\CsvExportStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DoctorNurseExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'doctor_nurse_export_csv_';

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
            'Especialidad',
            'Razón social',
            'RIF',
            'Convenio',
            'Estado en sistema',
            'Clasificación',
            'Estado',
            'Ciudad',
            'Zona de cobertura',
            'Tipo de clínica',
            'Horario',
            'Teléfono personal',
            'Teléfono local',
            'Correo',
            'Ubicación principal',
            'Convenio de pago',
            'Tiempo de crédito',
            'Afiliación proveedor',
            'Creado',
            'Actualizado',
            'Creado por',
            'Actualizado por',
        ];

        $filename = 'proveedores_naturales_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($ids, $headers): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            DoctorNurse::query()
                ->with(['supplierClasificacion'])
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lazyById(100)
                ->each(function (DoctorNurse $record) use ($handle): void {
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
    private function buildRow(DoctorNurse $record): array
    {
        return [
            (string) ($record->name ?? ''),
            (string) ($record->speciality ?? ''),
            (string) ($record->razon_social ?? ''),
            (string) ($record->rif ?? ''),
            (string) ($record->status_convenio ?? ''),
            (string) ($record->status_sistema ?? ''),
            (string) ($record->supplierClasificacion?->description ?? ''),
            (string) ($record->state ?? ''),
            (string) ($record->city ?? ''),
            (string) ($record->coverage_zone ?? ''),
            (string) ($record->tipo_clinica ?? ''),
            (string) ($record->horario ?? ''),
            (string) ($record->personal_phone ?? ''),
            (string) ($record->local_phone ?? ''),
            (string) ($record->correo_principal ?? ''),
            (string) ($record->ubicacion_principal ?? ''),
            (string) ($record->convenio_pago ?? ''),
            (string) ($record->tiempo_credito ?? ''),
            (string) ($record->afiliacion_proveedor ?? ''),
            (string) ($record->created_at ?? ''),
            (string) ($record->updated_at ?? ''),
            (string) ($record->created_by ?? ''),
            (string) ($record->updated_by ?? ''),
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
