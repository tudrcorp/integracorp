<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Models\OperationServiceOrderItem;
use App\Services\OperationQuoteGeneratorPdfService;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

final class CoordinationServiceAssociatedItemPricePreview
{
    /**
     * @param  array{id?: int|string, item_type?: string, title?: string, provider_name?: ?string, quote_id?: int|null, order_id?: int|null}  $row
     */
    public static function makeAction(array $row): ?Action
    {
        $itemId = (int) ($row['id'] ?? 0);
        $itemType = (string) ($row['item_type'] ?? '');
        $quoteId = (int) ($row['quote_id'] ?? 0);
        $orderId = (int) ($row['order_id'] ?? 0);
        $title = (string) ($row['title'] ?? 'Ítem');
        $providerName = filled($row['provider_name'] ?? null) ? (string) $row['provider_name'] : '—';

        if ($itemId <= 0 || ($quoteId <= 0 && $orderId <= 0)) {
            return null;
        }

        return Action::make('previewAssociatedItemPrices_'.$itemType.'_'.$itemId)
            ->label('Ver precios')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('info')
            ->iconButton()
            ->tooltip('Ver precios de cotización y orden de servicio')
            ->modalHeading('Precios de la gestión')
            ->modalDescription(fn (): HtmlString => new HtmlString(
                '<p class="text-sm text-gray-600 dark:text-gray-300">'
                .'Vista de precios de <span class="font-semibold text-gray-900 dark:text-white">'.e($title).'</span>'
                .' · Proveedor: <span class="font-semibold text-gray-900 dark:text-white">'.e($providerName).'</span>.'
                .'</p>'
            ))
            ->modalIcon(Heroicon::OutlinedBanknotes)
            ->modalIconColor('info')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->closeModalByClickingAway(true)
            ->modalContent(fn (): HtmlString => self::renderForIds($quoteId, $orderId, $title, $providerName));
    }

    public static function renderForIds(
        int $quoteId,
        int $orderId,
        string $itemTitle,
        string $providerName,
    ): HtmlString {
        $quote = $quoteId > 0
            ? OperationQuoteGenerator::query()->with('supplier')->find($quoteId)
            : null;
        $order = $orderId > 0
            ? OperationServiceOrder::query()
                ->with(['operationServiceOrderItems', 'supplier', 'doctorNurse', 'approvedOperationQuote.supplier'])
                ->find($orderId)
            : null;

        if ($quote === null && $order instanceof OperationServiceOrder && $order->approvedOperationQuote instanceof OperationQuoteGenerator) {
            $quote = $order->approvedOperationQuote;
        }

        return self::render($quote, $order, $itemTitle, $providerName);
    }

    public static function render(
        ?OperationQuoteGenerator $quote,
        ?OperationServiceOrder $order,
        string $itemTitle,
        string $providerName,
    ): HtmlString {
        if (! $quote instanceof OperationQuoteGenerator && ! $order instanceof OperationServiceOrder) {
            return new HtmlString(
                '<div class="rounded-2xl border border-dashed border-gray-300/80 px-4 py-6 text-center text-sm text-gray-600 dark:border-white/15 dark:text-gray-300">'
                .'No hay cotización ni orden de servicio asociadas a este ítem.'
                .'</div>'
            );
        }

        $sections = [];

        if ($quote instanceof OperationQuoteGenerator) {
            $sections[] = self::renderQuoteSection($quote, $itemTitle);
        }

        if ($order instanceof OperationServiceOrder) {
            $sections[] = self::renderOrderSection($order, $itemTitle);
        }

        return new HtmlString(
            '<div class="space-y-4">'
            .'<p class="text-xs text-gray-500 dark:text-gray-400">Proveedor: <span class="font-semibold text-gray-800 dark:text-gray-100">'.e($providerName).'</span></p>'
            .implode('', $sections)
            .'</div>'
        );
    }

