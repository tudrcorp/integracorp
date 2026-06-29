<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationCoordinationService;
use App\Models\OperationServiceOrder;

/**
 * Reúne, para una coordinación, todos los documentos cargados tanto en la propia
 * coordinación como en cada orden de servicio asociada, de modo que la pestaña
 * "Documentos" muestre el listado completo de todos los servicios asociados.
 */
final class CoordinationServiceDocumentsAggregator
{
    /**
     * @return list<array{document_name: string, file_path: string, document_type_ids: list<int>, document_types: list<string>, service_item_keys: list<string>, services: list<string>, service: string, source: string, uploaded_at: ?string}>
     */
    public static function forCoordination(OperationCoordinationService $record): array
    {
        $orderGroups = OperationServiceOrder::query()
            ->where('operation_coordination_service_id', $record->id)
            ->orderBy('id', 'asc')
            ->get(['id', 'order_number', 'service_type', 'uploaded_documents'])
            ->map(static fn (OperationServiceOrder $order): array => [
                'label' => self::orderServiceLabel($order),
                'documents' => self::normalizeDocuments($order->uploaded_documents),
            ])
            ->all();

        return self::buildRows(
            self::normalizeDocuments($record->uploaded_documents),
            $orderGroups
        );
    }

    /**
     * @param  list<array<string, mixed>>  $coordinationDocuments
     * @param  list<array{label: string, documents: list<array<string, mixed>>}>  $orderGroups
     * @return list<array{document_name: string, file_path: string, document_type_ids: list<int>, document_types: list<string>, service_item_keys: list<string>, services: list<string>, service: string, source: string, uploaded_at: ?string}>
     */
    public static function buildRows(array $coordinationDocuments, array $orderGroups): array
    {
        $documents = [];

        foreach ($coordinationDocuments as $document) {
            if (! is_array($document)) {
                continue;
            }

            $documents[] = self::buildRow($document, [], 'Coordinación');
        }

        foreach ($orderGroups as $group) {
            $label = trim((string) ($group['label'] ?? ''));
            $label = $label !== '' ? $label : 'Orden de servicio';

            foreach (self::normalizeDocuments($group['documents'] ?? []) as $document) {
                $documents[] = self::buildRow($document, [$label], 'Orden de servicio');
            }
        }

        usort(
            $documents,
            static fn (array $a, array $b): int => strcmp((string) ($b['uploaded_at'] ?? ''), (string) ($a['uploaded_at'] ?? ''))
        );

        return array_values($documents);
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<int, string>  $defaultServices
     * @return array{document_name: string, file_path: string, document_type_ids: list<int>, document_types: list<string>, service_item_keys: list<string>, services: list<string>, service: string, source: string, uploaded_at: ?string}
     */
    private static function buildRow(array $document, array $defaultServices, string $source): array
    {
        $services = self::stringList($document['services'] ?? []);

        if ($services === []) {
            $services = array_values(array_filter(
                $defaultServices,
                static fn (string $value): bool => trim($value) !== ''
            ));
        }

        return [
            'document_name' => trim((string) ($document['document_name'] ?? '')) !== ''
                ? trim((string) $document['document_name'])
                : 'Documento sin nombre',
            'file_path' => trim((string) ($document['file_path'] ?? '')),
            'document_type_ids' => self::intList($document['document_type_ids'] ?? []),
            'document_types' => self::stringList($document['document_types'] ?? []),
            'service_item_keys' => self::stringList($document['service_item_keys'] ?? []),
            'services' => $services,
            'service' => implode('; ', $services),
            'source' => $source,
            'uploaded_at' => isset($document['uploaded_at']) ? (string) $document['uploaded_at'] : null,
        ];
    }

    private static function orderServiceLabel(OperationServiceOrder $order): string
    {
        $serviceType = trim((string) ($order->service_type ?? ''));
        $orderNumber = trim((string) ($order->order_number ?? ''));

        $parts = array_values(array_filter([
            $serviceType !== '' ? $serviceType : null,
            $orderNumber !== '' ? $orderNumber : null,
        ]));

        return $parts === [] ? 'Orden de servicio' : implode(' · ', $parts);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function normalizeDocuments(mixed $documents): array
    {
        if (! is_array($documents)) {
            return [];
        }

        return array_values(array_filter(
            $documents,
            static fn (mixed $document): bool => is_array($document)
        ));
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(static fn (mixed $item): string => trim((string) $item))
            ->filter(static fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private static function intList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(static fn (mixed $item): int => is_numeric($item) ? (int) $item : 0)
            ->filter(static fn (int $item): bool => $item > 0)
            ->unique()
            ->values()
            ->all();
    }
}
