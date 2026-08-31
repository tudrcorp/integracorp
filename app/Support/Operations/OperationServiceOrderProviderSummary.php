<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\DoctorNurse;
use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Models\Supplier;
use Illuminate\Support\Collection;

final class OperationServiceOrderProviderSummary
{
    /**
     * @var array<int, array<string, array{name: ?string, rif: ?string, order_id: ?int, quote_id: ?int}>>
     */
    private static array $providersByClinicalLookupCache = [];

    public static function name(OperationServiceOrder $order): ?string
    {
        self::loadProviderRelations($order);

        if (filled($order->supplier?->name)) {
            return trim((string) $order->supplier->name);
        }

        if (filled($order->doctorNurse?->name)) {
            return trim((string) $order->doctorNurse->name);
        }

        if (filled($order->supplier_external)) {
            return trim((string) $order->supplier_external);
        }

        return null;
    }

    public static function nameOrDash(OperationServiceOrder $order): string
    {
        return self::name($order) ?? '—';
    }

    public static function rif(OperationServiceOrder $order): ?string
    {
        self::loadProviderRelations($order);

        return self::identityFromOrder($order)['rif'];
    }

    /**
     * @return array<string, array{name: ?string, rif: ?string, order_id: ?int, quote_id: ?int}>
     */
    public static function managementProvidersByClinicalLookup(OperationCoordinationService $record): array
    {
        $cacheKey = $record->getKey();

        if (is_int($cacheKey) && $cacheKey > 0 && isset(self::$providersByClinicalLookupCache[$cacheKey])) {
            return self::$providersByClinicalLookupCache[$cacheKey];
        }

        if ($record->exists) {
            $record->loadMissing([
                'operationServiceOrders.operationServiceOrderItems',
                'operationServiceOrders.supplier',
                'operationServiceOrders.doctorNurse',
                'operationServiceOrders.approvedOperationQuote',
                'operationQuoteGenerators.supplier',
            ]);
        }

        $map = [];

        foreach (self::relatedCollection($record, 'operationServiceOrders')->sortByDesc(
            static fn (mixed $order): int => $order instanceof OperationServiceOrder ? (int) ($order->id ?? 0) : 0
        ) as $order) {
            if (! $order instanceof OperationServiceOrder || self::isVoidedOrExpiredServiceOrder($order)) {
                continue;
            }

            $identity = self::identityFromOrder($order);
            $payload = [
                'name' => $identity['name'],
                'rif' => $identity['rif'],
                'order_id' => (int) ($order->id ?? 0) > 0 ? (int) $order->id : null,
                'quote_id' => $order->relationLoaded('approvedOperationQuote') && $order->approvedOperationQuote instanceof OperationQuoteGenerator
                    ? (int) $order->approvedOperationQuote->id
                    : null,
            ];

            $orderItems = $order->relationLoaded('operationServiceOrderItems')
                ? $order->operationServiceOrderItems
                : collect();

            foreach ($orderItems as $orderItem) {
                $serviceType = mb_strtoupper(trim((string) ($orderItem->category ?? '')));
                $itemName = mb_strtoupper(trim((string) ($orderItem->item_name ?? '')));

                if ($serviceType === '' || $itemName === '') {
                    continue;
                }

                self::assignLookup($map, $serviceType.'|'.$itemName, $payload);
            }
        }

        foreach (self::relatedCollection($record, 'operationQuoteGenerators')->sortByDesc(
            static fn (mixed $quote): int => $quote instanceof OperationQuoteGenerator ? (int) ($quote->id ?? 0) : 0
        ) as $quote) {
            if (! $quote instanceof OperationQuoteGenerator || self::isRejectedQuote($quote)) {
                continue;
            }

            $identity = self::identityFromSupplier(
                $quote->relationLoaded('supplier') ? $quote->supplier : null
            ) ?? ['name' => null, 'rif' => null];
            $payload = [
                'name' => $identity['name'],
                'rif' => $identity['rif'],
                'order_id' => filled($quote->operation_service_order_id) ? (int) $quote->operation_service_order_id : null,
                'quote_id' => (int) ($quote->id ?? 0) > 0 ? (int) $quote->id : null,
            ];

            $items = is_array($quote->items) ? $quote->items : [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $itemKey = trim((string) ($item['key'] ?? ''));

                if ($itemKey !== '') {
                    self::assignLookup($map, $itemKey, $payload);
                }

                $category = trim((string) ($item['category'] ?? ''));
                $label = trim((string) ($item['label'] ?? ''));

                if ($category === '' || $label === '') {
                    continue;
                }

                self::assignLookup(
                    $map,
                    CoordinationServiceItemsManager::clinicalItemServiceOrderKey($category, $label),
                    $payload
                );
            }
        }

        if (is_int($cacheKey) && $cacheKey > 0) {
            self::$providersByClinicalLookupCache[$cacheKey] = $map;
        }

        return $map;
    }

