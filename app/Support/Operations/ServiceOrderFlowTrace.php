<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceFlowEvent;
use App\Models\OperationServiceOrder;
use App\Models\Supplier;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Traza legible del flujo cotización → orden de servicio.
 *
 * Los cambios de estado de órdenes y cotizaciones se registran solos por los
 * observers (vengan de la pantalla, del scheduler o de un job). Las acciones del
 * analista abren un contexto con `withContext()` para que el evento lleve el
 * motivo y el nombre de la acción, y los intentos bloqueados se registran a mano
 * con `record()`: también son parte del camino recorrido.
 *
 * Registrar nunca rompe el flujo de negocio: si la tabla falta o el insert falla,
 * se deja en el log y la operación sigue.
 */
final class ServiceOrderFlowTrace
{
    public const ORDER_CREATED = 'ORDER_CREATED';

    public const ORDER_VOIDED = 'ORDER_VOIDED';

    public const ORDER_CANCELLED = 'ORDER_CANCELLED';

    public const ORDER_EXPIRED = 'ORDER_EXPIRED';

    public const ORDER_FINALIZED = 'ORDER_FINALIZED';

    public const ORDER_STATUS_CHANGED = 'ORDER_STATUS_CHANGED';

    public const ORDER_PROVIDER_CHANGED = 'ORDER_PROVIDER_CHANGED';

    public const ORDER_ADMIN_STATUS_CHANGED = 'ORDER_ADMIN_STATUS_CHANGED';

    public const QUOTE_CREATED = 'QUOTE_CREATED';

    public const QUOTE_UPDATED = 'QUOTE_UPDATED';

    public const QUOTE_STATUS_CHANGED = 'QUOTE_STATUS_CHANGED';

    public const QUOTE_LINKED = 'QUOTE_LINKED';

    public const QUOTE_RELEASED = 'QUOTE_RELEASED';

    public const RECEIVABLE_REOPENED = 'RECEIVABLE_REOPENED';

    public const ACTION_BLOCKED = 'ACTION_BLOCKED';

    public const CONTEXT_VOID = 'VOID';

    public const CONTEXT_CANCEL = 'CANCEL';

    public const CONTEXT_APPROVE_QUOTE = 'APPROVE_QUOTE';

    /** @var list<array<string, mixed>> */
    private static array $contextStack = [];

    private static ?bool $tableExists = null;

