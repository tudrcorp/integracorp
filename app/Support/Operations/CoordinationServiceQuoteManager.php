<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceQuotes;
use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTable;
use App\Http\Controllers\OperationServiceOrderController;
use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Models\TelemedicinePatientMedications;
use App\Services\OperationQuoteGeneratorPdfService;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

final class CoordinationServiceQuoteManager
{
    public static function coordinationQuotes(OperationCoordinationService $record): Collection
    {
        return OperationQuoteGenerator::query()
            ->where('operation_coordination_service_id', $record->id)
            ->latest('id')
            ->get();
    }

    public static function formatCoordinationQuoteNumber(int $quoteId): string
    {
        return 'COT-'.str_pad((string) $quoteId, 6, '0', STR_PAD_LEFT);
    }

    public static function approvalUrlForQuote(OperationCoordinationService $record, int $quoteId): string
    {
        return ManageCoordinationServiceQuotes::getUrl(['record' => $record->id]).'?quote_id='.$quoteId;
    }

    /**
     * @return array<string, array{id: int, quote_number: string, url: string}>
     */
    public static function quoteLinksByClinicalItemKey(OperationCoordinationService $record): array
    {
        $map = [];

        self::coordinationQuotes($record)
            ->each(function (OperationQuoteGenerator $quote) use (&$map, $record): void {
                $items = is_array($quote->items) ? $quote->items : [];

                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $key = trim((string) ($item['key'] ?? ''));

                    if ($key === '' || isset($map[$key])) {
                        continue;
                    }

                    $map[$key] = [
                        'id' => (int) $quote->id,
                        'quote_number' => self::formatCoordinationQuoteNumber((int) $quote->id),
                        'url' => self::approvalUrlForQuote($record, (int) $quote->id),
                    ];
                }
            });

