<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationCoordinationService;
use App\Models\OperationDocumentList;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Acción del infolist de coordinación (pestaña "Ítems asociados") para cargar
 * uno o varios documentos de los servicios cubiertos y, opcionalmente,
 * finalizarlos en un solo paso ("Guardar" / "Guardar y finalizar").
 */
final class CoordinationServiceCoveredItemsFinalizer
{
    public const MEDICATION_DELIVERY_RECEIPT_DOCUMENT_NAME = 'COMPROBANTE DE ENTREGA DE MEDICAMENTOS';

    public const LAB_DELIVERY_RECEIPT_DOCUMENT_NAME = 'COMPROBANTE DE ENTREGA DE LABORATORIOS';

    public const STUDY_DELIVERY_RECEIPT_DOCUMENT_NAME = 'COMPROBANTE DE ENTREGA DE ESTUDIOS';

    public const SPECIALTY_DELIVERY_RECEIPT_DOCUMENT_NAME = 'COMPROBANTE DE ENTREGA DE ESPECIALISTAS';

    /**
     * Acción específica para medicamentos CUBIERTOS: subir el comprobante de entrega
     * y, al guardar, pasarlos a FINALIZADO.
     */
    public static function makeUploadCoveredMedicationDeliveryReceiptAction(): Action
    {
        return self::makeUploadCoveredItemDeliveryReceiptAction('medication');
    }

    /**
     * Acción específica para laboratorios CUBIERTOS: subir el comprobante de entrega
     * y, al guardar, pasarlos a FINALIZADO.
     */
    public static function makeUploadCoveredLabDeliveryReceiptAction(): Action
    {
        return self::makeUploadCoveredItemDeliveryReceiptAction('lab');
    }

    /**
     * Acción específica para estudios CUBIERTOS: subir el comprobante de entrega
     * y, al guardar, pasarlos a FINALIZADO.
     */
    public static function makeUploadCoveredStudyDeliveryReceiptAction(): Action
    {
        return self::makeUploadCoveredItemDeliveryReceiptAction('study');
    }

    /**
     * Acción específica para especialistas CUBIERTOS: subir el comprobante de entrega
     * y, al guardar, pasarlos a FINALIZADO.
     */
    public static function makeUploadCoveredSpecialtyDeliveryReceiptAction(): Action
    {
        return self::makeUploadCoveredItemDeliveryReceiptAction('specialty');
    }

    /**
     * @param  'medication'|'lab'|'study'|'specialty'  $itemType
     */
    public static function makeUploadCoveredItemDeliveryReceiptAction(string $itemType): Action
    {
        $config = self::coveredItemDeliveryReceiptConfig($itemType);

        return Action::make($config['action_name'])
            ->label($config['label'])
            ->icon('heroicon-o-clipboard-document-check')
            ->color('success')
            ->button()
            ->visible(fn (OperationCoordinationService $record): bool => self::hasCoveredItemsOfTypePendingFinalization($record, $itemType))
            ->modalHeading($config['modal_heading'])
            ->modalDescription($config['modal_description'])
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Guardar')
            ->modalCancelActionLabel('Cancelar')
            ->form([
                CheckboxList::make('service_item_keys')
                    ->label($config['checkbox_label'])
                    ->helperText($config['checkbox_helper'])
                    ->options(fn (?OperationCoordinationService $record): array => $record instanceof OperationCoordinationService
                        ? self::coveredItemPendingFinalizationOptions($record, $itemType)
                        : [])
                    ->default(fn (?OperationCoordinationService $record): array => $record instanceof OperationCoordinationService
                        ? array_keys(self::coveredItemPendingFinalizationOptions($record, $itemType))
                        : [])
                    ->bulkToggleable()
                    ->columns(1)
                    ->required(),
                FileUpload::make('document_file')
                    ->label('Comprobante de entrega')
                    ->directory(fn (?OperationCoordinationService $record): string => 'operation-coordination-services/'.($record?->id ?? 'tmp').'/documents')
                    ->preserveFilenames()
                    ->required()
                    ->maxSize(10240),
            ])
            ->action(function (array $data, OperationCoordinationService $record) use ($itemType): void {
                self::handleCoveredItemDeliveryReceipt($record, $data, $itemType);
            });
    }