    /**
     * Ejecuta `$callback` con un contexto (acción, motivo, cotización de origen…)
     * que los observers leen al registrar lo que pase dentro.
     *
     * @template T
     *
     * @param  array{action?: string, reason?: string|null, quote_id?: int|null}  $context
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function withContext(array $context, Closure $callback): mixed
    {
        self::$contextStack[] = $context;

        try {
            return $callback();
        } finally {
            array_pop(self::$contextStack);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function context(): array
    {
        return array_merge([], ...self::$contextStack);
    }

    public static function contextAction(): ?string
    {
        $action = self::context()['action'] ?? null;

        return is_string($action) ? $action : null;
    }

    public static function contextReason(): ?string
    {
        $reason = trim((string) (self::context()['reason'] ?? ''));

        return $reason !== '' ? $reason : null;
    }

    /**
     * @param  array{order?: OperationServiceOrder|int|null, quote?: OperationQuoteGenerator|int|null, coordination?: OperationCoordinationService|int|null}  $refs
     * @param  array<string, mixed>  $changes
     */
    public static function record(
        string $event,
        string $description,
        array $refs = [],
        ?string $reason = null,
        array $changes = [],
    ): void {
        if (! self::tableExists()) {
            return;
        }

        try {
            $order = $refs['order'] ?? null;
            $quote = $refs['quote'] ?? null;
            $coordination = $refs['coordination'] ?? null;

            $orderId = $order instanceof OperationServiceOrder ? (int) $order->getKey() : (filled($order) ? (int) $order : null);
            $quoteId = $quote instanceof OperationQuoteGenerator ? (int) $quote->getKey() : (filled($quote) ? (int) $quote : null);
            $coordinationId = match (true) {
                $coordination instanceof OperationCoordinationService => (int) $coordination->getKey(),
                filled($coordination) => (int) $coordination,
                $order instanceof OperationServiceOrder && filled($order->operation_coordination_service_id) => (int) $order->operation_coordination_service_id,
                $quote instanceof OperationQuoteGenerator && filled($quote->operation_coordination_service_id) => (int) $quote->operation_coordination_service_id,
                default => null,
            };

            $caseId = $quote instanceof OperationQuoteGenerator && filled($quote->telemedicine_case_id)
                ? (int) $quote->telemedicine_case_id
                : ($coordinationId !== null
                    ? OperationCoordinationService::query()->whereKey($coordinationId)->value('telemedicine_case_id')
                    : null);

            $user = Auth::user();

            OperationServiceFlowEvent::query()->create([
                'operation_coordination_service_id' => $coordinationId,
                'telemedicine_case_id' => filled($caseId) ? (int) $caseId : null,
                'operation_service_order_id' => $orderId ?: null,
                'operation_quote_generator_id' => $quoteId ?: null,
                'event' => $event,
                'description' => $description,
                'reason' => $reason ?? self::contextReason(),
                'changes' => $changes !== [] ? $changes : null,
                'actor_id' => $user?->getAuthIdentifier(),
                'actor_name' => filled($user?->name) ? (string) $user->name : 'SISTEMA',
            ]);
        } catch (Throwable $exception) {
            Log::warning('No se pudo registrar la traza de orden de servicio.', [
                'event' => $event,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return Collection<int, OperationServiceFlowEvent>
     */
    public static function eventsForCoordination(int $coordinationId, int $limit = 300): Collection
    {
        if (! self::tableExists() || $coordinationId <= 0) {
            return collect();
        }

        return OperationServiceFlowEvent::query()
            ->where('operation_coordination_service_id', $coordinationId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Eventos de la orden y de las cotizaciones que alguna vez estuvieron ligadas a ella.
     *
     * @return Collection<int, OperationServiceFlowEvent>
     */
    public static function eventsForOrder(OperationServiceOrder $order, int $limit = 300): Collection
    {
        if (! self::tableExists() || ! $order->exists) {
            return collect();
        }

        $orderId = (int) $order->getKey();

        $quoteIds = OperationServiceFlowEvent::query()
            ->where('operation_service_order_id', $orderId)
            ->whereNotNull('operation_quote_generator_id')
            ->distinct()
            ->pluck('operation_quote_generator_id')
            ->push(OperationQuoteGenerator::query()->where('operation_service_order_id', $orderId)->value('id'))
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        return OperationServiceFlowEvent::query()
            ->where(function ($query) use ($orderId, $quoteIds): void {
                $query->where('operation_service_order_id', $orderId);

                if ($quoteIds !== []) {
                    $query->orWhereIn('operation_quote_generator_id', $quoteIds);
                }
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Aviso que llevan los documentos de una orden que ya no es válida (anulada,
     * cancelada o caducada), o null si la orden sigue vigente o se finalizó.
     *
     * @return array{stamp: string, title: string, detail: string}|null
     */
    public static function closingNoticeForOrder(OperationServiceOrder $order): ?array
    {
        $status = mb_strtoupper(trim((string) $order->status));

        if (! in_array($status, SupplierServiceOrderBuckets::CLOSED_UNFINISHED_STATUSES, true)) {
            return null;
        }

        $event = self::tableExists() && $order->exists
            ? OperationServiceFlowEvent::query()
                ->where('operation_service_order_id', $order->getKey())
                ->whereIn('event', [self::ORDER_VOIDED, self::ORDER_CANCELLED, self::ORDER_EXPIRED])
                ->latest('id')
                ->first()
            : null;

        $stamp = match (true) {
            $event?->event === self::ORDER_VOIDED => 'ANULADA',
            $status === OperationServiceOrderValidity::STATUS_EXPIRED => 'CADUCADA',
            default => 'CANCELADA',
        };

        $when = ($event?->created_at ?? $order->updated_at)?->timezone(config('app.timezone'))->format('d/m/Y H:i');
        $who = $event?->actor_name ?? (filled($order->updated_by) ? (string) $order->updated_by : null);

        $detail = trim(implode(' ', array_filter([
            $when !== null ? 'Fecha: '.$when.'.' : null,
            filled($who) ? 'Responsable: '.$who.'.' : null,
            filled($event?->reason) ? 'Motivo: '.trim((string) $event->reason) : null,
        ])));

        return [
            'stamp' => $stamp,
            'title' => 'ORDEN '.$stamp.': este documento NO es válido para prestar ni facturar el servicio.',
            'detail' => $detail,
        ];
    }

    /**
     * @param  Collection<int, OperationServiceFlowEvent>  $events
     */
    public static function renderTimeline(Collection $events, string $emptyText): HtmlString
    {
        if ($events->isEmpty()) {
            return new HtmlString(
                '<div class="rounded-2xl border border-dashed border-gray-300/80 px-4 py-3 text-sm text-gray-600 dark:border-white/15 dark:text-gray-300">'
                .e($emptyText)
                .'</div>'
            );
        }

        $rows = $events->map(function (OperationServiceFlowEvent $event): string {
            [$label, $tone] = self::eventBadge((string) $event->event);
            $reason = filled($event->reason)
                ? '<p class="mt-1 text-xs text-gray-700 dark:text-gray-300"><span class="font-semibold">Motivo:</span> '.nl2br(e((string) $event->reason)).'</p>'
                : '';

            return '<li class="relative flex gap-3 py-3">'
                .'<span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full '.$tone['dot'].'"></span>'
                .'<div class="min-w-0 flex-1">'
                .'<div class="flex flex-wrap items-center gap-x-2 gap-y-1">'
                .'<span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 '.$tone['badge'].'">'.e($label).'</span>'
                .'<span class="text-xs tabular-nums text-gray-500 dark:text-gray-400">'.e($event->created_at?->format('d/m/Y H:i:s') ?? '—').'</span>'
                .'<span class="text-xs text-gray-500 dark:text-gray-400">· '.e((string) ($event->actor_name ?: 'SISTEMA')).'</span>'
                .'</div>'
                .'<p class="mt-1 text-sm text-gray-900 dark:text-gray-100">'.e((string) $event->description).'</p>'
                .$reason
                .'</div>'
                .'</li>';
        })->implode('');

        return new HtmlString(
            '<ol class="divide-y divide-gray-200/80 dark:divide-white/10">'.$rows.'</ol>'
        );
    }

    /**
     * @return array{0: string, 1: array{dot: string, badge: string}}
     */
    public static function eventBadge(string $event): array
    {
        $green = ['dot' => 'bg-emerald-500', 'badge' => 'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-100 dark:ring-emerald-500/30'];
        $blue = ['dot' => 'bg-sky-500', 'badge' => 'bg-sky-50 text-sky-800 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-100 dark:ring-sky-500/30'];
        $amber = ['dot' => 'bg-amber-500', 'badge' => 'bg-amber-50 text-amber-900 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-100 dark:ring-amber-500/30'];
        $red = ['dot' => 'bg-rose-500', 'badge' => 'bg-rose-50 text-rose-800 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-100 dark:ring-rose-500/30'];
        $gray = ['dot' => 'bg-gray-400', 'badge' => 'bg-gray-50 text-gray-700 ring-gray-200 dark:bg-white/5 dark:text-gray-200 dark:ring-white/15'];

        return match ($event) {
            self::ORDER_CREATED => ['Orden creada', $green],
            self::ORDER_VOIDED => ['Orden anulada por error', $amber],
            self::ORDER_CANCELLED => ['Orden cancelada', $red],
            self::ORDER_EXPIRED => ['Orden caducada', $gray],
            self::ORDER_FINALIZED => ['Orden finalizada', $green],
            self::ORDER_STATUS_CHANGED => ['Cambio de estado', $blue],
            self::ORDER_PROVIDER_CHANGED => ['Cambio de proveedor', $amber],
            self::ORDER_ADMIN_STATUS_CHANGED => ['Estatus administrativo', $blue],
            self::QUOTE_CREATED => ['Cotización registrada', $blue],
            self::QUOTE_UPDATED => ['Cotización editada', $blue],
            self::QUOTE_STATUS_CHANGED => ['Estado de cotización', $blue],
            self::QUOTE_LINKED => ['Cotización vinculada', $green],
            self::QUOTE_RELEASED => ['Cotización liberada', $amber],
            self::RECEIVABLE_REOPENED => ['Cuenta por cobrar reabierta', $amber],
            self::ACTION_BLOCKED => ['Acción bloqueada', $red],
            default => [$event, $gray],
        };
    }

    public static function orderLabel(OperationServiceOrder|int|null $order): string
    {
        if ($order instanceof OperationServiceOrder) {
            return filled($order->order_number) ? (string) $order->order_number : '#'.$order->getKey();
        }

        if (! filled($order)) {
            return '—';
        }

        $number = OperationServiceOrder::query()->whereKey((int) $order)->value('order_number');

        return filled($number) ? (string) $number : '#'.$order;
    }

    public static function quoteLabel(OperationQuoteGenerator|int|null $quote): string
    {
        $id = $quote instanceof OperationQuoteGenerator ? (int) $quote->getKey() : (int) $quote;

        return $id > 0 ? CoordinationServiceQuoteManager::formatCoordinationQuoteNumber($id) : '—';
    }

    public static function supplierName(mixed $supplierId): string
    {
        if (! filled($supplierId)) {
            return 'sin proveedor';
        }

        $name = Supplier::query()->whereKey((int) $supplierId)->value('name');

        return filled($name) ? trim((string) $name) : 'proveedor #'.$supplierId;
    }

    public static function orderProviderLabel(OperationServiceOrder $order): string
    {
        if (filled($order->supplier_id)) {
            return self::supplierName($order->supplier_id);
        }

        if (filled($order->supplier_external)) {
            return trim((string) $order->supplier_external).' (no convenido)';
        }

        if (filled($order->doctor_nurse_id)) {
            return 'proveedor natural #'.$order->doctor_nurse_id;
        }

        return 'sin proveedor';
    }

    public static function money(mixed $amount): string
    {
        return is_numeric($amount) ? 'US$ '.number_format((float) $amount, 2, ',', '.') : '—';
    }

    /**
     * Solo se recuerda el «sí»: un worker que arrancó antes de la migración debe
     * empezar a registrar en cuanto la tabla exista, sin esperar a reiniciarse.
     */
    private static function tableExists(): bool
    {
        if (self::$tableExists === true) {
            return true;
        }

        return self::$tableExists = Schema::hasTable('operation_service_flow_events');
    }
}
