<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationServiceFlowEvent;
use App\Models\OperationServiceOrder;
use App\Services\OperationServiceOrderPdfService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Devuelve las órdenes CADUCADAS al estado que tenían antes de caducar.
 *
 * El estado anterior sale del evento ORDER_EXPIRED de la traza; las órdenes que
 * caducaron antes de existir la traza vuelven a EN GESTION, el único estado
 * abierto del flujo. Solo cambia el estado: ítems, cotizaciones y cuentas por
 * pagar o cobrar nunca se tocaron al caducar. El PDF se regenera aparte, ya sin
 * la franja «ORDEN CADUCADA».
 */
final class ExpiredServiceOrderRestorer
{
    public const FALLBACK_STATUS = 'EN GESTION';

    public const REASON = 'Restauración de órdenes caducadas: la caducidad automática se revirtió.';

    /**
     * @param  list<string>  $orders  IDs o números de orden; vacío = todas las CADUCADAS
     * @return Builder<OperationServiceOrder>
     */
    public static function candidatesQuery(array $orders = []): Builder
    {
        $query = OperationServiceOrder::query()
            ->where('status', OperationServiceOrderValidity::STATUS_EXPIRED);

        $orders = array_values(array_filter(array_map(static fn (mixed $value): string => trim((string) $value), $orders)));

        if ($orders !== []) {
            $ids = array_map('intval', array_filter($orders, static fn (string $value): bool => ctype_digit($value)));
            $numbers = array_values(array_filter($orders, static fn (string $value): bool => ! ctype_digit($value)));

            $query->where(function (Builder $inner) use ($ids, $numbers): void {
                $inner->whereIn('id', $ids === [] ? [0] : $ids)
                    ->orWhereIn('order_number', $numbers === [] ? [''] : $numbers);
            });
        }

        return $query;
    }

    /**
     * Estado que tenía cada orden antes de caducar, según su último evento ORDER_EXPIRED.
     *
     * @param  list<int>  $orderIds
     * @return array<int, array{status: string, source: 'traza'|'supuesto'}>
     */
    public static function previousStatuses(array $orderIds): array
    {
        $result = [];

        foreach ($orderIds as $orderId) {
            $result[$orderId] = ['status' => self::FALLBACK_STATUS, 'source' => 'supuesto'];
        }

        if ($orderIds === [] || ! self::traceTableExists()) {
            return $result;
        }

        $latestIds = OperationServiceFlowEvent::query()
            ->whereIn('operation_service_order_id', $orderIds)
            ->where('event', ServiceOrderFlowTrace::ORDER_EXPIRED)
            ->selectRaw('MAX(id) as id')
            ->groupBy('operation_service_order_id')
            ->pluck('id');

        $events = OperationServiceFlowEvent::query()
            ->whereIn('id', $latestIds)
            ->get(['id', 'operation_service_order_id', 'changes']);

        foreach ($events as $event) {
            $changes = $event->changes;

            if (is_string($changes)) {
                $changes = json_decode($changes, true);
            }

            $before = is_array($changes) ? mb_strtoupper(trim((string) ($changes['status']['antes'] ?? ''))) : '';

            if (self::isRestorableStatus($before)) {
                $result[(int) $event->operation_service_order_id] = ['status' => $before, 'source' => 'traza'];
            }
        }

        return $result;
    }

    /**
     * Un estado al que se puede volver: abierto (ni cerrado ni vacío).
     */
    public static function isRestorableStatus(string $status): bool
    {
        $status = mb_strtoupper(trim($status));

        return $status !== '' && ! in_array($status, OperationServiceOrderValidity::closedStatuses(), true);
    }

    /**
     * ¿La orden volvería a caducar con la configuración vigente si se restaura a `$status`?
     */
    public static function wouldExpireAgain(OperationServiceOrder $order, string $status): bool
    {
        $probe = clone $order;
        $probe->setRawAttributes([...$order->getAttributes(), 'status' => $status]);

        return OperationServiceOrderValidity::isExpired($probe);
    }

    /**
     * Restaura una orden. Devuelve false si ya no estaba CADUCADA (otro proceso la
     * tocó o se corrió dos veces): así el comando se puede repetir sin efectos dobles.
     */
    public static function restore(OperationServiceOrder $order, string $status, string $updatedBy): bool
    {
        if (! self::isRestorableStatus($status)) {
            $status = self::FALLBACK_STATUS;
        }

        return (bool) DB::transaction(function () use ($order, $status, $updatedBy): bool {
            $locked = OperationServiceOrder::query()->whereKey($order->getKey())->lockForUpdate()->first();

            if ($locked === null || mb_strtoupper(trim((string) $locked->status)) !== OperationServiceOrderValidity::STATUS_EXPIRED) {
                return false;
            }

            ServiceOrderFlowTrace::withContext(['reason' => self::REASON], function () use ($locked, $status, $updatedBy): void {
                $locked->update([
                    'status' => $status,
                    'updated_by' => $updatedBy,
                ]);
            });

            $order->setRawAttributes($locked->getAttributes(), true);

            return true;
        });
    }

    /**
     * Regenera el PDF de la orden ya restaurada (sale sin la franja de caducada).
     *
     * @return array{path: ?string, error: ?string}
     */
    public static function regeneratePdf(OperationServiceOrder $order): array
    {
        try {
            return ['path' => OperationServiceOrderPdfService::ensurePersisted($order->fresh() ?? $order, true), 'error' => null];
        } catch (Throwable $exception) {
            return ['path' => null, 'error' => $exception->getMessage()];
        }
    }

    /**
     * Resumen para la simulación.
     *
     * @param  Collection<int, OperationServiceOrder>  $orders
     * @param  array<int, array{status: string, source: string}>  $previous
     * @return array{total: int, from_trace: int, assumed: int, would_expire_again: int, by_status: array<string, int>}
     */
    public static function summarize(Collection $orders, array $previous): array
    {
        $summary = ['total' => 0, 'from_trace' => 0, 'assumed' => 0, 'would_expire_again' => 0, 'by_status' => []];

        foreach ($orders as $order) {
            $target = $previous[(int) $order->getKey()] ?? ['status' => self::FALLBACK_STATUS, 'source' => 'supuesto'];

            $summary['total']++;
            $summary[$target['source'] === 'traza' ? 'from_trace' : 'assumed']++;
            $summary['by_status'][$target['status']] = ($summary['by_status'][$target['status']] ?? 0) + 1;

            if (self::wouldExpireAgain($order, $target['status'])) {
                $summary['would_expire_again']++;
            }
        }

        return $summary;
    }

    private static function traceTableExists(): bool
    {
        try {
            return Schema::hasTable('operation_service_flow_events');
        } catch (Throwable) {
            return false;
        }
    }
}