    private static function renderQuoteSection(OperationQuoteGenerator $quote, string $itemTitle): string
    {
        $items = collect(is_array($quote->items) ? $quote->items : []);
        $currentLabel = self::itemTitleLabel($itemTitle);

        $rows = $items->map(function (array $item) use ($currentLabel): string {
            $label = trim((string) ($item['label'] ?? ''));
            $isCurrent = $currentLabel !== '' && mb_strtoupper($label) === $currentLabel;
            $rowClass = $isCurrent
                ? 'bg-sky-50/80 dark:bg-sky-500/10'
                : 'border-b border-gray-100 last:border-0 dark:border-white/10';

            return '<tr class="'.$rowClass.'">'
                .'<td class="px-3 py-2">'.e((string) ($item['category'] ?? '—')).'</td>'
                .'<td class="px-3 py-2 font-medium">'.e($label !== '' ? $label : '—').'</td>'
                .'<td class="px-3 py-2 text-right tabular-nums">'.e(self::moneyUsd(self::decimalOrNull($item['unit_price_usd'] ?? null))).'</td>'
                .'<td class="px-3 py-2 text-right tabular-nums">'.e(self::moneyVes(self::decimalOrNull($item['unit_price_ves'] ?? null))).'</td>'
                .'</tr>';
        })->implode('');

        $table = $items->isEmpty()
            ? '<p class="text-sm text-gray-600 dark:text-gray-300">Sin ítems registrados en la cotización.</p>'
            : '<div class="overflow-x-auto rounded-xl border border-gray-200/90 dark:border-white/10">'
                .'<table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-white/10">'
                .'<thead class="bg-gray-50/90 dark:bg-white/5"><tr>'
                .'<th class="px-3 py-2 text-left font-semibold">Categoría</th>'
                .'<th class="px-3 py-2 text-left font-semibold">Ítem</th>'
                .'<th class="px-3 py-2 text-right font-semibold">Precio USD</th>'
                .'<th class="px-3 py-2 text-right font-semibold">Precio VES</th>'
                .'</tr></thead><tbody>'.$rows.'</tbody></table></div>';

        $quoteNumber = CoordinationServiceQuoteManager::formatCoordinationQuoteNumber((int) $quote->id);
        $pdfLink = self::quotePdfLink($quote);

        return '<section class="fi-associated-item-price-preview-quote space-y-3 rounded-2xl border border-amber-200/70 bg-gradient-to-br from-amber-50/70 via-white to-white p-4 dark:border-amber-500/20 dark:from-amber-950/20 dark:via-zinc-900/90 dark:to-zinc-900/90">'
            .'<div class="flex flex-wrap items-start justify-between gap-3">'
            .'<div><p class="text-xs font-semibold uppercase tracking-wide text-amber-800/80 dark:text-amber-200/70">Cotización '.e($quoteNumber).'</p>'
            .'<p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">'.e((string) ($quote->type_service ?: 'Cotización de servicio')).'</p></div>'
            .'<div class="text-right"><p class="text-xs text-gray-500 dark:text-gray-400">Total</p>'
            .'<p class="text-lg font-bold text-emerald-700 dark:text-emerald-300">'.e(self::moneyUsd(self::decimalOrNull($quote->total))).'</p>'
            .'<p class="text-xs text-gray-500 dark:text-gray-400">'.e(self::moneyVes(self::decimalOrNull($quote->costo_bolivares))).'</p></div>'
            .'</div>'
            .'<div class="grid gap-3 sm:grid-cols-3">'
            .CoordinationServiceQuoteManager::manageQuoteSummaryRow('Costo base', self::moneyUsd(self::decimalOrNull($quote->costo_dolares)), 'slate')
            .CoordinationServiceQuoteManager::manageQuoteSummaryRow('Ganancia', number_format((float) $quote->porcentaje_ganancia, 2, '.', '').'%', 'amber')
            .CoordinationServiceQuoteManager::manageQuoteSummaryRow('Subtotal', self::moneyUsd(self::decimalOrNull($quote->subtotal)), 'slate')
            .'</div>'
            .$table
            .$pdfLink
            .'</section>';
    }

