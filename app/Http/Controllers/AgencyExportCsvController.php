<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Support\CommercialStructureBankingExportColumns;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AgencyExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'agency_export_csv_';

    private const TOKEN_TTL_SECONDS = 120;

    /**
     * Exporta las agencias seleccionadas a Excel (.xlsx).
     * Requiere ?token=xxx (token generado por la acción de la tabla con los IDs en cache).
     *
     * Se genera un .xlsx real, no un CSV: el número de cuenta bancaria del beneficiario
     * llega a tener 20 dígitos, y Excel interpreta cualquier cadena así de larga como un
     * número al abrir un CSV plano (notación científica, pérdida de precisión más allá de
     * los primeros 15 dígitos), sin importar el truco de escritura usado. El formato .xlsx
     * declara el tipo de cada celda explícitamente, así que el número de cuenta llega
     * siempre íntegro y legible, tal como ya hace `AdministrationAgencyReportsExportService`.
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

        $filename = 'agencias_'.now()->format('Y-m-d_His').'.xlsx';
        $path = tempnam(sys_get_temp_dir(), 'agency_export_');

        if ($path === false) {
            abort(500, 'No se pudo preparar el archivo temporal.');
        }

        $path .= '.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($this->headers()));

        Agency::query()
            ->with(['typeAgency', 'country', 'state', 'city'])
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lazyById(100)
            ->each(function (Agency $record) use ($writer): void {
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
            'ID',
            'PERTENECE A:',
            'CÓDIGO AGENCIA',
            'TIPO DE AGENCIA',
            'RIF',
            'NOMBRE CORPORATIVO',
            'CÉDULA RESPONSABLE',
            'DIRECCIÓN',
            'CORREO ELECTRÓNICO',
            'TELÉFONO PRINCIPAL',
            'INSTAGRAM',
            'PAÍS',
            'ESTADO',
            'CIUDAD',
            'REGIÓN',
            'NOMBRE CONTACTO',
            'CORREO CONTACTO',
            'TELÉFONO CONTACTO',
            'ESTATUS',
            'CREADO POR',
            'FECHA DE CREACIÓN',
            'FECHA DE ACTUALIZACIÓN',
            'COMENTARIOS',
            'USUARIO TDEV',
            'NOMBRE REPRESENTANTE LEGAL',
            ...CommercialStructureBankingExportColumns::csvHeaders(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildRow(Agency $record): array
    {
        return [
            (string) $record->id,
            (string) ($record->owner_code ?? ''),
            (string) ($record->code ?? ''),
            (string) ($record->typeAgency?->definition ?? ''),
            (string) ($record->rif ?? ''),
            (string) ($record->name_corporative ?? ''),
            (string) ($record->ci_responsable ?? ''),
            (string) ($record->address ?? ''),
            (string) ($record->email ?? ''),
            (string) ($record->phone ?? ''),
            (string) ($record->user_instagram ?? ''),
            (string) ($record->country?->name ?? ''),
            (string) ($record->state?->definition ?? ''),
            (string) ($record->city?->definition ?? ''),
            (string) ($record->region ?? ''),
            (string) ($record->name_contact_2 ?? ''),
            (string) ($record->email_contact_2 ?? ''),
            (string) ($record->phone_contact_2 ?? ''),
            (string) ($record->status ?? ''),
            (string) ($record->created_by ?? ''),
            (string) ($record->created_at ?? ''),
            (string) ($record->updated_at ?? ''),
            (string) ($record->comments ?? ''),
            (string) ($record->user_tdev ?? ''),
            (string) ($record->name_representative ?? ''),
            ...CommercialStructureBankingExportColumns::valuesFromModel($record),
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
