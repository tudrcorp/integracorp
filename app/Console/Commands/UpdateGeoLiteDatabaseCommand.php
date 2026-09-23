<?php

declare(strict_types=1);

namespace App\Console\Commands;

use GeoIp2\Database\Reader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Descarga la base GeoLite2-City de MaxMind para ubicar por IP en el monitor
 * en vivo. Requiere MAXMIND_ACCOUNT_ID y MAXMIND_LICENSE_KEY (cuenta gratuita).
 *
 * El reemplazo es atómico: la base nueva solo sustituye a la anterior si abre
 * y responde, así una descarga corrupta nunca deja el monitor sin ubicación.
 */
class UpdateGeoLiteDatabaseCommand extends Command
{
    protected $signature = 'live-presence:geoip-update';

    protected $description = 'Descarga o actualiza la base GeoLite2-City de MaxMind para el monitor en vivo';

    public function handle(): int
    {
        $accountId = (string) config('live-presence.geoip.account_id');
        $licenseKey = (string) config('live-presence.geoip.license_key');
        $target = (string) config('live-presence.geoip.database');

        if ($accountId === '' || $licenseKey === '') {
            $this->error('Faltan MAXMIND_ACCOUNT_ID y MAXMIND_LICENSE_KEY en el .env (cuenta gratuita en maxmind.com).');

            return self::FAILURE;
        }

        $workDir = storage_path('app/geoip/tmp-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($workDir);
        $archive = $workDir.'/GeoLite2-City.tar.gz';

        try {
            $this->info('Descargando GeoLite2-City…');

            $response = Http::withBasicAuth($accountId, $licenseKey)
                ->timeout(180)
                ->withOptions(['sink' => $archive])
                ->get((string) config('live-presence.geoip.download_url'));

            if (! $response->successful()) {
                $this->error('MaxMind respondió HTTP '.$response->status().'. Revise la cuenta y la licencia.');

                return self::FAILURE;
            }

            (new PharData($archive))->extractTo($workDir, null, true);
            $mmdb = $this->findDatabase($workDir);

            if ($mmdb === null) {
                $this->error('El archivo descargado no contiene GeoLite2-City.mmdb.');

                return self::FAILURE;
            }

            $probe = new Reader($mmdb);
            $probe->city('8.8.8.8');
            $probe->close();

            File::ensureDirectoryExists(dirname($target));
            $staging = $target.'.new';
            File::copy($mmdb, $staging);
            File::move($staging, $target);

            $this->info('Base actualizada en '.$target.' ('.round(filesize($target) / 1048576, 1).' MB).');
            $this->line('Si hay workers o PHP-FPM corriendo, reinícielos para que tomen la base nueva.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('No se pudo actualizar la base: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    private function findDatabase(string $directory): ?string
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === 'GeoLite2-City.mmdb') {
                return $file->getPathname();
            }
        }

        return null;
    }
}
