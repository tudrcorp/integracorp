<?php

declare(strict_types=1);

namespace App\Console\Commands;

use GeoIp2\Database\Reader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Descarga la base de ubicación por IP (DB-IP «IP to City Lite») para el
 * monitor en vivo. Gratuita, sin cuenta, uso comercial permitido citando a
 * DB-IP (CC BY 4.0). Se publica una por mes; si la del mes en curso aún no
 * está, se toma la del mes anterior.
 *
 * El reemplazo es atómico: la base nueva solo sustituye a la vigente si abre
 * y responde una consulta. Una descarga cortada o dañada nunca deja al
 * monitor sin ubicación.
 */
class UpdateIpLocationDatabaseCommand extends Command
{
    protected $signature = 'live-presence:geoip-update';

    protected $description = 'Descarga o actualiza la base de ubicación por IP (DB-IP Lite) del monitor en vivo';

    /** Una base de ciudades real pesa más de 50 MB: algo mucho menor es una página de error. */
    private const MIN_DATABASE_BYTES = 5 * 1024 * 1024;

    public function handle(): int
    {
        $target = (string) config('live-presence.geoip.database');
        $workDir = storage_path('app/geoip/tmp-'.bin2hex(random_bytes(4)));

        File::ensureDirectoryExists($workDir);

        try {
            [$month, $archive] = $this->download($workDir);
            $database = $workDir.'/ip-city.mmdb';

            $this->info('Descomprimiendo…');
            $this->gunzip($archive, $database);

            $this->info('Validando la base…');
            $type = $this->validate($database);

            File::ensureDirectoryExists(dirname($target));
            $staging = $target.'.new';
            File::copy($database, $staging);

            if (! @rename($staging, $target)) {
                throw new RuntimeException('No se pudo reemplazar la base en '.$target.'. Revise permisos de la carpeta.');
            }

            $this->info('Base actualizada: '.$type.' '.$month.' en '.$target.' ('.round((int) filesize($target) / 1048576, 1).' MB).');
            $this->line('Recargue PHP (Apache o PHP-FPM) y reinicie los workers para que tomen la base nueva.');
            $this->line('Ubicación por IP: '.config('live-presence.geoip.provider').' ('.config('live-presence.geoip.provider_url').'), licencia CC BY 4.0.');

            Log::info('LivePresence: base de ubicación por IP actualizada.', ['month' => $month, 'type' => $type, 'path' => $target]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('No se pudo actualizar la base de ubicación: '.$exception->getMessage());
            $this->line('La base anterior sigue en uso sin cambios.');

            Log::warning('LivePresence: falló la actualización de la base de ubicación por IP.', ['error' => $exception->getMessage()]);

            return self::FAILURE;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /**
     * @return array{0: string, 1: string} mes descargado y ruta del .gz
     */
    private function download(string $workDir): array
    {
        $pattern = (string) config('live-presence.geoip.download_url');
        $lastStatus = null;

        foreach ([now()->format('Y-m'), now()->subMonthNoOverflow()->format('Y-m')] as $month) {
            $url = str_replace('{month}', $month, $pattern);
            $archive = $workDir.'/ip-city-'.$month.'.mmdb.gz';

            $this->info('Descargando DB-IP City Lite '.$month.'…');

            $response = Http::timeout(600)
                ->retry(2, 3000, throw: false)
                ->withOptions(['sink' => $archive])
                ->get($url);

            if ($response->successful() && is_file($archive) && filesize($archive) > 1024) {
                return [$month, $archive];
            }

            $lastStatus = $response->status();
            @unlink($archive);
            $this->warn('No disponible (HTTP '.$lastStatus.'). Se intenta el mes anterior.');
        }

        throw new RuntimeException('DB-IP no respondió con la base (último código HTTP '.($lastStatus ?? '—').').');
    }

    /**
     * Descomprime por bloques: la base pesa más de 100 MB y no debe cargarse entera en memoria.
     */
    private function gunzip(string $archive, string $destination): void
    {
        $input = @gzopen($archive, 'rb');
        $output = @fopen($destination, 'wb');

        if ($input === false || $output === false) {
            throw new RuntimeException('No se pudo abrir el archivo descargado.');
        }

        try {
            while (! gzeof($input)) {
                $chunk = gzread($input, 1024 * 1024);

                if ($chunk === false) {
                    throw new RuntimeException('El archivo descargado está dañado.');
                }

                fwrite($output, $chunk);
            }
        } finally {
            gzclose($input);
            fclose($output);
        }

        if ((int) @filesize($destination) < self::MIN_DATABASE_BYTES) {
            throw new RuntimeException('El archivo descargado es demasiado pequeño para ser la base de ciudades.');
        }
    }

    private function validate(string $database): string
    {
        $reader = new Reader($database, ['es', 'en']);

        try {
            $type = (string) $reader->metadata()->databaseType;

            if (! str_contains(strtolower($type), 'city')) {
                throw new RuntimeException('La base descargada no es de ciudades ('.$type.').');
            }

            $record = $reader->city('8.8.8.8');

            if ((string) ($record->country->isoCode ?? '') === '') {
                throw new RuntimeException('La base no devolvió un país para una IP conocida.');
            }

            return $type;
        } finally {
            $reader->close();
        }
    }
}
