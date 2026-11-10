<?php

declare(strict_types=1);

namespace App\Console\Commands\Operations;

use App\Models\OperationServiceOrder;
use App\Services\OperationServiceOrderPdfService;
use App\Support\Operations\OperationServiceOrderInternalPricingNote as PricingNote;
use App\Support\SecurityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Quita de las órdenes de servicio los datos internos de precio que salían en
 * el PDF que recibe el proveedor:
 *
 *  - en observaciones, la frase «Generada automáticamente desde cotización #N
 *    (costo base …, ganancia …%)»;
 *  - en la descripción, el tramo «· Total US$ …» (precio de venta de TDG).
 *
 * Sin --apply solo muestra qué cambiaría. Con --apply guarda antes un respaldo
 * JSON (storage privado), actualiza en una transacción solo si los valores
 * siguen siendo los que se leyeron, deja traza por orden, regenera el PDF de
 * cada orden y pasa las copias viejas del PDF (disco público) al respaldo
 * privado. --restore=<archivo> deshace el cambio de datos.
 */
class CleanServiceOrderPricingNotesCommand extends Command
{
    protected $signature = 'operations:clean-service-order-pricing-notes
        {--apply : Escribe los cambios; sin esta opción solo simula}
        {--restore= : Ruta (en el disco local) del respaldo a restaurar}
        {--actor= : Nombre de quien autoriza, para la traza}
        {--order=* : Solo estas órdenes, por número (ORD-0603, 0603 o 603). Se repite o va separado por comas}';

    protected $description = 'Quita de las órdenes de servicio el costo base, la ganancia y el total de venta que salían en el PDF del proveedor';

    public const BACKUP_DIRECTORY = 'corrections/service-order-pricing-notes';

    private const PDF_DIRECTORY = 'operation-service-orders/generated-pdf';

