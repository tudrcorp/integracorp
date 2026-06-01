<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\OperationCoordinationClinicDocument;
use App\Models\OperationCoordinationService;
use App\Models\OperationDocumentList;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Models\OperationServiceOrderQuote;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class TelemedicineCaseDocumentsCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function hubViewContext(TelemedicineCase $case): array
    {
        $case->loadMissing('telemedicinePatient');

        $documentFilters = Schema::hasTable('operation_document_lists')
            ? OperationDocumentList::query()
                ->pluck('name')
                ->filter(static fn (mixed $name): bool => is_string($name) && trim($name) !== '')
                ->map(static fn (string $name): string => trim($name))
                ->unique()
                ->sort()
                ->values()
                ->all()
            : [];

        return [
            'documents' => self::entries($case),
            'documentFilters' => $documentFilters,
            'caseCode' => filled($case->code)
                ? (string) $case->code
                : 'Caso #'.$case->id,
            'defaultPhone' => (string) ($case->patient_phone ?: $case->telemedicinePatient?->phone ?: ''),
            'defaultEmail' => (string) ($case->telemedicinePatient?->email ?: $case->telemedicinePatient?->email_contact ?: ''),
            'patientName' => (string) ($case->patient_name ?: $case->telemedicinePatient?->full_name ?: 'Paciente'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function entries(TelemedicineCase $case): array
    {
        $entries = collect();
        $caseLabel = filled($case->code) ? (string) $case->code : 'Caso #'.$case->id;

        TelemedicineDocument::query()
            ->where('telemedicine_case_id', $case->id)
            ->orderByDesc('created_at')
            ->get()
            ->each(function (TelemedicineDocument $document) use ($entries, $caseLabel): void {
                $fileName = ltrim((string) $document->name, '/');
                $relativePath = 'telemedicina-doc/'.$fileName;

                $entries->push(self::makeEntry(
                    category: 'Referencia médica',
                    categoryTone: 'primary',
                    reference: $caseLabel,
                    referenceDetail: 'Documento telemedicina #'.$document->id,
                    documentName: basename($fileName),
                    types: ['Consignación del caso'],
                    filePath: $relativePath,
                    uploadedAt: $document->created_at,
                    downloadUrl: asset('storage/'.$relativePath),
                ));
            });

        self::appendConsultationUploadedDocuments($entries, $case);

        $coordinations = OperationCoordinationService::query()
            ->where('telemedicine_case_id', $case->id)
            ->orderByDesc('created_at')
            ->get();

        /** @var array<int, int> $coordinationIds */
        $coordinationIds = $coordinations->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        foreach ($coordinations as $coordination) {
            $coordinationReference = self::coordinationReference($coordination);

            foreach (self::normalizeUploadedDocuments($coordination->uploaded_documents) as $uploaded) {
                $entry = self::entryFromUploadedDocument(
                    uploaded: $uploaded,
                    category: 'Coordinación',
                    categoryTone: 'info',
                    reference: $coordinationReference,
                    referenceDetail: 'Coordinación #'.$coordination->id,
                    fallbackUploadedAt: $coordination->updated_at ?? $coordination->created_at,
                );

                if ($entry !== null) {
                    $entries->push($entry);
                }
            }

        }

        self::appendCoordinationClinicDocuments($entries, $coordinations);

        if (self::hasTable(OperationQuoteGenerator::class)) {
            $quotesQuery = OperationQuoteGenerator::query()->orderByDesc('created_at');

            if ($coordinationIds !== []) {
                $quotesQuery->where(function ($query) use ($case, $coordinationIds): void {
                    $query->where('telemedicine_case_id', $case->id)
                        ->orWhereIn('operation_coordination_service_id', $coordinationIds);
                });
            } else {
                $quotesQuery->where('telemedicine_case_id', $case->id);
            }

            $quotesQuery->get()->each(function (OperationQuoteGenerator $quote) use ($entries, $coordinations): void {
                if (! filled($quote->quote_pdf_path)) {
                    return;
                }

                $coordination = $coordinations->firstWhere('id', $quote->operation_coordination_service_id);
                $coordinationReference = $coordination
                    ? self::coordinationReference($coordination)
                    : '—';

                $entries->push(self::makeEntry(
                    category: 'Cotización coordinación',
                    categoryTone: 'warning',
                    reference: 'COT-'.str_pad((string) $quote->id, 6, '0', STR_PAD_LEFT),
                    referenceDetail: filled($coordination)
                        ? 'Coordinación '.$coordinationReference
                        : 'Cotización #'.$quote->id,
                    documentName: 'cotizacion-coordinacion-'.$quote->id.'.pdf',
                    types: array_filter([(string) ($quote->type_service ?? ''), (string) ($quote->status ?? '')]),
                    filePath: (string) $quote->quote_pdf_path,
                    uploadedAt: $quote->updated_at ?? $quote->created_at,
                    downloadUrl: self::publicUrl((string) $quote->quote_pdf_path),
                ));
            });
        }

        if ($coordinationIds === []) {
            return self::finalizeEntries($entries);
        }

        $orders = OperationServiceOrder::query()
            ->whereIn('operation_coordination_service_id', $coordinationIds)
            ->orderByDesc('created_at')
            ->get();

        foreach ($orders as $order) {
            $coordination = $coordinations->firstWhere('id', $order->operation_coordination_service_id);
            $coordinationReference = $coordination
                ? self::coordinationReference($coordination)
                : '—';
            $orderReference = filled($order->order_number)
                ? (string) $order->order_number
                : 'OS-'.$order->id;

            if (filled($order->service_order_pdf_path)) {
                $entries->push(self::makeEntry(
                    category: 'Orden de servicio',
                    categoryTone: 'success',
                    reference: $orderReference,
                    referenceDetail: 'Orden #'.$order->id.' · '.$coordinationReference,
                    documentName: 'orden-servicio-'.$order->id.'.pdf',
                    types: array_filter([(string) ($order->service_type ?? ''), (string) ($order->status ?? '')]),
                    filePath: (string) $order->service_order_pdf_path,
                    uploadedAt: $order->updated_at ?? $order->created_at,
                    downloadUrl: route('operations.operation-service-orders.pdf', ['operationServiceOrder' => $order->id]),
                ));
            }

            if (filled($order->associated_quote_pdf_path)) {
                $entries->push(self::makeEntry(
                    category: 'Cotización aprobada (orden)',
                    categoryTone: 'warning',
                    reference: $orderReference,
                    referenceDetail: 'PDF cotización vinculada a orden #'.$order->id,
                    documentName: basename((string) $order->associated_quote_pdf_path),
                    types: ['Cotización origen'],
                    filePath: (string) $order->associated_quote_pdf_path,
                    uploadedAt: $order->updated_at ?? $order->created_at,
                    downloadUrl: self::publicUrl((string) $order->associated_quote_pdf_path),
                ));
            }

            foreach (self::normalizeUploadedDocuments($order->uploaded_documents) as $uploaded) {
                $entry = self::entryFromUploadedDocument(
                    uploaded: $uploaded,
                    category: 'Orden de servicio',
                    categoryTone: 'success',
                    reference: $orderReference,
                    referenceDetail: 'Orden #'.$order->id.' · '.$coordinationReference,
                    fallbackUploadedAt: $order->updated_at ?? $order->created_at,
                );

                if ($entry !== null) {
                    $entries->push($entry);
                }
            }

            foreach (self::normalizeLegacyFiles($order->files) as $legacyPath) {
                $entries->push(self::makeEntry(
                    category: 'Orden de servicio',
                    categoryTone: 'success',
                    reference: $orderReference,
                    referenceDetail: 'Adjunto legado · Orden #'.$order->id,
                    documentName: basename($legacyPath),
                    types: ['Adjunto'],
                    filePath: $legacyPath,
                    uploadedAt: $order->updated_at ?? $order->created_at,
                    downloadUrl: self::publicUrl($legacyPath),
                ));
            }

            if (self::hasTable(OperationServiceOrderQuote::class)) {
                OperationServiceOrderQuote::query()
                    ->where('operation_service_order_id', $order->id)
                    ->orderByDesc('created_at')
                    ->get()
                    ->each(function (OperationServiceOrderQuote $orderQuote) use ($entries, $order, $orderReference): void {
                        if (! filled($orderQuote->quote_pdf_path)) {
                            return;
                        }

                        $entries->push(self::makeEntry(
                            category: 'Cotización medicamentos',
                            categoryTone: 'danger',
                            reference: filled($orderQuote->quote_number)
                                ? (string) $orderQuote->quote_number
                                : $orderReference,
                            referenceDetail: 'Cotización orden #'.$orderQuote->id.' · Orden #'.$order->id,
                            documentName: basename((string) $orderQuote->quote_pdf_path),
                            types: ['Medicamentos / insumos'],
                            filePath: (string) $orderQuote->quote_pdf_path,
                            uploadedAt: $orderQuote->updated_at ?? $orderQuote->created_at,
                            downloadUrl: self::publicUrl((string) $orderQuote->quote_pdf_path),
                        ));
                    });
            }
        }

        return self::finalizeEntries($entries);
    }

    /**
     * @return array<int, string>
     */
    public static function categories(array $entries): array
    {
        return collect($entries)
            ->pluck('category')
            ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     */
    private static function appendConsultationUploadedDocuments(Collection $entries, TelemedicineCase $case): void
    {
        $consultations = TelemedicineConsultationPatient::query()
            ->where('telemedicine_case_id', $case->id)
            ->orderByDesc('created_at')
            ->get();

        foreach ($consultations as $consultation) {
            $reference = filled($consultation->code_reference)
                ? (string) $consultation->code_reference
                : 'CONS-'.$consultation->id;

            foreach (self::normalizeUploadedDocuments($consultation->uploaded_documents) as $uploaded) {
                $entry = self::entryFromUploadedDocument(
                    uploaded: $uploaded,
                    category: 'Consulta telemedicina',
                    categoryTone: 'primary',
                    reference: $reference,
                    referenceDetail: 'Consulta #'.$consultation->id,
                    fallbackUploadedAt: $consultation->updated_at ?? $consultation->created_at,
                );

                if ($entry !== null) {
                    $entries->push($entry);
                }
            }
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     * @param  Collection<int, OperationCoordinationService>  $coordinations
     */
    private static function appendCoordinationClinicDocuments(Collection $entries, Collection $coordinations): void
    {
        if (! self::hasTable(OperationCoordinationClinicDocument::class) || $coordinations->isEmpty()) {
            return;
        }

        /** @var array<int, int> $coordinationIds */
        $coordinationIds = $coordinations->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        $clinicDocumentsByCoordination = OperationCoordinationClinicDocument::query()
            ->whereIn('operation_coordination_service_id', $coordinationIds)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('operation_coordination_service_id');

        foreach ($coordinations as $coordination) {
            $coordinationReference = self::coordinationReference($coordination);

            foreach ($clinicDocumentsByCoordination->get($coordination->id, collect()) as $clinicDocument) {
                if (! $clinicDocument instanceof OperationCoordinationClinicDocument) {
                    continue;
                }

                $category = match ((string) $clinicDocument->category) {
                    OperationCoordinationClinicDocument::CATEGORY_INGRESO => 'Clínica · Ingreso',
                    OperationCoordinationClinicDocument::CATEGORY_EGRESO => 'Clínica · Egreso',
                    default => 'Clínica',
                };

                $entries->push(self::makeEntry(
                    category: $category,
                    categoryTone: 'gray',
                    reference: $coordinationReference,
                    referenceDetail: 'Coordinación #'.$coordination->id,
                    documentName: filled($clinicDocument->original_filename)
                        ? (string) $clinicDocument->original_filename
                        : basename((string) $clinicDocument->path),
                    types: [ucfirst((string) $clinicDocument->category)],
                    filePath: (string) $clinicDocument->path,
                    uploadedAt: $clinicDocument->created_at,
                    downloadUrl: self::publicUrl((string) $clinicDocument->path),
                ));
            }
        }
    }

    private static function hasTable(string $modelClass): bool
    {
        $model = new $modelClass;

        return Schema::hasTable($model->getTable());
    }

    private static function coordinationReference(OperationCoordinationService $coordination): string
    {
        if (filled($coordination->reference_number)) {
            return (string) $coordination->reference_number;
        }

        return 'COO-'.str_pad((string) $coordination->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $uploaded
     * @return array<string, mixed>|null
     */
    private static function entryFromUploadedDocument(
        array $uploaded,
        string $category,
        string $categoryTone,
        string $reference,
        string $referenceDetail,
        mixed $fallbackUploadedAt,
    ): ?array {
        $filePath = trim((string) ($uploaded['file_path'] ?? ''));

        if ($filePath === '') {
            return null;
        }

        $documentName = trim((string) ($uploaded['document_name'] ?? ''));
        if ($documentName === '') {
            $documentName = basename($filePath);
        }

        /** @var array<int, string> $types */
        $types = is_array($uploaded['document_types'] ?? null)
            ? array_values(array_filter(array_map(
                static fn (mixed $type): string => trim((string) $type),
                $uploaded['document_types']
            )))
            : [];

        $uploadedAt = filled($uploaded['uploaded_at'] ?? null)
            ? (string) $uploaded['uploaded_at']
            : null;

        return self::makeEntry(
            category: $category,
            categoryTone: $categoryTone,
            reference: $reference,
            referenceDetail: $referenceDetail,
            documentName: $documentName,
            types: $types,
            filePath: $filePath,
            uploadedAt: $uploadedAt ? Carbon::parse($uploadedAt) : $fallbackUploadedAt,
            downloadUrl: self::publicUrl($filePath),
        );
    }

    /**
     * @param  array<int, string>  $types
     * @return array<string, mixed>
     */
    private static function makeEntry(
        string $category,
        string $categoryTone,
        string $reference,
        string $referenceDetail,
        string $documentName,
        array $types,
        string $filePath,
        mixed $uploadedAt,
        ?string $downloadUrl = null,
    ): array {
        $filePath = ltrim($filePath, '/');
        $downloadUrl ??= self::publicUrl($filePath);
        $exists = $filePath !== '' && Storage::disk('public')->exists($filePath);
        $extension = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));
        $types = array_values(array_filter($types, static fn (string $type): bool => $type !== ''));
        $typesLabel = $types !== [] ? implode(' · ', $types) : '—';

        $timestamp = self::resolveTimestamp($uploadedAt);

        $searchBlob = Str::lower(implode(' ', array_filter([
            $category,
            $reference,
            $referenceDetail,
            $documentName,
            $typesLabel,
            $extension,
        ])));

        return [
            'uid' => md5($category.'|'.$reference.'|'.$filePath.'|'.$documentName),
            'category' => $category,
            'category_tone' => $categoryTone,
            'reference' => $reference,
            'reference_detail' => $referenceDetail,
            'document_name' => $documentName,
            'types' => $types,
            'types_label' => $typesLabel,
            'extension' => $extension !== '' ? strtoupper($extension) : 'FILE',
            'file_path' => $filePath,
            'uploaded_at_label' => $timestamp?->format('d/m/Y H:i') ?? '—',
            'uploaded_at_relative' => $timestamp?->diffForHumans() ?? '',
            'sort_timestamp' => $timestamp?->timestamp ?? 0,
            'download_url' => $downloadUrl,
            'exists' => $exists,
            'search_blob' => $searchBlob,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    private static function finalizeEntries(Collection $entries): array
    {
        return $entries
            ->filter(static fn (array $entry): bool => ($entry['file_path'] ?? '') !== '')
            ->unique('uid')
            ->sortByDesc('sort_timestamp')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeUploadedDocuments(mixed $documents): array
    {
        if (! is_array($documents)) {
            return [];
        }

        return array_values(array_filter($documents, static fn (mixed $item): bool => is_array($item)));
    }

    /**
     * @return array<int, string>
     */
    private static function normalizeLegacyFiles(mixed $files): array
    {
        if (! is_array($files)) {
            return [];
        }

        return collect($files)
            ->map(static function (mixed $item): string {
                if (is_string($item)) {
                    return trim($item);
                }

                if (is_array($item)) {
                    return trim((string) ($item['path'] ?? $item['file'] ?? $item['file_path'] ?? ''));
                }

                return '';
            })
            ->filter(static fn (string $path): bool => $path !== '')
            ->values()
            ->all();
    }

    private static function publicUrl(string $path): string
    {
        $path = ltrim($path, '/');

        if ($path === '') {
            return '#';
        }

        return Storage::disk('public')->url($path);
    }

    private static function resolveTimestamp(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
