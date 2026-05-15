<?php

namespace App\Http\Controllers;

use App\Models\HelpDesk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HelpdeskExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'helpdesk_export_csv_';

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
            'Descripción',
            'Prioridad',
            'Estado',
            'Asignados',
            'Creado por',
            'Actualizado por',
            'Creado',
            'Actualizado',
        ];

        $filename = 'tickets_helpdesk_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($ids, $headers): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);

            HelpDesk::query()
                ->with(['rrhhColaboradores'])
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lazyById(100)
                ->each(function (HelpDesk $record) use ($handle): void {
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
    private function buildRow(HelpDesk $record): array
    {
        $assigned = $record->rrhhColaboradores
            ->pluck('fullName')
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->implode('; ');

        return [
            (string) $record->id,
            trim((string) ($record->description ?? '')),
            (string) ($record->priority ?? ''),
            (string) ($record->status ?? ''),
            $assigned,
            (string) ($record->created_by ?? ''),
            (string) ($record->updated_by ?? ''),
            (string) ($record->created_at ?? ''),
            (string) ($record->updated_at ?? ''),
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
