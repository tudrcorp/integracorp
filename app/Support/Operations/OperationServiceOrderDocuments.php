<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\OperationDocumentList;
use App\Models\OperationServiceOrder;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\SecurityAudit;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Documentos de una orden de servicio y su finalización.
 *
 * Única vía para cargar documentos y finalizar una orden, la use la vista de la
 * orden o la ficha del proveedor: así ambas pantallas validan, guardan y dejan
 * traza de la misma forma.
 *
 * Finalizar es todo o nada, dentro de una transacción con la orden bloqueada:
 * documentos, seguimiento por resultados, ítems clínicos, coordinación y bitácora
 * del caso. Si algo falla no queda nada a medias, y se borran los archivos que se
 * acababan de subir. Bloquear la fila impide que dos analistas finalicen la misma
 * orden a la vez.
 */
final class OperationServiceOrderDocuments
{
    public const ORIGIN_ORDER_VIEW = 'Vista de la orden de servicio';

    public const ORIGIN_SUPPLIER = 'Órdenes de servicio del proveedor';

    public const FINALIZE_BITACORA_PREFIX = 'ORDEN DE SERVICIO FINALIZADA';

    public const AUDIT_FINALIZED = 'AUDIT_OPERATIONS_SERVICE_ORDER_FINALIZED';

    public const AUDIT_ROUTE = 'operations.service-orders.finalize';

    public const MAX_FILE_KB = 10240;

    /**
     * Tipo de documento que toda orden de medicamentos necesita para finalizarse,
     * salga del inventario propio o la entregue una farmacia.
     */
    public const MEDICATION_DELIVERY_RECEIPT = CoordinationServiceCoveredItemsFinalizer::MEDICATION_DELIVERY_RECEIPT_DOCUMENT_NAME;

    /**
     * Campo opcional del formulario: ubicación desde la que salen los medicamentos del inventario.
     */
    public const INVENTORY_UBICATION_FIELD = 'inventory_ubication_id';