    public static function makeUploadAndFinalizeAction(): Action
    {
        return Action::make('uploadAndFinalizeCoveredServices')
            ->label('Cargar documentos / Finalizar cubiertos')
            ->icon('heroicon-o-paper-clip')
            ->color('warning')
            ->button()
            ->visible(fn (OperationCoordinationService $record): bool => self::hasCoveredItems($record))
            ->modalHeading('Documentos de servicios cubiertos')
            ->modalDescription('Cargue uno o varios documentos. Use "Guardar y finalizar" para cerrar los servicios cubiertos en gestión.')
            ->modalWidth(Width::FourExtraLarge)
            ->form(self::documentsUploadForm())
            ->modalSubmitActionLabel('Guardar')
            ->modalCancelActionLabel('Cancelar')
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('save_and_finalize_covered_services', arguments: ['finalize' => true])
                    ->label('Guardar y finalizar')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (OperationCoordinationService $record): bool => self::hasCoveredItemsToFinalize($record)),
            ])
            ->action(function (array $data, array $arguments, OperationCoordinationService $record): void {
                self::handle($record, $data, $arguments);
            });
    }

    /**
     * Acción del infolist para pasar los servicios cubiertos seleccionados de
     * "PENDIENTE" a "EN GESTION". Solo aplica a ítems cubiertos pendientes.
     */
    public static function makePlaceCoveredItemsInManagementAction(): Action
    {
        return Action::make('placeCoveredItemsInManagement')
            ->label('Activar gestión')
            ->icon('heroicon-o-play')
            ->color('info')
            ->button()
            ->visible(fn (OperationCoordinationService $record): bool => self::hasCoveredItemsPendingManagement($record))
            ->modalHeading('Colocar servicios cubiertos en gestión')
            ->modalDescription('Seleccione los servicios cubiertos que desea pasar de "Pendiente" a "En gestión".')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Colocar en gestión')
            ->modalCancelActionLabel('Cancelar')
            ->form([
                CheckboxList::make('service_item_keys')
                    ->label('Servicios cubiertos pendientes')
                    ->options(fn (?OperationCoordinationService $record): array => $record instanceof OperationCoordinationService
                        ? self::coveredPendingManagementOptions($record)
                        : [])
                    ->bulkToggleable()
                    ->columns(1)
                    ->required(),
            ])
            ->action(function (array $data, OperationCoordinationService $record): void {
                self::placeCoveredItemsInManagement($record, is_array($data['service_item_keys'] ?? null) ? $data['service_item_keys'] : []);
            });
    }

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function documentsUploadForm(): array
    {
        return [
            Repeater::make('documents')
                ->label('Documentos')
                ->defaultItems(1)
                ->addActionLabel('Agregar documento')
                ->reorderable()
                ->minItems(1)
                ->schema([
                    Select::make('service_item_keys')
                        ->label('Servicio(s) cubierto(s)')
                        ->helperText('Indique a qué servicio cubierto pertenece este documento.')
                        ->options(fn (?OperationCoordinationService $record): array => $record instanceof OperationCoordinationService
                            ? self::coveredItemServiceOptions($record)
                            : [])
                        ->searchable()
                        ->preload()
                        ->multiple()
                        ->required(),
                    Select::make('document_type_ids')
                        ->label('Tipo(s) de documento')
                        ->helperText('Seleccione uno o varios tipos según la información contenida en el archivo.')
                        ->options(fn (): array => OperationDocumentList::query()
                            ->orderBy('name', 'asc')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->multiple()
                        ->required(),
                    FileUpload::make('document_file')
                        ->label('Archivo')
                        ->directory(fn (?OperationCoordinationService $record): string => 'operation-coordination-services/'.($record?->id ?? 'tmp').'/documents')
                        ->preserveFilenames()
                        ->required()
                        ->maxSize(10240),
                ])
                ->columns(1)
                ->required(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $arguments
     */
    public static function handle(OperationCoordinationService $record, array $data, array $arguments): void
    {
        $newDocuments = self::buildUploadedDocumentsFromForm($data, self::coveredItemServiceOptions($record));

        if ($newDocuments === []) {
            Notification::make()
                ->warning()
                ->title('Sin documentos válidos')
                ->body('Debe cargar al menos un documento con archivo y tipos seleccionados.')
                ->send();

            return;
        }

        $existingDocuments = is_array($record->uploaded_documents)
            ? $record->uploaded_documents
            : [];

        $record->update([
            'uploaded_documents' => array_values(array_merge($existingDocuments, $newDocuments)),
        ]);

        $shouldFinalize = filter_var($arguments['finalize'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $shouldFinalize) {
            Notification::make()
                ->success()
                ->title('Documentos cargados')
                ->body(count($newDocuments) > 1
                    ? 'Se cargaron '.count($newDocuments).' documentos en la coordinación.'
                    : 'Se cargó 1 documento en la coordinación.')
                ->send();

            return;
        }

        $finalized = self::finalizeCoveredItems($record);

        if ($finalized === 0) {
            Notification::make()
                ->warning()
                ->title('Sin servicios cubiertos por finalizar')
                ->body('Se guardaron los documentos, pero no había servicios cubiertos pendientes de finalizar.')
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Servicios cubiertos finalizados')
            ->body($finalized > 1
                ? 'Se guardaron los documentos y se finalizaron '.$finalized.' servicios cubiertos.'
                : 'Se guardaron los documentos y se finalizó 1 servicio cubierto.')
            ->send();
    }

    public static function hasCoveredItems(OperationCoordinationService $record): bool
    {
        return CoordinationServiceItemsManager::associatedServiceItemsForManagement($record)
            ->contains(fn (array $item): bool => ($item['coverage'] ?? null) === true);
    }

    public static function hasCoveredItemsToFinalize(OperationCoordinationService $record): bool
    {
        return self::coveredItemsPendingFinalization($record)->isNotEmpty();
    }

    public static function hasCoveredMedicationsPendingFinalization(OperationCoordinationService $record): bool
    {
        return self::hasCoveredItemsOfTypePendingFinalization($record, 'medication');
    }

    public static function hasCoveredItemsOfTypePendingFinalization(OperationCoordinationService $record, string $itemType): bool
    {
        return self::coveredItemsOfTypePendingFinalization($record, $itemType)->isNotEmpty();
    }

    /**
     * @return Collection<int, array{key: string, category: string, label: string, detail: string, coverage: bool|null, coverage_label: string, status: string, selectable: bool}>
     */
    public static function coveredMedicationsPendingFinalization(OperationCoordinationService $record): Collection
    {
        return self::coveredItemsOfTypePendingFinalization($record, 'medication');
    }

    /**
     * @param  'medication'|'lab'|'study'|'specialty'  $itemType
     * @return Collection<int, array{key: string, category: string, label: string, detail: string, coverage: bool|null, coverage_label: string, status: string, selectable: bool}>
     */
    public static function coveredItemsOfTypePendingFinalization(OperationCoordinationService $record, string $itemType): Collection
    {
        $prefix = $itemType.':';

        return self::coveredItemsPendingFinalization($record)
            ->filter(static fn (array $item): bool => str_starts_with((string) ($item['key'] ?? ''), $prefix))
            ->values();
    }

    /**
     * @return array<string, string>
     */
    public static function coveredMedicationPendingFinalizationOptions(OperationCoordinationService $record): array
    {
        return self::coveredItemPendingFinalizationOptions($record, 'medication');
    }

    /**
     * @param  'medication'|'lab'|'study'|'specialty'  $itemType
     * @return array<string, string>
     */
    public static function coveredItemPendingFinalizationOptions(OperationCoordinationService $record, string $itemType): array
    {
        return self::coveredItemsOfTypePendingFinalization($record, $itemType)
            ->mapWithKeys(fn (array $item): array => [
                (string) $item['key'] => $item['label'].' · '.$item['status'],
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function handleCoveredMedicationDeliveryReceipt(OperationCoordinationService $record, array $data): void
    {
        self::handleCoveredItemDeliveryReceipt($record, $data, 'medication');
    }

    /**
     * @param  'medication'|'lab'|'study'|'specialty'  $itemType
     * @param  array<string, mixed>  $data
     */
    public static function handleCoveredItemDeliveryReceipt(OperationCoordinationService $record, array $data, string $itemType): void
    {
        $config = self::coveredItemDeliveryReceiptConfig($itemType);

        $documentType = OperationDocumentList::query()
            ->where('name', $config['document_name'])
            ->first();

        if ($documentType === null) {
            Notification::make()
                ->danger()
                ->title('Tipo de documento no configurado')
                ->body('No existe el tipo "'.$config['document_name'].'" en el catálogo de documentos.')
                ->send();

            return;
        }

        $allowedKeys = array_keys(self::coveredItemPendingFinalizationOptions($record, $itemType));
        $selectedKeys = collect($data['service_item_keys'] ?? [])
            ->map(static fn (mixed $key): string => trim((string) $key))
            ->filter(static fn (string $key): bool => in_array($key, $allowedKeys, true))
            ->unique()
            ->values()
            ->all();

        if ($selectedKeys === []) {
            Notification::make()
                ->warning()
                ->title($config['empty_selection_title'])
                ->body($config['empty_selection_body'])
                ->send();

            return;
        }

        $documentFile = trim((string) ($data['document_file'] ?? ''));

        if ($documentFile === '') {
            Notification::make()
                ->warning()
                ->title('Sin archivo')
                ->body($config['empty_file_body'])
                ->send();

            return;
        }

        $serviceLabelsByKey = self::coveredItemPendingFinalizationOptions($record, $itemType);
        $documents = self::mapFormDocuments([
            'documents' => [[
                'document_file' => $documentFile,
                'document_type_ids' => [(int) $documentType->id],
                'service_item_keys' => $selectedKeys,
            ]],
        ], [(int) $documentType->id => (string) $documentType->name], $serviceLabelsByKey);

        if ($documents === []) {
            Notification::make()
                ->warning()
                ->title('Documento inválido')
                ->body('No se pudo registrar el comprobante de entrega.')
                ->send();

            return;
        }

        $existingDocuments = is_array($record->uploaded_documents)
            ? $record->uploaded_documents
            : [];

        $record->update([
            'uploaded_documents' => array_values(array_merge($existingDocuments, $documents)),
        ]);

        $finalized = self::finalizeCoveredItemsByKeys($record, $selectedKeys);

        if ($finalized === 0) {
            Notification::make()
                ->warning()
                ->title('Comprobante guardado sin cambios de estatus')
                ->body($config['saved_without_finalize_body'])
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title($config['success_title'])
            ->body($finalized > 1
                ? $config['success_body_plural']($finalized)
                : $config['success_body_singular'])
            ->send();
    }

    /**
     * @param  'medication'|'lab'|'study'|'specialty'  $itemType
     * @return array{
     *     action_name: string,
     *     label: string,
     *     modal_heading: string,
     *     modal_description: string,
     *     checkbox_label: string,
     *     checkbox_helper: string,
     *     document_name: string,
     *     empty_selection_title: string,
     *     empty_selection_body: string,
     *     empty_file_body: string,
     *     saved_without_finalize_body: string,
     *     success_title: string,
     *     success_body_singular: string,
     *     success_body_plural: \Closure(int): string
     * }
     */
    public static function coveredItemDeliveryReceiptConfig(string $itemType): array
    {
        return match ($itemType) {
            'medication' => [
                'action_name' => 'uploadCoveredMedicationDeliveryReceipt',
                'label' => 'Comprobante',
                'modal_heading' => 'Comprobante de entrega de medicamentos',
                'modal_description' => 'Cargue el comprobante de entrega. Al guardar, los medicamentos cubiertos seleccionados pasarán a FINALIZADO.',
                'checkbox_label' => 'Medicamentos cubiertos',
                'checkbox_helper' => 'Seleccione los medicamentos cubiertos incluidos en este comprobante.',
                'document_name' => self::MEDICATION_DELIVERY_RECEIPT_DOCUMENT_NAME,
                'empty_selection_title' => 'Sin medicamentos seleccionados',
                'empty_selection_body' => 'Seleccione al menos un medicamento cubierto incluido en el comprobante.',
                'empty_file_body' => 'Debe cargar el comprobante de entrega de medicamentos.',
                'saved_without_finalize_body' => 'Se cargó el comprobante, pero no había medicamentos cubiertos pendientes de finalizar.',
                'success_title' => 'Comprobante guardado y medicamentos finalizados',
                'success_body_singular' => 'Se cargó el comprobante y se finalizó 1 medicamento cubierto.',
                'success_body_plural' => static fn (int $count): string => 'Se cargó el comprobante y se finalizaron '.$count.' medicamentos cubiertos.',
            ],
            'lab' => [
                'action_name' => 'uploadCoveredLabDeliveryReceipt',
                'label' => 'Comprobante',
                'modal_heading' => 'Comprobante de entrega de laboratorios',
                'modal_description' => 'Cargue el comprobante de entrega. Al guardar, los laboratorios cubiertos seleccionados pasarán a FINALIZADO.',
                'checkbox_label' => 'Laboratorios cubiertos',
                'checkbox_helper' => 'Seleccione los laboratorios cubiertos incluidos en este comprobante.',
                'document_name' => self::LAB_DELIVERY_RECEIPT_DOCUMENT_NAME,
                'empty_selection_title' => 'Sin laboratorios seleccionados',
                'empty_selection_body' => 'Seleccione al menos un laboratorio cubierto incluido en el comprobante.',
                'empty_file_body' => 'Debe cargar el comprobante de entrega de laboratorios.',
                'saved_without_finalize_body' => 'Se cargó el comprobante, pero no había laboratorios cubiertos pendientes de finalizar.',
                'success_title' => 'Comprobante guardado y laboratorios finalizados',
                'success_body_singular' => 'Se cargó el comprobante y se finalizó 1 laboratorio cubierto.',
                'success_body_plural' => static fn (int $count): string => 'Se cargó el comprobante y se finalizaron '.$count.' laboratorios cubiertos.',
            ],
            'study' => [
                'action_name' => 'uploadCoveredStudyDeliveryReceipt',
                'label' => 'Comprobante',
                'modal_heading' => 'Comprobante de entrega de estudios',
                'modal_description' => 'Cargue el comprobante de entrega. Al guardar, los estudios cubiertos seleccionados pasarán a FINALIZADO.',
                'checkbox_label' => 'Estudios cubiertos',
                'checkbox_helper' => 'Seleccione los estudios cubiertos incluidos en este comprobante.',
                'document_name' => self::STUDY_DELIVERY_RECEIPT_DOCUMENT_NAME,
                'empty_selection_title' => 'Sin estudios seleccionados',
                'empty_selection_body' => 'Seleccione al menos un estudio cubierto incluido en el comprobante.',
                'empty_file_body' => 'Debe cargar el comprobante de entrega de estudios.',
                'saved_without_finalize_body' => 'Se cargó el comprobante, pero no había estudios cubiertos pendientes de finalizar.',
                'success_title' => 'Comprobante guardado y estudios finalizados',
                'success_body_singular' => 'Se cargó el comprobante y se finalizó 1 estudio cubierto.',
                'success_body_plural' => static fn (int $count): string => 'Se cargó el comprobante y se finalizaron '.$count.' estudios cubiertos.',
            ],
            'specialty' => [
                'action_name' => 'uploadCoveredSpecialtyDeliveryReceipt',
                'label' => 'Comprobante',
                'modal_heading' => 'Comprobante de entrega de especialistas',
                'modal_description' => 'Cargue el comprobante de entrega. Al guardar, los especialistas cubiertos seleccionados pasarán a FINALIZADO.',
                'checkbox_label' => 'Especialistas cubiertos',
                'checkbox_helper' => 'Seleccione los especialistas cubiertos incluidos en este comprobante.',
                'document_name' => self::SPECIALTY_DELIVERY_RECEIPT_DOCUMENT_NAME,
                'empty_selection_title' => 'Sin especialistas seleccionados',
                'empty_selection_body' => 'Seleccione al menos un especialista cubierto incluido en el comprobante.',
                'empty_file_body' => 'Debe cargar el comprobante de entrega de especialistas.',
                'saved_without_finalize_body' => 'Se cargó el comprobante, pero no había especialistas cubiertos pendientes de finalizar.',
                'success_title' => 'Comprobante guardado y especialistas finalizados',
                'success_body_singular' => 'Se cargó el comprobante y se finalizó 1 especialista cubierto.',
                'success_body_plural' => static fn (int $count): string => 'Se cargó el comprobante y se finalizaron '.$count.' especialistas cubiertos.',
            ],
            default => throw new \InvalidArgumentException('Tipo de ítem cubierto no soportado: '.$itemType),
        };
    }

    /**
     * @param  list<string>  $keys
     */
    public static function finalizeCoveredItemsByKeys(OperationCoordinationService $record, array $keys): int
    {
        $allowedKeys = self::coveredItemsPendingFinalization($record)
            ->map(static fn (array $item): string => (string) ($item['key'] ?? ''))
            ->all();

        $selectedKeys = collect($keys)
            ->map(static fn (mixed $key): string => trim((string) $key))
            ->filter(static fn (string $key): bool => in_array($key, $allowedKeys, true))
            ->unique()
            ->values();

        if ($selectedKeys->isEmpty()) {
            return 0;
        }

        $updated = 0;

        DB::transaction(function () use ($selectedKeys, $record, &$updated): void {
            foreach ($selectedKeys as $key) {
                if (! str_contains($key, ':')) {
                    continue;
                }

                [$type, $id] = explode(':', $key, 2);
                $id = (int) $id;
                $modelClass = self::clinicalItemModelClass($type);

                if ($modelClass === null || $id <= 0) {
                    continue;
                }

                $updated += (int) $modelClass::query()
                    ->where('operation_coordination_service_id', $record->id)
                    ->whereKey($id)
                    ->where('status', '!=', 'FINALIZADO')
                    ->update(['status' => 'FINALIZADO']);
            }
        });

        if ($updated > 0) {
            $record->updated_by = Auth::user()?->name;
            $record->save();

            OperationServiceOrderCoordinationSync::refreshCoordinationStatus($record->fresh() ?? $record);
        }

        return $updated;
    }

    /**
     * @return Collection<int, array{key: string, category: string, label: string, detail: string, coverage: bool|null, coverage_label: string, status: string, selectable: bool}>
     */
    public static function coveredItemsPendingFinalization(OperationCoordinationService $record): Collection
    {
        return CoordinationServiceItemsManager::associatedServiceItemsForManagement($record)
            ->filter(self::isCoveredItemPendingFinalization(...))
            ->values();
    }

    /**
     * @param  array{coverage?: bool|null, status?: string}  $item
     */
    public static function isCoveredItemPendingFinalization(array $item): bool
    {
        if (($item['coverage'] ?? null) !== true) {
            return false;
        }

        $closedStatuses = array_map(
            static fn (string $status): string => mb_strtoupper(trim($status)),
            CoordinationServiceItemsManager::closedItemStatuses()
        );

        return ! in_array(mb_strtoupper(trim((string) ($item['status'] ?? ''))), $closedStatuses, true);
    }

    public static function hasCoveredItemsPendingManagement(OperationCoordinationService $record): bool
    {
        return self::coveredItemsPendingManagement($record)->isNotEmpty();
    }

    /**
     * @return Collection<int, array{key: string, category: string, label: string, detail: string, coverage: bool|null, coverage_label: string, status: string, selectable: bool}>
     */
    public static function coveredItemsPendingManagement(OperationCoordinationService $record): Collection
    {
        return CoordinationServiceItemsManager::associatedServiceItemsForManagement($record)
            ->filter(self::isCoveredItemPendingManagement(...))
            ->values();
    }

    /**
     * @param  array{coverage?: bool|null, status?: string}  $item
     */
    public static function isCoveredItemPendingManagement(array $item): bool
    {
        if (($item['coverage'] ?? null) !== true) {
            return false;
        }

        return mb_strtoupper(trim((string) ($item['status'] ?? ''))) === 'PENDIENTE';
    }

    /**
     * @return array<string, string>
     */
    public static function coveredPendingManagementOptions(OperationCoordinationService $record): array
    {
        return self::coveredItemsPendingManagement($record)
            ->mapWithKeys(fn (array $item): array => [
                (string) $item['key'] => $item['category'].': '.$item['label'],
            ])
            ->all();
    }

    /**
     * @param  array<int, string|int>  $keys
     */
    public static function placeCoveredItemsInManagement(OperationCoordinationService $record, array $keys): int
    {
        $allowedKeys = self::coveredItemsPendingManagement($record)
            ->map(static fn (array $item): string => (string) ($item['key'] ?? ''))
            ->all();

        $selectedKeys = collect($keys)
            ->map(static fn (mixed $key): string => trim((string) $key))
            ->filter(static fn (string $key): bool => in_array($key, $allowedKeys, true))
            ->unique()
            ->values();

        if ($selectedKeys->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('Sin servicios seleccionados')
                ->body('Seleccione al menos un servicio cubierto pendiente para colocarlo en gestión.')
                ->send();

            return 0;
        }

        $updated = 0;

        DB::transaction(function () use ($selectedKeys, $record, &$updated): void {
            foreach ($selectedKeys as $key) {
                if (! str_contains($key, ':')) {
                    continue;
                }

                [$type, $id] = explode(':', $key, 2);
                $id = (int) $id;
                $modelClass = self::clinicalItemModelClass($type);

                if ($modelClass === null || $id <= 0) {
                    continue;
                }

                $updated += (int) $modelClass::query()
                    ->where('operation_coordination_service_id', $record->id)
                    ->whereKey($id)
                    ->whereRaw('UPPER(TRIM(status)) = ?', ['PENDIENTE'])
                    ->update(['status' => 'EN GESTION']);
            }
        });

        if ($updated > 0) {
            $record->updated_by = Auth::user()?->name;
            $record->save();

            OperationServiceOrderCoordinationSync::refreshCoordinationStatus($record->fresh() ?? $record);
        }

        Notification::make()
            ->success()
            ->title('Servicios cubiertos en gestión')
            ->body($updated > 1
                ? 'Se colocaron '.$updated.' servicios cubiertos en gestión.'
                : 'Se colocó 1 servicio cubierto en gestión.')
            ->send();

        return $updated;
    }

    /**
     * @return class-string<TelemedicinePatientMedications|TelemedicinePatientLab|TelemedicinePatientStudy|TelemedicinePatientSpecialty>|null
     */
    private static function clinicalItemModelClass(string $type): ?string
    {
        return match ($type) {
            'medication' => TelemedicinePatientMedications::class,
            'lab' => TelemedicinePatientLab::class,
            'study' => TelemedicinePatientStudy::class,
            'specialty' => TelemedicinePatientSpecialty::class,
            default => null,
        };
    }

    public static function finalizeCoveredItems(OperationCoordinationService $record): int
    {
        $keys = self::coveredItemsPendingFinalization($record)
            ->map(static fn (array $item): string => (string) ($item['key'] ?? ''))
            ->filter(static fn (string $key): bool => $key !== '')
            ->values()
            ->all();

        return self::finalizeCoveredItemsByKeys($record, $keys);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{document_name: string, file_path: string, document_type_ids: list<int>, document_types: list<string>, uploaded_at: string}>
     */
    public static function buildUploadedDocumentsFromForm(array $data, array $serviceLabelsByKey = []): array
    {
        /** @var array<int, string> $documentTypeNames */
        $documentTypeNames = OperationDocumentList::query()
            ->pluck('name', 'id')
            ->mapWithKeys(static fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();

        return self::mapFormDocuments($data, $documentTypeNames, $serviceLabelsByKey);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $documentTypeNames
     * @param  array<string, string>  $serviceLabelsByKey
     * @return list<array{document_name: string, file_path: string, document_type_ids: list<int>, document_types: list<string>, service_item_keys: list<string>, services: list<string>, service: string, uploaded_at: string}>
     */
    public static function mapFormDocuments(array $data, array $documentTypeNames, array $serviceLabelsByKey = []): array
    {
        return collect($data['documents'] ?? [])
            ->map(function (mixed $item) use ($documentTypeNames, $serviceLabelsByKey): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $documentFile = trim((string) ($item['document_file'] ?? ''));

                if ($documentFile === '') {
                    return null;
                }

                $documentName = trim((string) pathinfo($documentFile, PATHINFO_FILENAME));

                if ($documentName === '') {
                    $documentName = basename($documentFile);
                }

                $rawTypeIds = $item['document_type_ids'] ?? [];

                $typeIds = collect(is_array($rawTypeIds) ? $rawTypeIds : [])
                    ->map(static fn (mixed $value, mixed $key): int => is_numeric($value)
                        ? (int) $value
                        : (is_numeric($key) ? (int) $key : 0))
                    ->filter(static fn (int $id): bool => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                $typeNames = collect($typeIds)
                    ->map(static fn (int $id): string => $documentTypeNames[$id] ?? '')
                    ->filter(static fn (string $value): bool => $value !== '')
                    ->values()
                    ->all();

                $rawServiceKeys = $item['service_item_keys'] ?? [];

                $serviceKeys = collect(is_array($rawServiceKeys) ? $rawServiceKeys : [])
                    ->map(static fn (mixed $value): string => trim((string) $value))
                    ->filter(static fn (string $value): bool => $value !== '')
                    ->unique()
                    ->values()
                    ->all();

                $serviceLabels = collect($serviceKeys)
                    ->map(static fn (string $key): string => $serviceLabelsByKey[$key] ?? '')
                    ->filter(static fn (string $value): bool => $value !== '')
                    ->values()
                    ->all();

                return [
                    'document_name' => $documentName,
                    'file_path' => $documentFile,
                    'document_type_ids' => $typeIds,
                    'document_types' => $typeNames,
                    'service_item_keys' => $serviceKeys,
                    'services' => $serviceLabels,
                    'service' => implode('; ', $serviceLabels),
                    'uploaded_at' => now()->toDateTimeString(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function coveredItemServiceOptions(OperationCoordinationService $record): array
    {
        return CoordinationServiceItemsManager::associatedServiceItemsForManagement($record)
            ->filter(fn (array $item): bool => ($item['coverage'] ?? null) === true)
            ->mapWithKeys(fn (array $item): array => [
                (string) $item['key'] => $item['category'].': '.$item['label'],
            ])
            ->all();
    }
}
