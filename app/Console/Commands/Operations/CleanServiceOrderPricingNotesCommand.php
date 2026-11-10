<?php

declare(strict_types=1);

namespace App\Console\Commands\Operations;

use App\Models\OperationServiceOrder;
use App\Support\Operations\OperationServiceOrderInternalPricingNote;
use App\Support\SecurityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Quita de las observaciones de las órdenes de servicio la frase interna con el
 * costo base y el margen de TDG («Generada automáticamente desde cotización…»).
 *
 * Sin --apply solo muestra qué cambiaría. Con --apply guarda antes un respaldo
 * JSON (storage privado), actualiza en una transacción solo si el valor sigue
 * siendo el que se leyó, y deja traza por orden. --restore=<archivo> deshace.
 */
class CleanServiceOrderPricingNotesCommand extends Command
{
    protected $signature = 'operations:clean-service-order-pricing-notes
        {--apply : Escribe los cambios; sin esta opción solo simula}
        {--restore= : Ruta (en el disco local) del respaldo a restaurar}
        {--actor= : Nombre de quien autoriza, para la traza}';

    protected $description = 'Quita de las órdenes de servicio la frase interna con costo base y margen de ganancia';

    public const BACKUP_DIRECTORY = 'corrections/service-order-pricing-notes';

    public function handle(): int
    {
        if (filled($this->option('restore'))) {
            return $this->restore((string) $this->option('restore'));
        }

        $rows = OperationServiceOrder::query()
            ->where('observations', 'like', '%costo base%')
            ->orderBy('id')
            ->get(['id', 'order_number', 'observations'])
            ->filter(fn (OperationServiceOrder $order): bool => OperationServiceOrderInternalPricingNote::contains($order->observations))
            ->map(fn (OperationServiceOrder $order): array => [
                'id' => (int) $order->id,
                'order_number' => (string) $order->order_number,
                'before' => (string) $order->observations,
                'after' => OperationServiceOrderInternalPricingNote::strip($order->observations),
            ])
            ->values()
            ->all();

        if ($rows === []) {
            $this->info('No hay órdenes con la frase interna de costo y ganancia. No se cambia nada.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Orden', 'Observación actual', 'Quedará'], array_map(static fn (array $row): array => [
            $row['id'],
            $row['order_number'],
            mb_strimwidth($row['before'], 0, 70, '…'),
            $row['after'] === null ? '(vacía)' : mb_strimwidth($row['after'], 0, 50, '…'),
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

        $backup = self::BACKUP_DIRECTORY.'/'.now()->format('Y_m_d_His').'.json';
        Storage::disk('local')->put($backup, (string) json_encode([
            'created_at' => now()->toIso8601String(),
            'actor' => $actor,
            'rows' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('Respaldo guardado en storage/app/private/'.$backup);

        try {
            $changed = DB::transaction(function () use ($rows, $actor): int {
                $changed = 0;

                foreach ($rows as $row) {
                    $changed += OperationServiceOrder::query()
                        ->whereKey($row['id'])
                        ->where('observations', $row['before'])
                        ->update(['observations' => $row['after'], 'updated_by' => $actor]);
                }

                return $changed;
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->error('No se aplicó nada: '.$exception->getMessage());

            return self::FAILURE;
        }

        foreach ($rows as $row) {
            SecurityAudit::log('AUDIT_OPERATIONS_SERVICE_ORDER_PRICING_NOTE_REMOVED', 'console.operations.clean-service-order-pricing-notes', [
                'service_order_id' => $row['id'],
                'order_number' => $row['order_number'],
                'before' => $row['before'],
                'after' => $row['after'],
                'actor' => $actor,
                'backup' => $backup,
            ]);
        }

        $this->info("Listo: {$changed} de ".count($rows).' órdenes actualizadas. El PDF de cada una se regenera sin la frase la próxima vez que se abra o se envíe.');

        if ($changed < count($rows)) {
            $this->warn('Algunas órdenes cambiaron mientras corría el comando y no se tocaron. Vuelva a ejecutarlo para revisarlas.');
        }

        return self::SUCCESS;
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
            $this->line(count($rows).' órdenes volverían a su observación anterior.');
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
                if ($row['after'] === null) {
                    $query->whereNull('observations');
                } else {
                    $query->where('observations', $row['after']);
                }

                $restored += $query->update(['observations' => $row['before'], 'updated_by' => $actor]);
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