    /**
     * @param  array<string, array{name: ?string, rif: ?string, order_id?: ?int, quote_id?: ?int}>  $lookup
     * @return array{name: ?string, rif: ?string, order_id: ?int, quote_id: ?int}
     */
    public static function providerForClinicalItem(
        array $lookup,
        string $category,
        string $label,
        string $managementKey,
    ): array {
        $keys = [
            $managementKey,
            CoordinationServiceItemsManager::clinicalItemServiceOrderKey($category, $label),
        ];

        if ($category === 'Servicio') {
            $keys[] = CoordinationServiceItemsManager::clinicalItemServiceOrderKey('Especialista', $label);
        }

        foreach ($keys as $key) {
            if ($key !== '' && isset($lookup[$key])) {
                return [
                    'name' => $lookup[$key]['name'] ?? null,
                    'rif' => $lookup[$key]['rif'] ?? null,
                    'order_id' => $lookup[$key]['order_id'] ?? null,
                    'quote_id' => $lookup[$key]['quote_id'] ?? null,
                ];
            }
        }

        return [
            'name' => null,
            'rif' => null,
            'order_id' => null,
            'quote_id' => null,
        ];
    }

    public static function address(OperationServiceOrder $order): ?string
    {
        self::loadProviderRelations($order);

        if (filled($order->approvedOperationQuote?->supplier_address)) {
            return trim((string) $order->approvedOperationQuote->supplier_address);
        }

        if ($order->supplier instanceof Supplier) {
            return self::addressFromSupplier($order->supplier);
        }

        if ($order->doctorNurse instanceof DoctorNurse) {
            return self::addressFromDoctorNurse($order->doctorNurse);
        }

        return null;
    }

    public static function addressOrDash(OperationServiceOrder $order): string
    {
        return self::address($order) ?? '—';
    }

    public static function phone(OperationServiceOrder $order): ?string
    {
        self::loadProviderRelations($order);

        $contacts = OperationServiceOrderProviderContacts::fromModels(
            $order->doctorNurse instanceof DoctorNurse ? $order->doctorNurse : null,
            $order->supplier instanceof Supplier ? $order->supplier : null,
        );

        if (filled($contacts['phone'])) {
            return $contacts['phone'];
        }

        if ($order->relationLoaded('medicalAppointment') && filled($order->medicalAppointment?->supplier_notify_phone)) {
            return trim((string) $order->medicalAppointment->supplier_notify_phone);
        }

        return null;
    }

    public static function phoneOrDash(OperationServiceOrder $order): string
    {
        return self::phone($order) ?? '—';
    }

    /**
     * @param  list<string>  $relations
     */
    private static function loadMissingIfNeeded(OperationServiceOrder|Supplier $model, array $relations): void
    {
        $missing = array_values(array_filter(
            $relations,
            static fn (string $relation): bool => ! $model->relationLoaded($relation)
        ));

        if ($missing === []) {
            return;
        }

        $model->loadMissing($missing);
    }

    private static function loadProviderRelations(OperationServiceOrder $order): void
    {
        self::loadMissingIfNeeded($order, [
            'approvedOperationQuote',
            'supplier',
            'doctorNurse',
        ]);

        if ($order->supplier instanceof Supplier) {
            self::loadMissingIfNeeded($order->supplier, ['state', 'city']);
        }
    }