    /**
     * Permiso granular «Finalizar órdenes de servicio» (SUPERADMIN siempre puede).
     */
    public static function userCanFinalize(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::FINALIZE_OPERATION_SERVICE_ORDER);
    }

    public static function directory(OperationServiceOrder|int $order): string
    {
        $id = $order instanceof OperationServiceOrder ? (int) $order->getKey() : $order;

        return 'operation-service-orders/'.$id.'/documents';
    }

    /**
     * Ubicación de despacho y aviso de comprobante, solo en órdenes de medicamentos.
     * Se exige al finalizar (no al solo guardar documentos), por eso la validación
     * dura vive en {@see finalize()}.
     *
     * @return list<Select|Placeholder>
     */
    public static function medicationDeliverySchema(OperationServiceOrder $order): array
    {
        if (mb_strtoupper(trim((string) $order->service_type)) !== 'MEDICAMENTOS') {
            return [];
        }

        $summary = ServiceOrderMedicationInventory::pendingSummary($order);

        return [
            Placeholder::make('medication_delivery_notice')
                ->hiddenLabel()
                ->content(new HtmlString(
                    '<div class="rounded-xl border border-sky-200/80 bg-sky-50/80 px-4 py-3 text-sm text-sky-950 dark:border-sky-500/25 dark:bg-sky-950/25 dark:text-sky-50">'
                    .'Para finalizar, incluya un documento de tipo <strong>'.e(self::MEDICATION_DELIVERY_RECEIPT).'</strong>.'
                    .($summary !== ''
                        ? '<br>Al finalizar saldrán del inventario: <strong>'.e($summary).'</strong>.'
                        : '<br>Ningún medicamento de esta orden sale del inventario propio.')
                    .'</div>'
                ))
                ->columnSpanFull(),
            Select::make(self::INVENTORY_UBICATION_FIELD)
                ->label('Ubicación de despacho')
                ->helperText('Almacén desde el que se entregan los medicamentos. Se descuenta al finalizar la orden.')
                ->options(fn (): array => ServiceOrderMedicationInventory::ubicationOptions())
                ->default(fn (): ?int => ServiceOrderMedicationInventory::suggestedUbicationId($order))
                ->native(false)
                ->visible($summary !== ''),
        ];
    }

    /**
     * Formulario de carga: uno o varios archivos, cada uno con sus tipos.
     *
     * El archivo se guarda con nombre único (un segundo archivo con el mismo
     * nombre ya no pisa al primero) y el nombre original se conserva para
     * mostrarlo.
     *
     * @param  Closure(): string  $directory
     * @return list<Repeater>
     */
    public static function formSchema(Closure $directory): array
    {
        return [
            Repeater::make('documents')
                ->label('Documentos')
                ->defaultItems(1)
                ->addActionLabel('Agregar documento')
                ->reorderable()
                ->minItems(1)
                ->schema([
                    Select::make('document_type_ids')
                        ->label('Tipo(s) de documento')
                        ->helperText('Seleccione uno o varios tipos según la información contenida en el archivo.')
                        ->options(fn (): array => OperationDocumentList::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->multiple()
                        ->required()
                        ->validationMessages([
                            'required' => 'Indique al menos un tipo de documento.',
                        ]),
                    FileUpload::make('document_file')
                        ->label('Archivo')
                        ->disk('public')
                        ->directory($directory)
                        ->storeFileNamesIn('document_original_name')
                        ->required()
                        ->maxSize(self::MAX_FILE_KB)
                        ->helperText('PDF, imagen u otro documento. Máximo 10 MB.')
                        ->validationMessages([
                            'required' => 'Adjunte el archivo.',
                            'max' => 'El archivo no puede superar los 10 MB.',
                        ]),
                ])
                ->columns(1)
                ->required(),
        ];
    }

    /**
     * Normaliza los documentos del formulario. Descarta los incompletos.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{document_name: string, file_path: string, document_type_ids: list<int>, document_types: list<string>, uploaded_at: string}>
     */
    public static function fromForm(array $data): array
    {
        /** @var array<int, string> $typeNames */
        $typeNames = OperationDocumentList::query()
            ->pluck('name', 'id')
            ->mapWithKeys(static fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();

        $documents = [];

        foreach ((array) ($data['documents'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $file = $item['document_file'] ?? '';
            $file = trim((string) (is_array($file) ? (reset($file) ?: '') : $file));

            if ($file === '') {
                continue;
            }

            $original = $item['document_original_name'] ?? '';
            $original = trim((string) (is_array($original) ? (reset($original) ?: '') : $original));
            $name = trim((string) pathinfo($original !== '' ? $original : $file, PATHINFO_FILENAME));

            $rawTypeIds = is_array($item['document_type_ids'] ?? null) ? $item['document_type_ids'] : [];
            $typeIds = array_values(array_unique(array_filter(
                array_map(static fn (mixed $value): int => is_numeric($value) ? (int) $value : 0, $rawTypeIds),
                static fn (int $id): bool => $id > 0,
            )));

            $documents[] = [
                'document_name' => $name !== '' ? $name : basename($file),
                'file_path' => $file,
                'document_type_ids' => $typeIds,
                'document_types' => array_values(array_filter(
                    array_map(static fn (int $id): string => $typeNames[$id] ?? '', $typeIds),
                    static fn (string $value): bool => $value !== '',
                )),
                'uploaded_at' => now()->toDateTimeString(),
            ];
        }

        return $documents;
    }

    /**
     * Agrega documentos sin finalizar. Con la orden bloqueada para no perder los
     * de otro analista que cargue a la vez.
     *
     * @param  list<array<string, mixed>>  $documents
     * @return array{order: OperationServiceOrder, follow_up_suffix: string}
     */
    public static function attach(OperationServiceOrder $order, array $documents): array
    {
        self::assertHasDocuments($documents);

        try {
            return DB::transaction(function () use ($order, $documents): array {
                $locked = self::lock($order);
                $locked->update(['uploaded_documents' => self::merge($locked, $documents)]);

                return [
                    'order' => $locked->fresh() ?? $locked,
                    'follow_up_suffix' => self::registerFollowUp($locked, $documents),
                ];
            });
        } catch (Throwable $exception) {
            self::discardUploadedFiles($order, $documents);

            throw $exception;
        }
    }

    /**
     * Guarda los documentos y finaliza la orden, con traza en la bitácora del caso
     * y en la auditoría del sistema.
     *
     * @param  list<array<string, mixed>>  $documents
     * @return array{order: OperationServiceOrder, follow_up_suffix: string, logged_to_case: bool}
     *
     * @throws OperationServiceOrderFinalizationException
     */
    public static function finalize(OperationServiceOrder $order, array $documents, User $user, string $origin, ?int $inventoryUbicationId = null): array
    {
        try {
            // El permiso se exige aquí y no solo en el botón: ninguna vía finaliza sin él.
            if (! self::userCanFinalize()) {
                throw new OperationServiceOrderFinalizationException('No tiene permiso para finalizar órdenes de servicio. Un SUPERADMIN puede asignárselo en Permisos → Operaciones → «Finalizar órdenes de servicio».');
            }

            self::assertHasDocuments($documents);

            // Una orden vencida se marca CADUCADA fuera de la transacción: si se
            // hiciera dentro, el rechazo revertiría también la caducidad.
            if (OperationServiceOrderValidity::expireIfNeeded($order)) {
                throw new OperationServiceOrderFinalizationException('La orden superó su vigencia de '.OperationServiceOrderValidity::VALIDITY_DAYS.' días y quedó CADUCADA: ya no puede finalizarse.');
            }

            $result = DB::transaction(function () use ($order, $documents, $user, $origin, $inventoryUbicationId): array {
                $locked = self::lock($order);

                if (! OperationServiceOrderViewActions::canFinalize($locked)) {
                    throw new OperationServiceOrderFinalizationException(
                        'La orden '.self::orderLabel($locked).' ya no está en gestión (estatus '.($locked->status ?: '—').'). Es posible que otro analista la haya cerrado.'
                    );
                }

                $mergedDocuments = self::merge($locked, $documents);
                self::assertMedicationDeliveryReceipt($locked, $mergedDocuments);

                $previousStatus = (string) $locked->status;
                $userName = filled($user->name) ? (string) $user->name : 'SISTEMA';
                // Antes de finalizar: la salida necesita los medicamentos aún en gestión.
                $dispatched = ServiceOrderMedicationInventory::dispatch($locked, $inventoryUbicationId, $userName);
                $locked->update(['uploaded_documents' => $mergedDocuments]);
                $followUpSuffix = self::registerFollowUp($locked, $documents);
                $finalized = OperationServiceOrderCoordinationSync::finalizeOrder($locked);
                $loggedToCase = self::writeBitacora($finalized, $documents, $user, $origin, $dispatched);

                return [
                    'order' => $finalized,
                    'follow_up_suffix' => $followUpSuffix,
                    'logged_to_case' => $loggedToCase,
                    'previous_status' => $previousStatus,
                ];
            });
        } catch (Throwable $exception) {
            self::discardUploadedFiles($order, $documents);

            throw $exception;
        }

        /** @var OperationServiceOrder $finalized */
        $finalized = $result['order'];

        SecurityAudit::log(self::AUDIT_FINALIZED, self::AUDIT_ROUTE, [
            'operation_service_order_id' => $finalized->getKey(),
            'order_number' => $finalized->order_number,
            'service_type' => $finalized->service_type,
            'supplier_id' => $finalized->supplier_id,
            'operation_coordination_service_id' => $finalized->operation_coordination_service_id,
            'telemedicine_case_id' => $finalized->operationCoordinationService?->telemedicine_case_id,
            'previous_status' => $result['previous_status'],
            'origin' => $origin,
            'inventory_ubication_id' => $finalized->operation_inventory_ubication_id,
            'documents' => array_map(static fn (array $document): string => (string) ($document['document_name'] ?? ''), $documents),
        ], $user);

        return [
            'order' => $finalized,
            'follow_up_suffix' => $result['follow_up_suffix'],
            'logged_to_case' => $result['logged_to_case'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     */
    /**
     * @param  list<string>  $dispatchedMedications
     */
    public static function buildFinalizeBitacora(OperationServiceOrder $order, array $documents, string $analystName, string $origin, array $dispatchedMedications = []): string
    {
        $lines = array_map(static function (array $document): string {
            $types = array_values(array_filter((array) ($document['document_types'] ?? [])));

            return '- '.($document['document_name'] ?? 'Documento').($types !== [] ? ' ('.implode(', ', $types).')' : '');
        }, $documents);

        return self::FINALIZE_BITACORA_PREFIX
            ."\n".'Orden: '.self::orderLabel($order)
            ."\n".'Tipo: '.(filled($order->service_type) ? (string) $order->service_type : '—')
            ."\n".'Proveedor: '.(filled($order->supplier?->name) ? (string) $order->supplier->name : '—')
            ."\n".'Analista: '.$analystName
            ."\n".'Desde: '.$origin
            ."\n".'Documentos:'
            ."\n".implode("\n", $lines)
            .($dispatchedMedications !== []
                ? "\n".'Salida de inventario:'."\n".implode("\n", array_map(static fn (string $line): string => '- '.$line, $dispatchedMedications))
                : '');
    }

    /**
     * Toda orden de medicamentos se cierra con el comprobante de entrega, cargado ahora o antes.
     *
     * @param  list<array<string, mixed>>  $documents
     */
    public static function assertMedicationDeliveryReceipt(OperationServiceOrder $order, array $documents): void
    {
        if (mb_strtoupper(trim((string) $order->service_type)) !== 'MEDICAMENTOS') {
            return;
        }

        foreach ($documents as $document) {
            $types = array_map(
                static fn (mixed $type): string => mb_strtoupper(trim((string) $type)),
                (array) ($document['document_types'] ?? []),
            );

            if (in_array(self::MEDICATION_DELIVERY_RECEIPT, $types, true)) {
                return;
            }
        }

        throw new OperationServiceOrderFinalizationException(
            'Para finalizar una orden de medicamentos cargue el comprobante de entrega: un documento de tipo «'.self::MEDICATION_DELIVERY_RECEIPT.'».'
        );
    }

    public static function orderLabel(OperationServiceOrder $order): string
    {
        return filled($order->order_number) ? (string) $order->order_number : '#'.$order->getKey();
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     */
    private static function assertHasDocuments(array $documents): void
    {
        if ($documents === []) {
            throw new OperationServiceOrderFinalizationException('Debe cargar al menos un documento con archivo y tipo de documento.');
        }
    }

    private static function lock(OperationServiceOrder $order): OperationServiceOrder
    {
        $locked = OperationServiceOrder::query()->whereKey($order->getKey())->lockForUpdate()->first();

        if (! $locked instanceof OperationServiceOrder) {
            throw new OperationServiceOrderFinalizationException('La orden de servicio ya no existe.');
        }

        return $locked;
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @return list<array<string, mixed>>
     */
    private static function merge(OperationServiceOrder $order, array $documents): array
    {
        $existing = is_array($order->uploaded_documents) ? $order->uploaded_documents : [];

        return array_values(array_merge($existing, $documents));
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     */
    private static function registerFollowUp(OperationServiceOrder $order, array $documents): string
    {
        return LabImagingResultsFollowUpRegistrar::analystMessageSuffix(
            LabImagingResultsFollowUpRegistrar::registerFromServiceOrder($order, $documents),
        );
    }

    /**
     * Bitácora de la coordinación y del caso, con el mismo patrón que la anulación.
     *
     * @param  list<array<string, mixed>>  $documents
     */
    /**
     * @param  list<string>  $dispatchedMedications
     */
    private static function writeBitacora(OperationServiceOrder $order, array $documents, User $user, string $origin, array $dispatchedMedications = []): bool
    {
        $order->loadMissing(['operationCoordinationService', 'supplier']);
        $coordination = $order->operationCoordinationService;
        $userName = filled($user->name) ? (string) $user->name : 'SISTEMA';
        $bitacora = self::buildFinalizeBitacora($order, $documents, $userName, $origin, $dispatchedMedications);

        if (! $coordination instanceof OperationCoordinationService) {
            return false;
        }

        $previous = trim((string) ($coordination->observations ?? ''));
        $coordination->observations = $previous !== '' ? $previous."\n\n".$bitacora : $bitacora;
        $coordination->updated_by = $userName;
        $coordination->save();

        if (! filled($coordination->telemedicine_case_id)) {
            return false;
        }

        ObservationCase::query()->create([
            'telemedicine_case_id' => $coordination->telemedicine_case_id,
            'description' => $bitacora,
            'created_by' => (string) $user->id,
        ]);

        return true;
    }

    /**
     * Borra los archivos recién subidos de una operación fallida, sin tocar los
     * que la orden ya tenía registrados.
     *
     * @param  list<array<string, mixed>>  $documents
     */
    private static function discardUploadedFiles(OperationServiceOrder $order, array $documents): void
    {
        $fresh = OperationServiceOrder::query()->find($order->getKey());
        $registered = collect(is_array($fresh?->uploaded_documents) ? $fresh->uploaded_documents : [])
            ->map(static fn (mixed $document): string => is_array($document) ? (string) ($document['file_path'] ?? '') : '')
            ->filter()
            ->all();

        foreach ($documents as $document) {
            $path = (string) ($document['file_path'] ?? '');

            if ($path === '' || in_array($path, $registered, true) || ! str_starts_with($path, self::directory($order).'/')) {
                continue;
            }

            Storage::disk('public')->delete($path);
        }
    }
}
