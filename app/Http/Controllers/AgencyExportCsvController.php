<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Support\CsvExportStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgencyExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'agency_export_csv_';

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
            'Pertenece a',
            'Código agencia',
            'Tipo de agencia',
            'RIF',
            'Nombre corporativo',
            'Cédula responsable',
            'Dirección',
            'Correo electrónico',
            'Teléfono principal',
            'Instagram',
            'País',
            'Estado',
            'Ciudad',
            'Región',
            'Nombre contacto',
            'Correo contacto',
            'Teléfono contacto',
            'Estatus',
            'Creado por',
            'Fecha de creación',
            'Fecha de actualización',
            'Comentarios',
            'Usuario TDEV',
            'Nombre representante legal',
        ];

        $filename = 'Export-Agencies.csv';

        return new StreamedResponse(function () use ($ids, $headers): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            Agency::query()
                ->with(['typeAgency', 'country', 'state', 'city'])
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lazyById(100)
                ->each(function (Agency $record) use ($handle): void {
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
