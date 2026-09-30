<?php

declare(strict_types=1);

namespace App\Console\Commands\Operations;

use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTable;
use App\Models\OperationCoordinationService;
use App\Models\TelemedicineCase;
use App\Models\User;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Operations\CoordinationServiceItemsManager;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Throwable;

/**
 * Diagnóstico de rendimiento del cuadro de control de servicios médicos.
 *
 * Solo lee: todo corre dentro de una transacción que se revierte, con la caché en
 * memoria (no toca la caché real) y autenticando sin eventos de inicio de sesión.
 * Pensado para correrlo en producción y comparar con local.
 */
class DiagnoseCoordinationTablePerformanceCommand extends Command
{
    protected $signature = 'operations:diagnose-coordination-table
                            {--user= : ID del analista con el que se mide (por defecto, el primer analista de Operaciones activo)}
                            {--iterations=5 : Repeticiones de cada medición}';

    protected $description = 'Mide (sin escribir nada) la configuración del servidor y las consultas del cuadro de control de servicios médicos';

    public function handle(): int
    {
        $iterations = max(1, min(50, (int) $this->option('iterations')));

        $this->reportEnvironment();

        config(['cache.default' => 'array']);

        DB::beginTransaction();

        try {
            $user = $this->resolveUser();

            if (! $user instanceof User) {
                $this->error('No se encontró el usuario. Indique uno con --user=ID.');

                return CommandAlias::FAILURE;
            }

            Auth::guard('web')->setUser($user);
            Filament::setCurrentPanel(Filament::getPanel('operations'));
            $this->line(sprintf('Usuario de la medición: #%d %s', $user->id, $user->email));
            $this->newLine();

            $this->reportDatabaseLatency($iterations);
            $this->reportVolumes();
            $this->reportQueries($iterations);
        } catch (Throwable $exception) {
            $this->error('El diagnóstico falló: '.$exception->getMessage());

            return CommandAlias::FAILURE;
        } finally {
            DB::rollBack();
            Auth::guard('web')->forgetUser();
        }

        $this->newLine();
        $this->info('Listo. No se escribió nada en la base ni en la caché.');
        $this->line('Para medir el arranque real de PHP-FPM (sin sesión ni base de datos), desde el servidor:');
        $this->line('  curl -s -o /dev/null -w "arranque FPM: %{time_starttransfer}s\n" '.rtrim((string) config('app.url'), '/').'/up');
        $this->line('Y la configuración de OPcache que usa FPM (la del CLI puede ser distinta):');
        $this->line('  php-fpm8.3 -i | grep -E "opcache\.(enable|memory_consumption|max_accelerated_files|interned_strings_buffer|validate_timestamps)|realpath_cache_size"');

        return CommandAlias::SUCCESS;
    }

    private function reportEnvironment(): void
    {
        $this->info('== Entorno');

        $rows = [
            ['APP_ENV', (string) config('app.env')],
            ['APP_DEBUG', var_export((bool) config('app.debug'), true)],
            ['Debugbar activo', $this->debugbarEnabled()],
            ['Driver de caché', (string) config('cache.default')],
            ['Driver de sesión', (string) config('session.driver')],
            ['Cola', (string) config('queue.default')],
            ['Host de MySQL', (string) config('database.connections.'.config('database.default').'.host')],
            ['PHP', PHP_VERSION],
            ['Config cacheada', $this->laravel->configurationIsCached() ? 'sí' : 'NO'],
            ['Rutas cacheadas', $this->laravel->routesAreCached() ? 'sí' : 'NO'],
            ['Eventos cacheados', $this->laravel->eventsAreCached() ? 'sí' : 'NO'],
            ['OPcache (este CLI)', $this->opcacheSummary()],
            ['realpath_cache_size (CLI)', (string) ini_get('realpath_cache_size')],
        ];

        $this->table(['Parámetro', 'Valor'], $rows);
    }

    private function reportDatabaseLatency(int $iterations): void
    {
        $this->info('== Latencia a MySQL (SELECT 1)');

        $samples = [];

        for ($i = 0; $i < max(10, $iterations * 4); $i++) {
            $start = hrtime(true);
            DB::select('SELECT 1');
            $samples[] = (hrtime(true) - $start) / 1e6;
        }

        $this->line($this->stats($samples).'  ← cada consulta de la página paga al menos esto');
        $this->newLine();
    }

    private function reportVolumes(): void
    {
        $this->info('== Volumen de datos');

        $tables = [
            'operation_coordination_services',
            'telemedicine_patient_medications',
            'telemedicine_patient_labs',
            'telemedicine_patient_studies',
            'telemedicine_patient_specialties',
            'telemedicine_cases',
            'notifications',
            'sessions',
        ];

        $rows = [];

        foreach ($tables as $table) {
            $rows[] = [$table, Schema::hasTable($table) ? number_format(DB::table($table)->count(), 0, ',', '.') : 'no existe'];
        }

        $indexes = collect(DB::select(
            'SELECT DISTINCT INDEX_NAME AS name FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            ['operation_coordination_services'],
        ))->pluck('name')->all();

        $rows[] = ['índices del cuadro (ocs_*)', count(array_filter($indexes, fn (string $name): bool => str_starts_with($name, 'ocs_'))).' de 4'];

        $this->table(['Tabla', 'Filas'], $rows);
    }