    private static function renderOrderSection(OperationServiceOrder $order, string $itemTitle): string
    {
        $items = $order->relationLoaded('operationServiceOrderItems')
            ? $order->operationServiceOrderItems
            : collect();
        $currentLabel = self::itemTitleLabel($itemTitle);

        $rows = $items->map(function (mixed $item) use ($currentLabel): string {
            if (! $item instanceof OperationServiceOrderItem) {
                return '';
            }

            $label = trim((string) ($item->item_name ?? ''));
            $isCurrent = $currentLabel !== '' && mb_strtoupper($label) === $currentLabel;
            $rowClass = $isCurrent
                ? 'bg-sky-50/80 dark:bg-sky-500/10'
                : 'border-b border-gray-100 last:border-0 dark:border-white/10';
            $quantity = max(1, (int) ($item->quantity ?? 1));
            $unit = self::decimalOrNull($item->amount);
            $lineTotal = $unit !== null ? round($unit * $quantity, 2) : null;

            return '<tr class="'.$rowClass.'">'
                .'<td class="px-3 py-2">'.e((string) ($item->category ?? '—')).'</td>'
                .'<td class="px-3 py-2 font-medium">'.e($label !== '' ? $label : '—').'</td>'
                .'<td class="px-3 py-2 text-right tabular-nums">'.e((string) $quantity).'</td>'
                .'<td class="px-3 py-2 text-right tabular-nums">'.e(self::moneyUsd($unit)).'</td>'
                .'<td class="px-3 py-2 text-right tabular-nums">'.e(self::moneyUsd($lineTotal)).'</td>'
                .'</tr>';
        })->implode('');

        $table = $items->isEmpty()
            ? '<p class="text-sm text-gray-600 dark:text-gray-300">Sin ítems registrados en la orden.</p>'
            : '<div class="overflow-x-auto rounded-xl border border-gray-200/90 dark:border-white/10">'
                .'<table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-white/10">'
                .'<thead class="bg-gray-50/90 dark:bg-white/5"><tr>'
                .'<th class="px-3 py-2 text-left font-semibold">Categoría</th>'
                .'<th class="px-3 py-2 text-left font-semibold">Ítem</th>'
                .'<th class="px-3 py-2 text-right font-semibold">Cant.</th>'
                .'<th class="px-3 py-2 text-right font-semibold">Precio USD</th>'
                .'<th class="px-3 py-2 text-right font-semibold">Total USD</th>'
                .'</tr></thead><tbody>'.$rows.'</tbody></table></div>';

        $orderNumber = filled($order->order_number) ? (string) $order->order_number : '#'.(string) ($order->id ?? '—');

        return '<section class="fi-associated-item-price-preview-order space-y-3 rounded-2xl border border-sky-200/70 bg-gradient-to-br from-sky-50/70 via-white to-white p-4 dark:border-sky-500/20 dark:from-sky-950/20 dark:via-zinc-900/90 dark:to-zinc-900/90">'
            .'<div class="flex flex-wrap items-start justify-between gap-3">'
            .'<div><p class="text-xs font-semibold uppercase tracking-wide text-sky-800/80 dark:text-sky-200/70">Orden de servicio '.e($orderNumber).'</p>'
            .'<p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">'.e((string) ($order->service_type ?: 'Gestión de servicio')).'</p></div>'
            .'<div class="text-right"><p class="text-xs text-gray-500 dark:text-gray-400">Total</p>'
            .'<p class="text-lg font-bold text-emerald-700 dark:text-emerald-300">'.e(self::moneyUsd(self::decimalOrNull($order->total_amount_usd))).'</p>'
            .'<p class="text-xs text-gray-500 dark:text-gray-400">'.e(self::moneyVes(self::decimalOrNull($order->total_amount_ves))).'</p></div>'
            .'</div>'
            .'<div class="grid gap-3 sm:grid-cols-2">'
            .CoordinationServiceQuoteManager::manageQuoteSummaryRow('Tasa BCV', self::decimalOrNull($order->tasa_bcv) !== null ? number_format((float) $order->tasa_bcv, 4, '.', ',') : '—', 'slate')
            .CoordinationServiceQuoteManager::manageQuoteSummaryRow('Estatus', filled($order->status) ? (string) $order->status : '—', 'slate')
            .'</div>'
            .$table
            .self::orderPdfLinks($order)
            .'</section>';
    }

    private static function quotePdfLink(OperationQuoteGenerator $quote): string
    {
        if (! filled($quote->quote_pdf_path)) {
            return '';
        }

        $pdfUrl = URL::to(Storage::url((string) $quote->quote_pdf_path));
        $downloadName = e(OperationQuoteGeneratorPdfService::filename($quote));

        return '<div>'.self::documentLink($pdfUrl, $downloadName, 'Ver PDF de cotización').'</div>';
    }

    private static function orderPdfLinks(OperationServiceOrder $order): string
    {
        $links = [];

        if (filled($order->associated_quote_pdf_path)) {
            $links[] = self::documentLink(
                URL::to(Storage::url((string) $order->associated_quote_pdf_path)),
                'cotizacion-orden.pdf',
                'Ver PDF de cotización asociada',
            );
        }

        if (filled($order->service_order_pdf_path)) {
            $links[] = self::documentLink(
                URL::to(Storage::url((string) $order->service_order_pdf_path)),
                'orden-servicio.pdf',
                'Ver PDF de orden de servicio',
            );
        }

        if ($links === []) {
            return '';
        }

        return '<div class="flex flex-wrap gap-2">'.implode('', $links).'</div>';
    }

    private static function documentLink(string $url, string $downloadName, string $label): string
    {
        return '<a href="'.e($url).'" download="'.e($downloadName).'" target="_blank" rel="noopener noreferrer" '
            .'class="inline-flex items-center gap-1.5 rounded-full border-b-2 border-primary-600 bg-primary-500/15 px-3 py-1.5 text-xs font-semibold text-primary-700 no-underline dark:border-primary-500 dark:bg-primary-500/25 dark:text-primary-300">'
            .e($label)
            .'</a>';
    }

    private static function itemTitleLabel(string $title): string
    {
        $normalized = trim($title);

        foreach (['Laboratorio: ', 'Medicamento: ', 'Estudio: ', 'Especialidad: ', 'Servicio: '] as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return mb_strtoupper(trim(substr($normalized, strlen($prefix))));
            }
        }

        return mb_strtoupper($normalized);
    }

    private static function moneyUsd(?float $amount): string
    {
        return CoordinationServiceQuoteManager::formatManageQuoteAmountPreview($amount);
    }

    private static function moneyVes(?float $amount): string
    {
        return CoordinationServiceQuoteManager::formatManageQuoteAmountPreview($amount, 'VES');
    }

    private static function decimalOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }
}
