<?php

namespace App\Http\Controllers;

use App\Models\TravelAgency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TravelAgencyExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'travel_agency_export_csv_';

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
            'Estado',
            'Correo',
            'Teléfono',
            'País',
            'Estado / provincia',
            'Ciudad',
            'Dirección',
            'Clasificación',
            'Nivel',
            'Comisión',
            'Crédito aprobado',
            'Fecha de ingreso',
            'Representante',
            'ID representante',
            'Tipo identificación',
            'Nº identificación',
            'Usuario portal',
            'Instagram',
            'Creado',
            'Actualizado',
            'Creado por',
            'Actualizado por',
        ];

        $filename = 'agencias_de_viaje_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($ids, $headers): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            TravelAgency::query()
                ->with(['country', 'state', 'city'])
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lazyById(100)
                ->each(function (TravelAgency $record) use ($handle): void {
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
    private function buildRow(TravelAgency $record): array
    {
        return [
            (string) ($record->name ?? ''),
            (string) ($record->status ?? ''),
            (string) ($record->email ?? ''),
            (string) ($record->phone ?? ''),
            (string) ($record->country?->name ?? ''),
            (string) ($record->state?->definition ?? ''),
            (string) ($record->city?->definition ?? ''),
            (string) ($record->address ?? ''),
            (string) ($record->classification ?? ''),
            (string) ($record->nivel ?? ''),
            (string) ($record->comision ?? ''),
            (string) ($record->montoCreditoAprobado ?? ''),
            (string) ($record->fechaIngreso ?? ''),
            (string) ($record->representante ?? ''),
            (string) ($record->idRepresentante ?? ''),
            (string) ($record->typeIdentification ?? ''),
            (string) ($record->numberIdentification ?? ''),
            (string) ($record->userPortalWeb ?? ''),
            (string) ($record->userInstagram ?? ''),
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