    private function reportQueries(int $iterations): void
    {
        $this->info('== Consultas del cuadro de control');

        $rows = [];

        $rows[] = ['Conteo de «Todas» (8 subconsultas)', $this->measure($iterations, fn (): int => OperationCoordinationServicesTable::applyHideFullyFinalizedScope(
            OperationsSupplierScope::coordinationServiceQuery()
        )->count())];

        $rows[] = ['Conteos por estatus (GROUP BY)', $this->measure($iterations, fn (): int => OperationsSupplierScope::coordinationServiceQuery()
            ->toBase()
            ->selectRaw('UPPER(TRIM(status)) AS estatus, COUNT(*) AS total')
            ->groupBy('estatus')
            ->get()
            ->count())];

        $records = null;
        $rows[] = ['Página de 10 registros + relaciones', $this->measure($iterations, function () use (&$records): int {
            $records = $this->pageQuery()->get();

            return $records->count();
        })];

        if ($records !== null && $records->isNotEmpty()) {
            $rows[] = ['Pintar «Ítems clínicos» de esas filas (PHP)', $this->measure($iterations, function () use ($records): int {
                CoordinationServiceItemsManager::flushClinicalItemsCache();

                foreach ($records as $record) {
                    CoordinationServiceItemsManager::renderCoordinationClinicalItemsCompactList($record);
                }

                return $records->count();
            })];
        }

        $this->table(['Medición', 'Tiempo (min / mediana / max)'], $rows);
        $this->line('Referencia local (30/09/2026, ~1.400 coordinaciones): «Todas» ~20 ms, página ~35 ms, ítems ~4 ms, latencia MySQL ~0,1 ms, /up ~0,16 s.');
    }

    /**
     * Misma consulta que la tabla: alcance del usuario, pestaña «Todas», relaciones
     * precargadas y el orden de los grupos por fecha de creación del caso.
     *
     * @return \Illuminate\Database\Eloquent\Builder<OperationCoordinationService>
     */
    private function pageQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = OperationCoordinationServicesTable::applyHideFullyFinalizedScope(
            OperationsSupplierScope::coordinationServiceQuery()
        );

        return $query
            ->with(OperationCoordinationServicesTable::listEagerLoads())
            ->orderByDesc(
                TelemedicineCase::query()
                    ->select('created_at')
                    ->whereColumn('telemedicine_cases.id', 'operation_coordination_services.telemedicine_case_id')
                    ->limit(1)
            )
            ->orderByDesc('date_solicitud')
            ->limit(10);
    }

    /**
     * @param  callable(): int  $callback
     */
    private function measure(int $iterations, callable $callback): string
    {
        $samples = [];
        $result = 0;

        for ($i = 0; $i < $iterations; $i++) {
            $start = hrtime(true);
            $result = $callback();
            $samples[] = (hrtime(true) - $start) / 1e6;
        }

        return $this->stats($samples).'  (resultado: '.$result.')';
    }

    /**
     * @param  list<float>  $samples
     */
    private function stats(array $samples): string
    {
        sort($samples);

        return sprintf(
            '%.1f / %.1f / %.1f ms',
            $samples[0],
            $samples[intdiv(count($samples), 2)],
            $samples[count($samples) - 1],
        );
    }

    private function resolveUser(): ?User
    {
        if (filled($this->option('user'))) {
            return User::query()->find((int) $this->option('user'));
        }

        return User::query()
            ->where('email', 'like', '%@tudrencasa.com')
            ->where('status', 'ACTIVO')
            ->where('departament', 'like', '%OPERACIONES%')
            ->whereNull('supplier_id')
            ->orderBy('id')
            ->first();
    }

    private function debugbarEnabled(): string
    {
        if (! class_exists(\Barryvdh\Debugbar\ServiceProvider::class)) {
            return 'no instalado';
        }

        $enabled = config('debugbar.enabled');

        if ($enabled === null) {
            return config('app.debug') ? 'SÍ (sigue a APP_DEBUG)' : 'no (sigue a APP_DEBUG)';
        }

        return $enabled ? 'SÍ' : 'no';
    }

    private function opcacheSummary(): string
    {
        if (! function_exists('opcache_get_status')) {
            return 'extensión no cargada';
        }

        if (! (bool) ini_get('opcache.enable_cli')) {
            return 'apagado en CLI (normal); revisar el de FPM con el comando del final';
        }

        return sprintf(
            'memoria %s MB, archivos máx. %s',
            (string) ini_get('opcache.memory_consumption'),
            (string) ini_get('opcache.max_accelerated_files'),
        );
    }
}
