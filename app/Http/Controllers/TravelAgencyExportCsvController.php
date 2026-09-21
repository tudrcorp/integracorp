<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TravelAgency;
use App\Support\CommercialStructureBankingExportColumns;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TravelAgencyExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'travel_agency_export_csv_';

    private const TOKEN_TTL_SECONDS = 120;

    /**
     * Exporta las agencias de viaje seleccionadas a Excel (.xlsx).
     *
     * Se genera un .xlsx real, no un CSV: el número de cuenta bancaria del beneficiario
     * llega a tener 20 dígitos, y Excel interpreta cualquier cadena así de larga como un
     * número al abrir un CSV plano (notación científica, pérdida de precisión más allá de
     * los primeros 15 dígitos), sin importar el truco de escritura usado. El formato .xlsx
     * declara el tipo de cada celda explícitamente, así que el número de cuenta llega
     * siempre íntegro y legible.
     */
    public function __invoke(Request $request): BinaryFileResponse
    {
        $token = $request->query('token');

        if (! is_string($token) || $token === '') {
            abort(400, 'Token de exportación no válido o expirado.');
        }

        $ids = Cache::pull(self::CACHE_PREFIX.$token);

        if (! is_array($ids) || empty($ids)) {
            abort(400, 'Token de exportación no válido o expirado.');
        }

        $filename = 'agencias_de_viaje_'.now()->format('Y-m-d_His').'.xlsx';
        $path = tempnam(sys_get_temp_dir(), 'travel_agency_export_');

        if ($path === false) {
            abort(500, 'No se pudo preparar el archivo temporal.');
        }

        $path .= '.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($this->headers()));

        TravelAgency::query()
            ->with(['country', 'state', 'city'])
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lazyById(100)
            ->each(function (TravelAgency $record) use ($writer): void {
                $writer->addRow(Row::fromValues($this->buildRow($record)));
            });

        $writer->close();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @return list<string>
     */
    private function headers(): array
    {
        return [
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
            ...CommercialStructureBankingExportColumns::csvHeaders(),
        ];
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
            ...CommercialStructureBankingExportColumns::valuesFromModel($record),
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
