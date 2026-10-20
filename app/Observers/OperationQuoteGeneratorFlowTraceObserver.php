<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\OperationQuoteGenerator;
use App\Support\Operations\ServiceOrderFlowTrace;

/**
 * Deja en la traza el alta de cada cotización y los cambios que importan:
 * estado, vínculo con una orden, proveedor y montos.
 */
class OperationQuoteGeneratorFlowTraceObserver
{
    private const AMOUNT_ATTRIBUTES = ['supplier_id', 'total', 'subtotal', 'costo_dolares', 'porcentaje_ganancia', 'items'];

    public function created(OperationQuoteGenerator $quote): void
    {
        ServiceOrderFlowTrace::record(
            ServiceOrderFlowTrace::QUOTE_CREATED,
            sprintf(
                'Se registró la cotización %s (%s) por %s con %s, estado %s.%s',
                ServiceOrderFlowTrace::quoteLabel($quote),
                filled($quote->type_service) ? (string) $quote->type_service : 'sin tipo',
                ServiceOrderFlowTrace::money($quote->total),
                ServiceOrderFlowTrace::supplierName($quote->supplier_id),
                filled($quote->status) ? (string) $quote->status : OperationQuoteGenerator::STATUS_PENDING,
                $this->itemsSummary($quote),
            ),
            ['quote' => $quote],
        );
    }

    public function updated(OperationQuoteGenerator $quote): void
    {
        $label = ServiceOrderFlowTrace::quoteLabel($quote);

        if ($quote->wasChanged('status')) {
            ServiceOrderFlowTrace::record(
                ServiceOrderFlowTrace::QUOTE_STATUS_CHANGED,
                sprintf(
                    'La cotización %s pasó de %s a %s.',
                    $label,
                    filled($quote->getOriginal('status')) ? (string) $quote->getOriginal('status') : OperationQuoteGenerator::STATUS_PENDING,
                    (string) $quote->status,
                ),
                ['quote' => $quote, 'order' => $quote->operation_service_order_id],
                changes: ['status' => ['antes' => $quote->getOriginal('status'), 'despues' => $quote->status]],
            );
        }

        if ($quote->wasChanged('operation_service_order_id')) {
            $previousOrderId = $quote->getOriginal('operation_service_order_id');
            $currentOrderId = $quote->operation_service_order_id;
            $changes = ['operation_service_order_id' => ['antes' => $previousOrderId, 'despues' => $currentOrderId]];

            if (filled($currentOrderId)) {
                ServiceOrderFlowTrace::record(
                    ServiceOrderFlowTrace::QUOTE_LINKED,
                    sprintf('La cotización %s quedó vinculada a la orden %s.', $label, ServiceOrderFlowTrace::orderLabel((int) $currentOrderId)),
                    ['quote' => $quote, 'order' => (int) $currentOrderId],
                    changes: $changes,
                );
            } else {
                ServiceOrderFlowTrace::record(
                    ServiceOrderFlowTrace::QUOTE_RELEASED,
                    sprintf(
                        'La cotización %s se desvinculó de la orden %s y quedó %s.',
                        $label,
                        ServiceOrderFlowTrace::orderLabel(filled($previousOrderId) ? (int) $previousOrderId : null),
                        (string) $quote->status,
                    ),
                    ['quote' => $quote, 'order' => filled($previousOrderId) ? (int) $previousOrderId : null],
                    changes: $changes,
                );
            }
        }

        if ($quote->wasChanged(self::AMOUNT_ATTRIBUTES)) {
            $changes = [];

            foreach (self::AMOUNT_ATTRIBUTES as $attribute) {
                if ($attribute !== 'items' && $quote->wasChanged($attribute)) {
                    $changes[$attribute] = ['antes' => $quote->getOriginal($attribute), 'despues' => $quote->getAttribute($attribute)];
                }
            }

            ServiceOrderFlowTrace::record(
                ServiceOrderFlowTrace::QUOTE_UPDATED,
                sprintf(
                    'Se editó la cotización %s: total %s → %s, proveedor %s → %s.',
                    $label,
                    ServiceOrderFlowTrace::money($quote->getOriginal('total')),
                    ServiceOrderFlowTrace::money($quote->total),
                    ServiceOrderFlowTrace::supplierName($quote->getOriginal('supplier_id')),
                    ServiceOrderFlowTrace::supplierName($quote->supplier_id),
                ),
                ['quote' => $quote],
                changes: $changes,
            );
        }
    }

    private function itemsSummary(OperationQuoteGenerator $quote): string
    {
        $labels = collect(is_array($quote->items) ? $quote->items : [])
            ->map(fn (mixed $item): string => is_array($item) ? trim((string) ($item['label'] ?? '')) : '')
            ->filter()
            ->values();

        if ($labels->isEmpty()) {
            return '';
        }

        return ' Ítems: '.$labels->take(10)->implode(', ').($labels->count() > 10 ? ' y '.($labels->count() - 10).' más' : '').'.';
    }
}
