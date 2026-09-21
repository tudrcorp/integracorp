<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Support\CommercialStructureBankingExportColumns;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AgentExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'agent_export_csv_';

    private const TOKEN_TTL_SECONDS = 120;

    /**
     * Exporta los agentes seleccionados a Excel (.xlsx).
     * Requiere ?token=xxx (token generado por la acción de la tabla con los IDs en cache).
     *
     * Se genera un .xlsx real, no un CSV: el número de cuenta bancaria del beneficiario
     * llega a tener 20 dígitos, y Excel interpreta cualquier cadena así de larga como un
     * número al abrir un CSV plano (notación científica, pérdida de precisión más allá de
     * los primeros 15 dígitos), sin importar el truco de escritura usado. El formato .xlsx
     * declara el tipo de cada celda explícitamente, así que el número de cuenta llega
     * siempre íntegro y legible, tal como ya hace `AdministrationAgentReportsExportService`.
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

        $filename = 'agentes_'.now()->format('Y-m-d_His').'.xlsx';
        $path = tempnam(sys_get_temp_dir(), 'agent_export_');

        if ($path === false) {
            abort(500, 'No se pudo preparar el archivo temporal.');
        }

        $path .= '.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($this->headers()));

        Agent::query()
            ->with(['country', 'state', 'city'])
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lazyById(100)
            ->each(function (Agent $record) use ($writer): void {
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
            ...CommercialStructureBankingExportColumns::csvHeaders(),
        ];
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
