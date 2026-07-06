<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Filament\Business\Resources\BusinessAppointments\BusinessAppointmentLabels;
use App\Models\BusinessAppointments;
use App\Support\CsvExportStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessAppointmentExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'business_appointment_export_csv_';

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
            'Teléfono',
            'Correo',
            'País',
            'Estado',
            'Ciudad',
            'Estado de la cita',
            'Creado por',
            'Actualizado por',
            'Alta',
            'Última actualización',
        ];

        $filename = 'citas_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($ids, $headers): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            BusinessAppointments::query()
                ->with(['country', 'state', 'city'])
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lazyById(100)
                ->each(function (BusinessAppointments $record) use ($handle): void {
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
    private function buildRow(BusinessAppointments $record): array
    {
        return [
            (string) $record->legal_name,
            (string) ($record->phone ?? ''),
            (string) ($record->email ?? ''),
            (string) ($record->country?->name ?? ''),
            (string) ($record->state?->definition ?? ''),
            (string) ($record->city?->definition ?? ''),
            BusinessAppointmentLabels::statusLabel($record->status),
            (string) ($record->created_by ?? ''),
            (string) ($record->updated_by ?? ''),
            (string) $record->created_at,
            (string) $record->updated_at,
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
