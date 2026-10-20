<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\OperationServiceOrder;
use App\Support\Operations\OperationServiceOrderValidity;
use App\Support\Operations\ServiceOrderFlowTrace;

/**
 * Deja en la traza cada alta y cada cambio de estado, proveedor o estatus
 * administrativo de una orden, sin importar qué pantalla, job o comando lo hizo.
 */
class OperationServiceOrderFlowTraceObserver
{
    public function created(OperationServiceOrder $order): void
    {
        $quoteId = ServiceOrderFlowTrace::context()['quote_id'] ?? null;
        $origin = filled($quoteId)
            ? ' desde la cotización '.ServiceOrderFlowTrace::quoteLabel((int) $quoteId)
            : ' sin cotización vinculada';

        ServiceOrderFlowTrace::record(
            ServiceOrderFlowTrace::ORDER_CREATED,
            sprintf(
                'Se creó la orden %s (%s)%s, con %s y estado %s.',
                ServiceOrderFlowTrace::orderLabel($order),
                filled($order->service_type) ? (string) $order->service_type : 'sin tipo',
                $origin,
                ServiceOrderFlowTrace::orderProviderLabel($order),
                filled($order->status) ? (string) $order->status : 'EN GESTION',
            ),
            ['order' => $order, 'quote' => filled($quoteId) ? (int) $quoteId : null],
        );
    }

    public function updated(OperationServiceOrder $order): void
    {
        if ($order->wasChanged('status')) {
            $this->recordStatusChange($order);
        }

        if ($order->wasChanged(['supplier_id', 'supplier_external', 'doctor_nurse_id'])) {
            $before = clone $order;
            $before->setRawAttributes($order->getOriginal());

            ServiceOrderFlowTrace::record(
                ServiceOrderFlowTrace::ORDER_PROVIDER_CHANGED,
                sprintf(
                    'La orden %s cambió de proveedor: %s → %s.',
                    ServiceOrderFlowTrace::orderLabel($order),
                    ServiceOrderFlowTrace::orderProviderLabel($before),
                    ServiceOrderFlowTrace::orderProviderLabel($order),
                ),
                ['order' => $order],
                changes: $this->diff($order, ['supplier_id', 'supplier_external', 'doctor_nurse_id']),
            );
        }

        if ($order->wasChanged('administrative_status')) {
            ServiceOrderFlowTrace::record(
                ServiceOrderFlowTrace::ORDER_ADMIN_STATUS_CHANGED,
                sprintf(
                    'El estatus administrativo de la orden %s pasó de %s a %s.',
                    ServiceOrderFlowTrace::orderLabel($order),
                    filled($order->getOriginal('administrative_status')) ? (string) $order->getOriginal('administrative_status') : 'PENDIENTE',
                    filled($order->administrative_status) ? (string) $order->administrative_status : 'PENDIENTE',
                ),
                ['order' => $order],
                changes: $this->diff($order, ['administrative_status']),
            );
        }
    }

    private function recordStatusChange(OperationServiceOrder $order): void
    {
        $from = mb_strtoupper(trim((string) $order->getOriginal('status')));
        $to = mb_strtoupper(trim((string) $order->status));
        $label = ServiceOrderFlowTrace::orderLabel($order);
        $action = ServiceOrderFlowTrace::contextAction();

        [$event, $description] = match (true) {
            in_array($to, ['CANCELADA', 'CANCELADO'], true) && $action === ServiceOrderFlowTrace::CONTEXT_VOID => [
                ServiceOrderFlowTrace::ORDER_VOIDED,
                sprintf('Se anuló por error la orden %s (%s → %s). Sus ítems volvieron a PENDIENTE para generar una nueva orden.', $label, $from, $to),
            ],
            in_array($to, ['CANCELADA', 'CANCELADO'], true) => [
                ServiceOrderFlowTrace::ORDER_CANCELLED,
                sprintf('Se canceló la orden %s (%s → %s). El servicio no se prestará.', $label, $from, $to),
            ],
            $to === OperationServiceOrderValidity::STATUS_EXPIRED => [
                ServiceOrderFlowTrace::ORDER_EXPIRED,
                sprintf('La orden %s caducó al vencer su vigencia (%s → %s).', $label, $from, $to),
            ],
            $to === 'FINALIZADO' => [
                ServiceOrderFlowTrace::ORDER_FINALIZED,
                sprintf('Se finalizó la orden %s (%s → %s).', $label, $from, $to),
            ],
            default => [
                ServiceOrderFlowTrace::ORDER_STATUS_CHANGED,
                sprintf('La orden %s cambió de estado: %s → %s.', $label, $from !== '' ? $from : '—', $to !== '' ? $to : '—'),
            ],
        };

        ServiceOrderFlowTrace::record($event, $description, ['order' => $order], changes: $this->diff($order, ['status']));

        // El PDF guardado de una orden anulada o caducada se descarta para que se vuelva
        // a generar con la marca de anulación y nadie reenvíe la versión vigente.
        if (in_array($to, ['CANCELADA', 'CANCELADO', OperationServiceOrderValidity::STATUS_EXPIRED], true)
            && filled($order->service_order_pdf_path)) {
            $order->service_order_pdf_path = null;
            $order->saveQuietly();
        }
    }

    /**
     * @param  list<string>  $attributes
     * @return array<string, array{antes: mixed, despues: mixed}>
     */
    private function diff(OperationServiceOrder $order, array $attributes): array
    {
        $changes = [];

        foreach ($attributes as $attribute) {
            if ($order->wasChanged($attribute)) {
                $changes[$attribute] = [
                    'antes' => $order->getOriginal($attribute),
                    'despues' => $order->getAttribute($attribute),
                ];
            }
        }

        return $changes;
    }
}
