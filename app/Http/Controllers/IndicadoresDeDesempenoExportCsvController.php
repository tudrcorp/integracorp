<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\CsvExportStream;
use App\Support\IndicadoresDeDesempeno\IndicadoresDeDesempenoCsvRows;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IndicadoresDeDesempenoExportCsvController extends Controller
{
    private const CACHE_PREFIX = 'indicadores_de_desempeno_export_csv_';

    private const TOKEN_TTL_SECONDS = 120;

    public function __invoke(Request $request): StreamedResponse
    {
        $token = $request->query('token');

        if (! is_string($token) || $token === '') {
            abort(400, 'Token de exportación no válido o expirado.');
        }

        $period = Cache::pull(self::CACHE_PREFIX.$token);

        if (! is_array($period) || ! isset($period['from'], $period['to'])) {
            abort(400, 'Token de exportación no válido o expirado.');
        }

        $from = (string) $period['from'];
        $to = (string) $period['to'];

        if (! self::isValidDate($from) || ! self::isValidDate($to) || $from > $to) {
            abort(400, 'El intervalo de fechas de exportación no es válido.');
        }

        $rows = IndicadoresDeDesempenoCsvRows::build($from, $to);
        $filename = 'indicadores_desempeno_'.$from.'_'.$to.'_'.now()->format('His').'.csv';

        return new StreamedResponse(function () use ($rows): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public static function storePeriodAndGetToken(string $from, string $to): string
    {
        $token = bin2hex(random_bytes(16));

        Cache::put(self::CACHE_PREFIX.$token, [
            'from' => $from,
            'to' => $to,
        ], self::TOKEN_TTL_SECONDS);

        return $token;
    }

    private static function isValidDate(string $date): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
    }
}