        return $map;
    }

    public static function nextServiceOrderNumber(): string
    {
        return 'ORD-'.str_pad((string) (((int) (OperationServiceOrder::max('id') ?? 0)) + 1), 4, '0', STR_PAD_LEFT);
    }

    public static function contextHeader(OperationCoordinationService $record): HtmlString
    {
        return new HtmlString(
            '<div class="rounded-2xl border border-black/[0.06] bg-zinc-50/90 px-4 py-3.5 dark:border-white/[0.08] dark:bg-zinc-900/90">'
            .'<div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">'
            .'<p><span class="font-semibold text-gray-900 dark:text-white">Paciente:</span> '.e($record->patient ?? '—').'</p>'
            .'<p><span class="font-semibold text-gray-900 dark:text-white">Referencia:</span> '.e($record->reference_number ?? '—')
            .' · <span class="font-semibold text-gray-900 dark:text-white">Servicio:</span> '.e($record->specific_service ?? $record->servicie ?? '—').'</p>'
            .'<p class="text-xs text-gray-500 dark:text-gray-400">Seleccione una o varias cotizaciones pendientes para aprobarlas, revise el detalle y complete la orden de servicio cuando corresponda.</p>'
            .'</div>'
            .'</div>'
        );
    }

    public static function formatManageQuoteAmountPreview(?float $amount, string $currency = 'USD'): string
    {
        if ($amount === null) {
            return '—';
        }

        $prefix = $currency === 'VES' ? 'Bs. ' : 'US$ ';

        return $prefix.number_format($amount, 2, '.', ',');
    }

    public static function manageQuoteSummaryRow(string $label, string $value, string $tone): string
    {
        $toneClasses = match ($tone) {
            'amber' => 'border-amber-200/70 bg-amber-50/50 text-amber-950 dark:border-amber-500/20 dark:bg-amber-950/20 dark:text-amber-50',
            default => 'border-black/[0.05] bg-white/70 text-zinc-900 dark:border-white/[0.08] dark:bg-zinc-900/50 dark:text-zinc-50',
        };

        return '<div class="flex items-center justify-between gap-3 rounded-xl border px-3.5 py-2.5 '.$toneClasses.'">'
            .'<span class="text-xs font-medium opacity-80">'.e($label).'</span>'
            .'<span class="text-sm font-semibold tabular-nums">'.e($value).'</span>'
            .'</div>';
    }

    /**
     * @return array<string, mixed>
     */
    public static function formDefaults(OperationCoordinationService $record): array
    {
        return [
            'quote_statuses' => self::coordinationQuotes($record)
                ->map(fn (OperationQuoteGenerator $quote): array => [
                    'quote_id' => $quote->id,
                    'status' => $quote->status ?? OperationQuoteGenerator::STATUS_PENDING,
                    'has_service_order' => filled($quote->operation_service_order_id),
                    'status_locked' => self::quoteStatusUpdateIsLocked($quote),
                ])
                ->all(),
            'selected_pending_quote_ids' => [],
            'approved_quote_id' => null,
            'order_number' => self::nextServiceOrderNumber(),
            'telemedicine_priority_id' => $record->telemedicine_priority_id,
            'operation_inventory_ubication_id' => null,
            'service_order_description' => null,
            'service_order_observations' => null,
        ];
    }

    public static function renderCoordinationQuotesSummary(OperationCoordinationService $record): HtmlString
    {
        $quotes = self::coordinationQuotes($record);

        if ($quotes->isEmpty()) {
            return new HtmlString(
                '<div class="rounded-2xl border border-dashed border-gray-300/80 px-4 py-3 text-sm text-gray-600 dark:border-white/15 dark:text-gray-300">No hay cotizaciones registradas para esta coordinación.</div>'
            );
        }

        $rows = $quotes->map(function (OperationQuoteGenerator $quote): string {
            $statusClass = match ($quote->status) {
                OperationQuoteGenerator::STATUS_APPROVED => 'bg-emerald-100 text-emerald-800 ring-emerald-300/70 dark:bg-emerald-500/20 dark:text-emerald-100',
                OperationQuoteGenerator::STATUS_REJECTED => 'bg-rose-100 text-rose-800 ring-rose-300/70 dark:bg-rose-500/20 dark:text-rose-100',
                OperationQuoteGenerator::STATUS_PRIVATE_CARE => 'bg-gray-100 text-gray-800 ring-gray-300/70 dark:bg-gray-500/20 dark:text-gray-100',
                default => 'bg-amber-100 text-amber-900 ring-amber-300/70 dark:bg-amber-500/20 dark:text-amber-100',
            };

            return '<tr class="border-b border-gray-100 last:border-0 dark:border-white/10">'
                .'<td class="px-3 py-2 font-medium">#'.e((string) $quote->id).'</td>'
                .'<td class="px-3 py-2">'.e((string) $quote->type_service).'</td>'
                .'<td class="px-3 py-2">'.e(self::formatManageQuoteAmountPreview((float) $quote->total)).'</td>'
                .'<td class="px-3 py-2"><span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 '.$statusClass.'">'.e((string) ($quote->status ?? OperationQuoteGenerator::STATUS_PENDING)).'</span></td>'
                .'<td class="px-3 py-2">'.self::renderQuoteGeneratorPdfCell($quote).'</td>'
                .'<td class="px-3 py-2">'.e(optional($quote->created_at)->format('d/m/Y H:i') ?? '—').'</td>'
                .'</tr>';
        })->implode('');

        return new HtmlString(
            '<div class="overflow-x-auto rounded-2xl border border-black/[0.06] dark:border-white/10">'
            .'<table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-white/10">'
            .'<thead class="bg-gray-50/90 dark:bg-white/5"><tr>'
            .'<th class="px-3 py-2 text-left font-semibold">ID</th>'
            .'<th class="px-3 py-2 text-left font-semibold">Tipo</th>'
            .'<th class="px-3 py-2 text-left font-semibold">Total</th>'
            .'<th class="px-3 py-2 text-left font-semibold">Estatus</th>'
            .'<th class="px-3 py-2 text-left font-semibold">Documento</th>'
            .'<th class="px-3 py-2 text-left font-semibold">Creada</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table></div>'
        );
    }

    public static function renderQuoteGeneratorPdfCell(OperationQuoteGenerator $quote): string
    {
        if (! filled($quote->quote_pdf_path)) {
            return '<span class="text-xs text-gray-500 dark:text-gray-400">Sin PDF</span>';
        }

        $pdfUrl = URL::to(Storage::url((string) $quote->quote_pdf_path));
        $downloadName = e(OperationQuoteGeneratorPdfService::filename($quote));

        return '<a href="'.e($pdfUrl).'" download="'.$downloadName.'" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 rounded-full border-b-2 border-primary-600 bg-primary-500/15 px-3 py-1.5 text-xs font-semibold text-primary-700 no-underline dark:border-primary-500 dark:bg-primary-500/25 dark:text-primary-300">'
            .'<svg class="size-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>'
            .'Descargar'
            .'</a>';
    }

    public static function renderOperationQuotePreview(OperationQuoteGenerator $quote): HtmlString
    {
        $quote->loadMissing('supplier');

        $items = collect(is_array($quote->items) ? $quote->items : []);

        $itemRows = $items->map(function (array $item): string {
            return '<tr class="border-b border-gray-100 last:border-0 dark:border-white/10">'
                .'<td class="px-3 py-2">'.e((string) ($item['category'] ?? '—')).'</td>'
                .'<td class="px-3 py-2 font-medium">'.e((string) ($item['label'] ?? '—')).'</td>'
                .'<td class="px-3 py-2">'.e((string) ($item['detail'] ?? '—')).'</td>'
                .'<td class="px-3 py-2">'.e((string) ($item['coverage_label'] ?? '—')).'</td>'
                .'</tr>';
        })->implode('');

        $itemsTable = $items->isEmpty()
            ? '<p class="text-sm text-gray-600 dark:text-gray-300">Sin ítems registrados en la cotización.</p>'
            : '<div class="overflow-x-auto rounded-xl border border-gray-200/90 dark:border-white/10">'
                .'<table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-white/10">'
                .'<thead class="bg-gray-50/90 dark:bg-white/5"><tr>'
                .'<th class="px-3 py-2 text-left font-semibold">Categoría</th>'
                .'<th class="px-3 py-2 text-left font-semibold">Ítem</th>'
                .'<th class="px-3 py-2 text-left font-semibold">Detalle</th>'
                .'<th class="px-3 py-2 text-left font-semibold">Cobertura</th>'
                .'</tr></thead><tbody>'.$itemRows.'</tbody></table></div>';

        return new HtmlString(
            '<div class="space-y-4 rounded-2xl border border-amber-200/70 bg-gradient-to-br from-amber-50/70 via-white to-white p-4 dark:border-amber-500/20 dark:from-amber-950/20 dark:via-zinc-900/90 dark:to-zinc-900/90">'
            .'<div class="flex flex-wrap items-start justify-between gap-3">'
            .'<div><p class="text-xs font-semibold uppercase tracking-wide text-amber-800/80 dark:text-amber-200/70">Cotización #'.e((string) $quote->id).'</p>'
            .'<p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">'.e((string) $quote->type_service).'</p></div>'
            .'<div class="text-right"><p class="text-xs text-gray-500 dark:text-gray-400">Total</p>'
            .'<p class="text-lg font-bold text-emerald-700 dark:text-emerald-300">'.e(self::formatManageQuoteAmountPreview((float) $quote->total)).'</p>'
            .'<p class="text-xs text-gray-500 dark:text-gray-400">'.e(self::formatManageQuoteAmountPreview((float) $quote->costo_bolivares, 'VES')).'</p></div>'
            .'</div>'
            .'<div class="grid gap-3 sm:grid-cols-3">'
            .self::manageQuoteSummaryRow('Costo base', self::formatManageQuoteAmountPreview((float) $quote->costo_dolares), 'slate')
            .self::manageQuoteSummaryRow('Ganancia', number_format((float) $quote->porcentaje_ganancia, 2, '.', '').'%', 'amber')
            .self::manageQuoteSummaryRow('Subtotal', self::formatManageQuoteAmountPreview((float) $quote->subtotal), 'slate')
            .'</div>'
            .self::renderQuoteSupplierPreviewBlock($quote)
            .$itemsTable
            .(filled($quote->observations)
                ? '<div class="rounded-xl border border-amber-200/80 bg-amber-50/60 px-3 py-2.5 dark:border-amber-500/20 dark:bg-amber-950/20">'
                    .'<p class="text-xs font-semibold uppercase tracking-wide text-amber-800/80 dark:text-amber-200/70">Observaciones</p>'
                    .'<p class="mt-1 whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-200">'.e((string) $quote->observations).'</p>'
                    .'</div>'
                : '')
            .'</div>'
        );
    }

    public static function renderQuoteSupplierPreviewBlock(OperationQuoteGenerator $quote): string
    {
        $quote->loadMissing('supplier');

        $supplierName = filled($quote->supplier?->name)
            ? (string) $quote->supplier->name
            : null;
        $supplierAddress = filled($quote->supplier_address)
            ? trim((string) $quote->supplier_address)
            : null;

        if ($supplierName === null && $supplierAddress === null) {
            return '';
        }

        return '<div class="rounded-xl border border-sky-200/80 bg-sky-50/60 px-3 py-2.5 dark:border-sky-500/20 dark:bg-sky-950/20">'
            .'<p class="text-xs font-semibold uppercase tracking-wide text-sky-800/80 dark:text-sky-200/70">Proveedor</p>'
            .($supplierName !== null
                ? '<p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">'.e($supplierName).'</p>'
                : '')
            .($supplierAddress !== null
                ? '<p class="mt-1 text-sm text-gray-600 dark:text-gray-300">'.e($supplierAddress).'</p>'
                : '')
            .'</div>';
    }

    /**
     * @return array<int, string>
     */
    public static function pendingQuoteApprovalOptions(OperationCoordinationService $record): array
    {
        return self::coordinationQuotes($record)
            ->filter(fn (OperationQuoteGenerator $quote): bool => self::isQuotePendingForApproval($quote))
            ->mapWithKeys(fn (OperationQuoteGenerator $quote): array => [
                $quote->id => '#'.$quote->id.' · '.(string) $quote->type_service.' · '.self::formatManageQuoteAmountPreview((float) $quote->total),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function pendingQuoteApprovalDescriptions(OperationCoordinationService $record): array
    {
        return self::coordinationQuotes($record)
            ->filter(fn (OperationQuoteGenerator $quote): bool => self::isQuotePendingForApproval($quote))
            ->mapWithKeys(fn (OperationQuoteGenerator $quote): array => [
                $quote->id => 'Creada '.(optional($quote->created_at)->format('d/m/Y H:i') ?? '—'),
            ])
            ->all();
    }

    public static function hasPendingQuotesForApproval(OperationCoordinationService $record): bool
    {
        return self::pendingQuoteApprovalOptions($record) !== [];
    }

    /**
     * @return Collection<int, OperationQuoteGenerator>
     */
    public static function pendingQuotesForApproval(OperationCoordinationService $record): Collection
    {
        return self::coordinationQuotes($record)
            ->load('supplier')
            ->filter(fn (OperationQuoteGenerator $quote): bool => self::isQuotePendingForApproval($quote))
            ->values();
    }

    public static function resolveBcvRateFromQuote(OperationQuoteGenerator $quote): ?float
    {
        $totalUsd = (float) ($quote->total ?? 0);

        if ($totalUsd > 0 && (float) ($quote->costo_bolivares ?? 0) > 0) {
            return round((float) $quote->costo_bolivares / $totalUsd, 4);
        }

        return OperationCoordinationServicesTable::referenciaTasaBcvDesdeApi();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function syncQuoteStatusesFromFormData(array &$data, OperationCoordinationService $record): void
    {
        $selected = array_map(intval(...), (array) ($data['selected_pending_quote_ids'] ?? []));
        $entries = is_array($data['quote_statuses'] ?? null) ? $data['quote_statuses'] : [];
        $lastSelectedForOrder = 0;

        foreach ($entries as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $quoteId = (int) ($entry['quote_id'] ?? 0);
            $hasOrder = (bool) ($entry['has_service_order'] ?? false);
            $statusLocked = (bool) ($entry['status_locked'] ?? false);
            $status = (string) ($entry['status'] ?? OperationQuoteGenerator::STATUS_PENDING);

            if ($statusLocked) {
                continue;
            }

            if (! $hasOrder && $status === OperationQuoteGenerator::STATUS_PENDING && in_array($quoteId, $selected, true)) {
                $entries[$index]['status'] = OperationQuoteGenerator::STATUS_APPROVED;
                $lastSelectedForOrder = $quoteId;
            }
        }

        $data['quote_statuses'] = $entries;

        if ($lastSelectedForOrder > 0 && count($selected) === 1) {
            $data = self::prefillServiceOrderFormDataFromQuote($data, $record, $lastSelectedForOrder);

            return;
        }

        if ($selected === []) {
            $data['approved_quote_id'] = null;

            return;
        }

        $data['approved_quote_id'] = (int) $selected[array_key_last($selected)];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prefillServiceOrderFormDataFromQuote(
        array $data,
        OperationCoordinationService $record,
        int $quoteId
    ): array {
        $quote = OperationQuoteGenerator::query()->find($quoteId);

        if (! $quote instanceof OperationQuoteGenerator || filled($quote->operation_service_order_id)) {
            return $data;
        }

        $itemsCount = count(is_array($quote->items) ? $quote->items : []);

        $data['approved_quote_id'] = $quote->id;
        $data['order_number'] = self::nextServiceOrderNumber();
        $data['telemedicine_priority_id'] = $record->telemedicine_priority_id;
        $data['service_order_description'] = sprintf(
            'Orden por cotización #%d · %s · %d ítem(s) · Total %s',
            $quote->id,
            $quote->type_service,
            $itemsCount,
            self::formatManageQuoteAmountPreview((float) $quote->total)
        );
        $data['service_order_observations'] = sprintf(
            'Generada automáticamente desde cotización #%d (costo base %s, ganancia %s%%).',
            $quote->id,
            self::formatManageQuoteAmountPreview((float) $quote->subtotal),
            number_format((float) $quote->porcentaje_ganancia, 2, '.', '')
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function updatePendingQuote(
        OperationQuoteGenerator $quote,
        OperationCoordinationService $record,
        array $data
    ): bool {
        if (! self::isQuotePendingForApproval($quote)) {
            Notification::make()
                ->title('Cotización no editable')
                ->body('Solo puede editar cotizaciones pendientes sin orden de servicio vinculada.')
                ->warning()
                ->send();

            return false;
        }

        $lineItems = (array) ($data['edit_quote_line_items'] ?? []);
        $subtotal = OperationCoordinationServicesTable::decimalOrNull($data['edit_quote_costo_dolares'] ?? null)
            ?? CoordinationServiceItemsManager::manageQuoteSubtotalFromLineItems($lineItems);
        $bcvRate = OperationCoordinationServicesTable::decimalOrNull($data['edit_quote_bcv_rate'] ?? null)
            ?? self::resolveBcvRateFromQuote($quote);
        $porcentaje = OperationCoordinationServicesTable::decimalOrNull($data['edit_quote_porcentaje_ganancia'] ?? 0) ?? 0.0;
        $supplierId = (int) ($data['edit_quote_supplier_id'] ?? 0);

        if ($subtotal === null || $subtotal <= 0 || $bcvRate === null || $bcvRate <= 0) {
            Notification::make()
                ->title('Datos incompletos')
                ->body('Revise el costo base, los precios unitarios y la tasa BCV de la cotización.')
                ->warning()
                ->send();

            return false;
        }

        if ($supplierId <= 0) {
            Notification::make()
                ->title('Proveedor requerido')
                ->body('Seleccione el proveedor de la cotización.')
                ->warning()
                ->send();

            return false;
        }

        $subtotal = round($subtotal, 2);
        $total = CoordinationServiceItemsManager::manageQuoteTotal($subtotal, $porcentaje) ?? 0.0;
        $costoBs = round($total * $bcvRate, 2);
        $supplierAddress = filled($data['edit_quote_supplier_address'] ?? null)
            ? trim((string) $data['edit_quote_supplier_address'])
            : CoordinationServiceItemsManager::resolveManageQuoteSupplierAddress($supplierId);
        $updatedItems = self::mergeQuoteLineItemsIntoItemsPayload($quote, $lineItems, $bcvRate);

        $quote->update([
            'supplier_id' => $supplierId,
            'supplier_address' => $supplierAddress,
            'observations' => filled($data['edit_quote_observations'] ?? null)
                ? trim((string) $data['edit_quote_observations'])
                : null,
            'items' => $updatedItems,
            'costo_dolares' => $subtotal,
            'costo_bolivares' => $costoBs,
            'porcentaje_ganancia' => $porcentaje,
            'subtotal' => $subtotal,
            'total' => $total,
            'updated_by' => Auth::user()?->name,
        ]);

        $quote->update([
            'quote_pdf_path' => OperationQuoteGeneratorPdfService::store($quote->fresh(), $record, $bcvRate),
        ]);

        $record->neto = $subtotal;
        $record->porcen_tdec = $porcentaje;
        $record->quote_price = $total;
        $record->updated_by = Auth::user()?->name;
        $record->save();

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     * @return array<int, array<string, mixed>>
     */
    private static function mergeQuoteLineItemsIntoItemsPayload(
        OperationQuoteGenerator $quote,
        array $lineItems,
        float $bcvRate
    ): array {
        $original = collect(is_array($quote->items) ? $quote->items : [])->keyBy('key');

        return collect($lineItems)
            ->map(function (array $line) use ($original, $bcvRate): array {
                $key = (string) ($line['key'] ?? '');
                $orig = $original->get($key, []);
                $unitUsd = OperationCoordinationServicesTable::decimalOrNull($line['unit_price_usd'] ?? null);
                $unitVes = OperationCoordinationServicesTable::decimalOrNull($line['unit_price_ves'] ?? null)
                    ?? ($unitUsd !== null ? round($unitUsd * $bcvRate, 2) : null);

                return array_merge(is_array($orig) ? $orig : [], [
                    'unit_price_usd' => $unitUsd,
                    'unit_price_ves' => $unitVes,
                ]);
            })
            ->values()
            ->all();
    }

    public static function isQuotePendingForApproval(OperationQuoteGenerator $quote): bool
    {
        return ($quote->status ?? OperationQuoteGenerator::STATUS_PENDING) === OperationQuoteGenerator::STATUS_PENDING
            && blank($quote->operation_service_order_id);
    }

    public static function quoteStatusUpdateIsLocked(OperationQuoteGenerator $quote): bool
    {
        if (filled($quote->operation_service_order_id)) {
            return true;
        }

        return OperationQuoteGenerator::isTerminalStatus($quote->status);
    }

    public static function shouldShowQuoteInManagementRepeater(Get $get, mixed $selectedPendingIds): bool
    {
        if ((bool) $get('has_service_order')) {
            return true;
        }

        $status = (string) ($get('status') ?? OperationQuoteGenerator::STATUS_PENDING);

        if ($status !== OperationQuoteGenerator::STATUS_PENDING) {
            return true;
        }

        $quoteId = (int) $get('quote_id');
        $selected = array_map(intval(...), is_array($selectedPendingIds) ? $selectedPendingIds : []);

        return in_array($quoteId, $selected, true);
    }

    public static function syncQuoteStatusesFromPendingSelection(Get $get, Set $set, OperationCoordinationService $record): void
    {
        $selected = array_map(intval(...), (array) ($get('selected_pending_quote_ids') ?? []));
        $entries = is_array($get('quote_statuses')) ? $get('quote_statuses') : [];
        $updated = [];
        $lastSelectedForOrder = 0;

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $quoteId = (int) ($entry['quote_id'] ?? 0);
            $hasOrder = (bool) ($entry['has_service_order'] ?? false);
            $status = (string) ($entry['status'] ?? OperationQuoteGenerator::STATUS_PENDING);

            if (! $hasOrder && $status === OperationQuoteGenerator::STATUS_PENDING) {
                if (in_array($quoteId, $selected, true)) {
                    $entry['status'] = OperationQuoteGenerator::STATUS_APPROVED;
                    $lastSelectedForOrder = $quoteId;
                }
            }

            $updated[] = $entry;
        }

        $set('quote_statuses', $updated);

        if ($lastSelectedForOrder > 0 && count($selected) === 1) {
            self::prefillServiceOrderFormFromQuote($set, $record, $lastSelectedForOrder);

            return;
        }

        if (count($selected) === 0) {
            $set('approved_quote_id', null);

            return;
        }

        $set('approved_quote_id', (int) $selected[array_key_last($selected)]);
    }

    public static function syncSelectedPendingQuotesFromStatusChange(Get $get, Set $set, int $quoteId, ?string $status): void
    {
        $selected = array_map(intval(...), (array) ($get('selected_pending_quote_ids') ?? []));

        if ($status === OperationQuoteGenerator::STATUS_APPROVED && ! in_array($quoteId, $selected, true)) {
            $selected[] = $quoteId;
            $set('selected_pending_quote_ids', array_values(array_unique($selected)));

            return;
        }

        if (! in_array($status, [OperationQuoteGenerator::STATUS_APPROVED], true)) {
            $set(
                'selected_pending_quote_ids',
                array_values(array_filter($selected, fn (int $id): bool => $id !== $quoteId))
            );
        }

        if ($status === OperationQuoteGenerator::STATUS_PRIVATE_CARE) {
            $set('approved_quote_id', null);
        }
    }

    public static function multiOrderCreationNotice(Get $get): HtmlString
    {
        $quoteIds = self::approvedQuoteIdsPendingOrderInForm($get('quote_statuses'));

        if (count($quoteIds) <= 1) {
            return new HtmlString('');
        }

        $types = OperationQuoteGenerator::query()
            ->whereIn('id', $quoteIds)
            ->pluck('type_service')
            ->unique()
            ->values();

        if ($types->count() > 1) {
            return new HtmlString(
                '<div class="rounded-xl border border-rose-200/90 bg-rose-50/90 px-4 py-3 text-sm text-rose-950 dark:border-rose-500/30 dark:bg-rose-950/35 dark:text-rose-50">'
                .'Las cotizaciones seleccionadas deben ser del mismo tipo de servicio para crear órdenes en un solo guardado. Ajuste la selección o guarde por separado.'
                .'</div>'
            );
        }

        return new HtmlString(
            '<div class="rounded-xl border border-sky-200/90 bg-sky-50/90 px-4 py-3 text-sm text-sky-950 dark:border-sky-500/30 dark:bg-sky-950/35 dark:text-sky-50">'
            .'Se crearán <strong>'.count($quoteIds).'</strong> órdenes de servicio (tipo <strong>'.e((string) $types->first()).'</strong>) con los mismos datos operativos y números consecutivos.'
            .'</div>'
        );
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $quoteStatuses
     * @return array<int>
     */
    public static function approvedQuoteIdsPendingOrderInForm(mixed $quoteStatuses): array
    {
        if (! is_array($quoteStatuses)) {
            return [];
        }

        return collect($quoteStatuses)
            ->filter(
                fn (array $entry): bool => ($entry['status'] ?? null) === OperationQuoteGenerator::STATUS_APPROVED
                    && ! (bool) ($entry['has_service_order'] ?? false)
            )
            ->map(fn (array $entry): int => (int) ($entry['quote_id'] ?? 0))
            ->filter(fn (int $quoteId): bool => $quoteId > 0)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function applyPendingSelectionToFormData(array $data): array
    {
        $selected = array_map(intval(...), (array) ($data['selected_pending_quote_ids'] ?? []));
        $entries = is_array($data['quote_statuses'] ?? null) ? $data['quote_statuses'] : [];

        foreach ($entries as $index => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $quoteId = (int) ($entry['quote_id'] ?? 0);
            $hasOrder = (bool) ($entry['has_service_order'] ?? false);
            $statusLocked = (bool) ($entry['status_locked'] ?? false);
            $status = (string) ($entry['status'] ?? OperationQuoteGenerator::STATUS_PENDING);

            if ($statusLocked) {
                continue;
            }

            if (! $hasOrder && $status === OperationQuoteGenerator::STATUS_PENDING && in_array($quoteId, $selected, true)) {
                $entries[$index]['status'] = OperationQuoteGenerator::STATUS_APPROVED;
            }
        }

        $data['quote_statuses'] = $entries;

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $quoteStatuses
     */
    public static function hasApprovedQuotePendingOrderInForm(mixed $quoteStatuses): bool
    {
        if (! is_array($quoteStatuses)) {
            return false;
        }

        return collect($quoteStatuses)->contains(
            fn (array $entry): bool => ($entry['status'] ?? null) === OperationQuoteGenerator::STATUS_APPROVED
                && ! (bool) ($entry['has_service_order'] ?? false)
        );
    }

    public static function approvedQuoteServiceType(int $quoteId): ?string
    {
        if ($quoteId <= 0) {
            return null;
        }

        return OperationQuoteGenerator::query()->whereKey($quoteId)->value('type_service');
    }

    public static function approvedQuoteOrderNotice(int $quoteId): HtmlString
    {
        $quote = OperationQuoteGenerator::query()->with('supplier')->find($quoteId);

        if (! $quote instanceof OperationQuoteGenerator) {
            return new HtmlString(
                '<div class="rounded-xl border border-amber-200/80 bg-amber-50/80 px-4 py-3 text-sm text-amber-950 dark:border-amber-500/25 dark:bg-amber-950/25 dark:text-amber-50">Seleccione una cotización aprobada para completar la orden de servicio.</div>'
            );
        }

        $providerLine = filled($quote->supplier?->name)
            ? '<p class="mt-2 text-sm opacity-90">Proveedor asignado en cotización: <strong>'.e((string) $quote->supplier->name).'</strong>'
                .(filled($quote->supplier_address)
                    ? ' · '.e((string) $quote->supplier_address)
                    : '')
                .'</p>'
            : '<p class="mt-2 text-sm text-amber-800/90 dark:text-amber-200/80">Esta cotización no tiene proveedor asignado. Edítela antes de crear la orden.</p>';

        return new HtmlString(
            '<div class="rounded-2xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50/95 to-white px-4 py-3 text-sm text-emerald-950 dark:border-emerald-500/30 dark:from-emerald-950/35 dark:to-zinc-900/90 dark:text-emerald-50">'
            .'<p class="font-semibold">Orden para cotización #'.e((string) $quote->id).'</p>'
            .'<p class="mt-1 opacity-90">Tipo <strong>'.e((string) $quote->type_service).'</strong> · Total '.e(self::formatManageQuoteAmountPreview((float) $quote->total)).'. Los campos operativos se completaron automáticamente y puede ajustarlos antes de guardar.</p>'
            .$providerLine
            .'</div>'
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public static function mergeOrderDataWithQuoteProvider(array $data, OperationQuoteGenerator $quote): ?array
    {
        $quote->loadMissing('supplier');

        if (! filled($quote->supplier_id)) {
            Notification::make()
                ->title('Orden de servicio')
                ->body('La cotización #'.$quote->id.' no tiene proveedor asignado. Edite la cotización y seleccione un proveedor antes de crear la orden.')
                ->warning()
                ->send();

            return null;
        }

        return array_merge($data, [
            'supplier_id' => (int) $quote->supplier_id,
            'doctor_nurse_id' => null,
            'supplier_external' => null,
            'register_unregistered_provider' => false,
            'unregistered_provider_type' => null,
            'unregistered_name' => null,
            'unregistered_rif' => null,
            'unregistered_phone' => null,
            'unregistered_correo_principal' => null,
            'unregistered_ubicacion_principal' => null,
        ]);
    }

    public static function prefillServiceOrderFormFromQuote(Set $set, OperationCoordinationService $record, int $quoteId): void
    {
        $quote = OperationQuoteGenerator::query()->find($quoteId);

        if (! $quote instanceof OperationQuoteGenerator || filled($quote->operation_service_order_id)) {
            return;
        }

        $itemsCount = count(is_array($quote->items) ? $quote->items : []);

        $set('approved_quote_id', $quote->id);
        $set('order_number', self::nextServiceOrderNumber());
        $set('telemedicine_priority_id', $record->telemedicine_priority_id);
        $set('service_order_description', sprintf(
            'Orden por cotización #%d · %s · %d ítem(s) · Total %s',
            $quote->id,
            $quote->type_service,
            $itemsCount,
            self::formatManageQuoteAmountPreview((float) $quote->total)
        ));
        $set('service_order_observations', sprintf(
            'Generada automáticamente desde cotización #%d (costo base %s, ganancia %s%%).',
            $quote->id,
            self::formatManageQuoteAmountPreview((float) $quote->subtotal),
            number_format((float) $quote->porcentaje_ganancia, 2, '.', '')
        ));
    }

    /**
     * @return int|null ID de la orden creada (> 0), 0 si guardó sin crear orden, null si la validación falló.
     */
    public static function save(OperationCoordinationService $record, array $data): ?int
    {
        $data = self::applyPendingSelectionToFormData($data);
        $entries = is_array($data['quote_statuses'] ?? null) ? $data['quote_statuses'] : [];
        $quotesPendingOrder = self::approvedQuoteIdsPendingOrderInForm($entries);
        $shouldCreateOrder = $quotesPendingOrder !== [];

        if ($shouldCreateOrder) {
            if (blank($data['order_number'] ?? null) || blank($data['service_order_description'] ?? null)) {
                Notification::make()
                    ->title('Orden de servicio')
                    ->body('Complete número y descripción de la orden para las cotizaciones aprobadas.')
                    ->warning()
                    ->send();

                return null;
            }

            $types = OperationQuoteGenerator::query()
                ->whereIn('id', $quotesPendingOrder)
                ->pluck('type_service')
                ->unique();

            if ($types->count() > 1) {
                Notification::make()
                    ->title('Orden de servicio')
                    ->body('Las cotizaciones aprobadas pendientes de orden deben ser del mismo tipo de servicio para guardar en un solo paso.')
                    ->warning()
                    ->send();

                return null;
            }
        }

        $ordersCreated = 0;
        $createdOrderId = 0;
        $privateCareItemsFinalized = 0;

        DB::transaction(function () use ($record, $data, $entries, $shouldCreateOrder, $quotesPendingOrder, &$ordersCreated, &$createdOrderId, &$privateCareItemsFinalized): void {
            foreach ($entries as $entry) {
                $quoteId = (int) ($entry['quote_id'] ?? 0);
                $quote = OperationQuoteGenerator::query()->find($quoteId);

                if (! $quote instanceof OperationQuoteGenerator) {
                    continue;
                }

                if (self::quoteStatusUpdateIsLocked($quote)) {
                    continue;
                }

                $status = (string) ($entry['status'] ?? OperationQuoteGenerator::STATUS_PENDING);
                $quote->status = $status;
                $quote->updated_by = Auth::user()?->name;
                $quote->save();

                if ($status === OperationQuoteGenerator::STATUS_PRIVATE_CARE) {
                    $privateCareItemsFinalized += self::finalizeClinicalItemsForPrivateCareQuote($record, $quote);
                }
            }

            if ($shouldCreateOrder) {
                foreach ($quotesPendingOrder as $index => $quoteId) {
                    $quote = OperationQuoteGenerator::query()->find($quoteId);

                    if (! $quote instanceof OperationQuoteGenerator || filled($quote->operation_service_order_id)) {
                        continue;
                    }

                    $orderData = self::mergeOrderDataWithQuoteProvider($data, $quote);

                    if ($orderData === null) {
                        continue;
                    }

                    if ($index > 0) {
                        $orderData['order_number'] = self::nextServiceOrderNumber();
                    }

                    $orderId = self::createServiceOrderFromApprovedQuote($record, $orderData, $quote);

                    if ($orderId > 0) {
                        $quote->status = OperationQuoteGenerator::STATUS_APPROVED;
                        $quote->operation_service_order_id = $orderId;
                        $quote->updated_by = Auth::user()?->name;
                        $quote->save();
                        $ordersCreated++;
                        $createdOrderId = $orderId;
                    }
                }
            }
        });

        $body = 'Se actualizaron los estatus de las cotizaciones.';

        if ($ordersCreated > 0) {
            $body .= $ordersCreated === 1
                ? ' Se creó la orden de servicio vinculada a la cotización aprobada.'
                : ' Se crearon '.$ordersCreated.' órdenes de servicio.';
        }

        if ($privateCareItemsFinalized > 0) {
            $body .= ' Se finalizaron '.$privateCareItemsFinalized.' ítem(s) clínico(s) por atención particular sin generar orden de servicio.';
        }

        Notification::make()
            ->title('Cotizaciones gestionadas')
            ->body($body)
            ->success()
            ->send();

        return $createdOrderId > 0 ? $createdOrderId : 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     */
    public static function resolveApprovedQuoteIdForOrderCreation(array $entries, int $approvedQuoteId): int
    {
        if ($approvedQuoteId > 0) {
            return $approvedQuoteId;
        }

        foreach ($entries as $entry) {
            $entryQuoteId = (int) ($entry['quote_id'] ?? 0);

            if (
                $entryQuoteId > 0
                && ($entry['status'] ?? null) === OperationQuoteGenerator::STATUS_APPROVED
                && ! (bool) ($entry['has_service_order'] ?? false)
            ) {
                return $entryQuoteId;
            }
        }

        return 0;
    }

    public static function finalizeClinicalItemsForPrivateCareQuote(
        OperationCoordinationService $record,
        OperationQuoteGenerator $quote
    ): int {
        $keys = collect(is_array($quote->items) ? $quote->items : [])
            ->pluck('key')
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();

        $records = OperationCoordinationServicesTable::selectedServiceOrderRecordsByType(
            $record,
            OperationCoordinationServicesTable::managementKeysToNumericIds($keys),
            (string) $quote->type_service
        );

        if ($records->isEmpty()) {
            return 0;
        }

        $terminalStatuses = ['FINALIZADO', 'CANCELADA', 'CANCELADO', 'CADUCADA'];
        $updated = 0;

        foreach ($records as $item) {
            $currentStatus = mb_strtoupper(trim((string) ($item->status ?? '')));

            if (in_array($currentStatus, $terminalStatuses, true)) {
                continue;
            }

            $item->update(['status' => 'FINALIZADO']);
            $updated++;
        }

        if ($updated > 0) {
            $freshRecord = $record->fresh() ?? $record;
            OperationServiceOrderCoordinationSync::refreshCoordinationStatus($freshRecord);
        }

        return $updated;
    }

    public static function createServiceOrderFromApprovedQuote(
        OperationCoordinationService $record,
        array $data,
        OperationQuoteGenerator $quote
    ): int {
        $keys = collect(is_array($quote->items) ? $quote->items : [])
            ->pluck('key')
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();

        $records = OperationCoordinationServicesTable::selectedServiceOrderRecordsByType(
            $record,
            OperationCoordinationServicesTable::managementKeysToNumericIds($keys),
            (string) $quote->type_service
        );

        if ($records->isEmpty()) {
            Notification::make()
                ->title('Orden de servicio')
                ->body('Los ítems de la cotización aprobada no están disponibles para crear la orden.')
                ->warning()
                ->send();

            return 0;
        }

        $payload = OperationCoordinationServicesTable::buildServiceOrderPayload($record, $data, (string) $quote->type_service);

        if ($payload === null) {
            return 0;
        }

        if ($quote->type_service === 'MEDICAMENTOS') {
            $payload['medications_list'] = $records->map(fn (TelemedicinePatientMedications $item): array => [
                'quantity' => 1,
                'indications' => $item->indications ?? null,
            ])->values()->all();
        }

        OperationServiceOrderController::create($payload, $record->toArray(), $records);

        $order = OperationServiceOrder::query()
            ->where('operation_coordination_service_id', $record->id)
            ->where('order_number', (string) ($payload['order_number'] ?? ''))
            ->latest('id')
            ->first();

        if ($order instanceof OperationServiceOrder) {
            $order->associated_quote_pdf_path = self::resolveQuotePdfPathForOrder($quote, $record);
            $order->updated_by = Auth::user()?->name;
            $order->save();

            $record->service_order_number = $payload['order_number'];
            $record->updated_by = Auth::user()?->name;
            $record->save();

            return (int) $order->id;
        }

        return 0;
    }

    public static function resolveQuotePdfPathForOrder(
        OperationQuoteGenerator $quote,
        OperationCoordinationService $record
    ): ?string {
        if (filled($quote->quote_pdf_path)) {
            return (string) $quote->quote_pdf_path;
        }

        try {
            $subtotalUsd = (float) ($quote->subtotal ?? 0);
            $bcvRate = $subtotalUsd > 0
                ? ((float) ($quote->costo_bolivares ?? 0) / $subtotalUsd)
                : (OperationCoordinationServicesTable::referenciaTasaBcvDesdeApi() ?? 1.0);

            if ($bcvRate <= 0) {
                $bcvRate = OperationCoordinationServicesTable::referenciaTasaBcvDesdeApi() ?? 1.0;
            }

            $path = OperationQuoteGeneratorPdfService::store($quote, $record, $bcvRate);
            $quote->quote_pdf_path = $path;
            $quote->updated_by = Auth::user()?->name;
            $quote->save();

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }
}