    public static function addressFromSupplier(Supplier $supplier): ?string
    {
        if (filled($supplier->ubicacion_principal)) {
            return trim((string) $supplier->ubicacion_principal);
        }

        return self::composeLocation(
            $supplier->state?->definition,
            $supplier->city?->definition,
        );
    }

    public static function addressFromDoctorNurse(DoctorNurse $doctorNurse): ?string
    {
        if (filled($doctorNurse->ubicacion_principal)) {
            return trim((string) $doctorNurse->ubicacion_principal);
        }

        return self::composeLocation(
            $doctorNurse->getAttributes()['state'] ?? null,
            $doctorNurse->getAttributes()['city'] ?? null,
        );
    }

    private static function composeLocation(mixed $state, mixed $city): ?string
    {
        $parts = array_values(array_filter([
            filled($state) ? trim((string) $state) : null,
            filled($city) ? trim((string) $city) : null,
        ]));

        return $parts === [] ? null : implode(' — ', $parts);
    }

    /**
     * @return array{name: ?string, rif: ?string}
     */
    private static function identityFromOrder(OperationServiceOrder $order): array
    {
        $name = null;
        $rif = null;

        if ($order->relationLoaded('supplier') && $order->supplier instanceof Supplier) {
            $name = filled($order->supplier->name) ? trim((string) $order->supplier->name) : null;
            $rif = self::normalizeDocument($order->supplier->rif);
        }

        if ($name === null && $order->relationLoaded('doctorNurse') && $order->doctorNurse instanceof DoctorNurse) {
            $name = filled($order->doctorNurse->name) ? trim((string) $order->doctorNurse->name) : null;
            $rif = $rif ?? self::normalizeDocument($order->doctorNurse->rif);
        }

        if ($name === null && filled($order->supplier_external)) {
            $name = trim((string) $order->supplier_external);
        }

        return [
            'name' => $name,
            'rif' => $rif,
        ];
    }

    /**
     * @return array{name: string, rif: ?string}|null
     */
    private static function identityFromSupplier(?Supplier $supplier): ?array
    {
        if (! $supplier instanceof Supplier || ! filled($supplier->name)) {
            return null;
        }

        return [
            'name' => trim((string) $supplier->name),
            'rif' => self::normalizeDocument($supplier->rif),
        ];
    }

    private static function normalizeDocument(mixed $value): ?string
    {
        $document = trim((string) $value);

        return $document !== '' ? mb_strtoupper($document) : null;
    }

    /**
     * @return Collection<int, mixed>
     */
    private static function relatedCollection(OperationCoordinationService $record, string $relation): Collection
    {
        if (! $record->relationLoaded($relation)) {
            return collect();
        }

        return Collection::wrap($record->getRelation($relation));
    }

    private static function isVoidedOrExpiredServiceOrder(OperationServiceOrder $order): bool
    {
        $status = mb_strtoupper(trim((string) ($order->status ?? '')));

        return in_array($status, [
            'CANCELADA',
            'CANCELADO',
            OperationServiceOrderValidity::STATUS_EXPIRED,
        ], true);
    }

    private static function isRejectedQuote(OperationQuoteGenerator $quote): bool
    {
        return mb_strtoupper(trim((string) ($quote->status ?? ''))) === OperationQuoteGenerator::STATUS_REJECTED;
    }

    /**
     * @param  array<string, array{name: ?string, rif: ?string, order_id: ?int, quote_id: ?int}>  $map
     * @param  array{name: ?string, rif: ?string, order_id: ?int, quote_id: ?int}  $incoming
     */
    private static function assignLookup(array &$map, string $key, array $incoming): void
    {
        if ($key === '') {
            return;
        }

        if (! isset($map[$key])) {
            $map[$key] = $incoming;

            return;
        }

        $current = $map[$key];
        $map[$key] = [
            'name' => $current['name'] ?? $incoming['name'],
            'rif' => $current['rif'] ?? $incoming['rif'],
            'order_id' => $current['order_id'] ?? $incoming['order_id'],
            'quote_id' => $current['quote_id'] ?? $incoming['quote_id'],
        ];
    }
}