    public function handle(): int
    {
        if (filled($this->option('restore'))) {
            return $this->restore((string) $this->option('restore'));
        }

        $orderNumbers = $this->requestedOrderNumbers();

        if ($orderNumbers !== []) {
            $this->reportRequestedOrders($orderNumbers);
        }

        $rows = OperationServiceOrder::query()
            ->where(fn ($query) => $query
                ->where('observations', 'like', '%costo base%')
                ->orWhere('description', 'like', '%Total%'))
            ->when($orderNumbers !== [], fn ($query) => $query->whereIn('order_number', $orderNumbers))
            ->orderBy('id')
            ->get(['id', 'order_number', 'description', 'observations'])
            ->filter(fn (OperationServiceOrder $order): bool => self::needsCleaning($order))
            ->map(fn (OperationServiceOrder $order): array => [
                'id' => (int) $order->id,
                'order_number' => (string) $order->order_number,
                'before' => $order->observations,
                'after' => PricingNote::contains($order->observations) ? PricingNote::strip($order->observations) : $order->observations,
                'description_before' => $order->description,
                'description_after' => PricingNote::stripDescriptionTotal($order->description),
            ])
            ->values()
            ->all();

        if ($rows === []) {
            $this->info($orderNumbers === []
                ? 'No hay órdenes con costo, ganancia o total de venta en sus datos. No se cambia nada.'
                : 'Ninguna de las órdenes indicadas tiene costo, ganancia o total de venta en sus datos. No se cambia nada.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Orden', 'Qué se quita', 'Descripción quedará', 'Observación quedará'], array_map(static fn (array $row): array => [
            $row['id'],
            $row['order_number'],
            implode(' + ', array_filter([
                $row['description_before'] !== $row['description_after'] ? 'total de venta' : null,
                $row['before'] !== $row['after'] ? 'costo y ganancia' : null,
            ])),
            mb_strimwidth((string) $row['description_after'], 0, 55, '…'),
            $row['before'] === $row['after'] ? '(sin cambio)' : ($row['after'] === null ? '(vacía)' : mb_strimwidth($row['after'], 0, 40, '…')),
        ], $rows));
        $this->line(count($rows).' órdenes afectadas.');

        if (! $this->option('apply')) {
            $this->warn('Simulación: no se guardó nada. Repita con --apply --actor="Nombre" para aplicar.');

            return self::SUCCESS;
        }

        $actor = trim((string) $this->option('actor'));

        if ($actor === '') {
            $this->error('Indique --actor con el nombre de quien autoriza el cambio.');

            return self::FAILURE;
        }

        $stamp = now()->format('Y_m_d_His');
        $backup = self::BACKUP_DIRECTORY.'/'.$stamp.'.json';
        Storage::disk('local')->put($backup, (string) json_encode([
            'created_at' => now()->toIso8601String(),
            'actor' => $actor,
            'rows' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('Respaldo guardado en storage/app/private/'.$backup);

        try {
            $changedIds = DB::transaction(function () use ($rows, $actor): array {
                $changedIds = [];

                foreach ($rows as $row) {
                    $updated = self::whereValues(OperationServiceOrder::query()->whereKey($row['id']), $row['description_before'], $row['before'])
                        ->update([
                            'description' => $row['description_after'],
                            'observations' => $row['after'],
                            'updated_by' => $actor,
                        ]);

                    if ($updated > 0) {
                        $changedIds[] = $row['id'];
                    }
                }

                return $changedIds;
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->error('No se aplicó nada: '.$exception->getMessage());

            return self::FAILURE;
        }

        foreach ($rows as $row) {
            if (! in_array($row['id'], $changedIds, true)) {
                continue;
            }

            SecurityAudit::log('AUDIT_OPERATIONS_SERVICE_ORDER_PRICING_NOTE_REMOVED', 'console.operations.clean-service-order-pricing-notes', [
                'service_order_id' => $row['id'],
                'order_number' => $row['order_number'],
                'before' => $row['before'],
                'after' => $row['after'],
                'description_before' => $row['description_before'],
                'description_after' => $row['description_after'],
                'actor' => $actor,
                'backup' => $backup,
            ]);
        }

        $this->info('Listo: '.count($changedIds).' de '.count($rows).' órdenes actualizadas.');

        if (count($changedIds) < count($rows)) {
            $this->warn('Algunas órdenes cambiaron mientras corría el comando y no se tocaron. Vuelva a ejecutarlo para revisarlas.');
        }

        $this->refreshPdfs($rows, $changedIds, $stamp);

        return self::SUCCESS;
    }

    private static function needsCleaning(OperationServiceOrder $order): bool
    {
        return PricingNote::contains($order->observations) || PricingNote::descriptionContainsTotal($order->description);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<OperationServiceOrder>  $query
     * @return \Illuminate\Database\Eloquent\Builder<OperationServiceOrder>
     */
    private static function whereValues($query, ?string $description, ?string $observations)
    {
        foreach (['description' => $description, 'observations' => $observations] as $column => $value) {
            $value === null ? $query->whereNull($column) : $query->where($column, $value);
        }

        return $query;
    }

    /**
     * Genera el PDF vigente de cada orden corregida y pasa al respaldo privado
     * todas sus copias anteriores del disco público, que todavía muestran los
     * datos internos. Se mueven, no se borran: quedan como constancia de lo que
     * se envió.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<int>  $changedIds
     */
    private function refreshPdfs(array $rows, array $changedIds, string $stamp): void
    {
        $public = Storage::disk('public');
        $archiveDirectory = self::BACKUP_DIRECTORY.'/pdf-'.$stamp;
        $allFiles = $public->exists(self::PDF_DIRECTORY) ? $public->files(self::PDF_DIRECTORY) : [];
        $regenerated = 0;
        $archived = 0;
        $failed = [];

        foreach ($rows as $row) {
            if (! in_array($row['id'], $changedIds, true)) {
                continue;
            }

            try {
                $order = OperationServiceOrder::query()->findOrFail($row['id']);
                $current = OperationServiceOrderPdfService::ensurePersisted($order, true);
                $regenerated++;

                $safeOrder = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) $order->order_number) ?: (string) $order->id;
                $prefix = self::PDF_DIRECTORY.'/service-order-'.$safeOrder.'-';

                foreach ($allFiles as $file) {
                    if ($file === $current || ! str_starts_with($file, $prefix)) {
                        continue;
                    }

                    Storage::disk('local')->put($archiveDirectory.'/'.basename($file), (string) $public->get($file));
                    $public->delete($file);
                    $archived++;
                }
            } catch (Throwable $exception) {
                report($exception);
                $failed[] = $row['order_number'];
            }
        }

        $this->info("PDF regenerados: {$regenerated}. Copias viejas retiradas del disco público: {$archived}"
            .($archived > 0 ? " (guardadas en storage/app/private/{$archiveDirectory})." : '.'));

        if ($failed !== []) {
            $this->warn('No se pudo regenerar el PDF de: '.implode(', ', $failed).'. Se regenera solo la próxima vez que se abra o se envíe la orden.');
        }
    }

    /**
     * @param  list<string>  $orderNumbers
     */
    private function reportRequestedOrders(array $orderNumbers): void
    {
        $found = OperationServiceOrder::query()
            ->whereIn('order_number', $orderNumbers)
            ->get(['id', 'order_number', 'description', 'observations'])
            ->keyBy(fn (OperationServiceOrder $order): string => mb_strtoupper((string) $order->order_number));

        foreach ($orderNumbers as $number) {
            $order = $found->get($number);

            if ($order === null) {
                $this->warn("{$number}: no existe esa orden.");
            } elseif (! self::needsCleaning($order)) {
                $this->line("{$number}: no tiene costo, ganancia ni total de venta; no se toca.");
            }
        }
    }

    /**
     * Números de orden pedidos con --order, normalizados a «ORD-0603».
     *
     * @return list<string>
     */
    private function requestedOrderNumbers(): array
    {
        $numbers = [];

        foreach ((array) $this->option('order') as $value) {
            foreach (preg_split('/[\s,;]+/', (string) $value) ?: [] as $piece) {
                $piece = mb_strtoupper(trim($piece));

                if ($piece === '') {
                    continue;
                }

                if (preg_match('/^(?:ORD-?)?(\d+)$/', $piece, $match) === 1) {
                    $piece = 'ORD-'.str_pad(ltrim($match[1], '0') ?: '0', 4, '0', STR_PAD_LEFT);
                }

                $numbers[$piece] = $piece;
            }
        }

        return array_values($numbers);
    }

    private function restore(string $path): int
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            $this->error("No existe el respaldo: {$path}");

            return self::FAILURE;
        }

        $data = json_decode((string) $disk->get($path), true);
        $rows = is_array($data['rows'] ?? null) ? $data['rows'] : null;

        if ($rows === null) {
            $this->error('El respaldo no es válido.');

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->line(count($rows).' órdenes volverían a sus datos anteriores.');
            $this->warn('Simulación: repita con --apply --actor="Nombre" para restaurar.');

            return self::SUCCESS;
        }

        $actor = trim((string) $this->option('actor'));

        if ($actor === '') {
            $this->error('Indique --actor con el nombre de quien autoriza la restauración.');

            return self::FAILURE;
        }

        $restored = DB::transaction(function () use ($rows, $actor): int {
            $restored = 0;

            foreach ($rows as $row) {
                $query = OperationServiceOrder::query()->whereKey((int) $row['id']);
                $row['after'] === null ? $query->whereNull('observations') : $query->where('observations', $row['after']);
                $values = ['observations' => $row['before'], 'updated_by' => $actor];

                // Los respaldos anteriores a la limpieza de la descripción no traen estas claves.
                if (array_key_exists('description_after', $row)) {
                    $row['description_after'] === null ? $query->whereNull('description') : $query->where('description', $row['description_after']);
                    $values['description'] = $row['description_before'];
                }

                $restored += $query->update($values);
            }

            return $restored;
        });

        SecurityAudit::log('AUDIT_OPERATIONS_SERVICE_ORDER_PRICING_NOTE_RESTORED', 'console.operations.clean-service-order-pricing-notes', [
            'backup' => $path,
            'restored' => $restored,
            'actor' => $actor,
        ]);

        $this->info("Restauradas {$restored} de ".count($rows).' órdenes.');

        return self::SUCCESS;
    }
}
