<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTable;
use App\Http\Controllers\OperationServiceOrderController;
use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use App\Services\OperationQuoteGeneratorPdfService;
use App\Support\Telemedicine\TelemedicineCaseTdgReassignmentCoordination;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

final class CoordinationServiceItemsManager
{
    public static function coverageValue(?string $serviceOrderType, mixed $row): ?bool
    {
        if ($serviceOrderType === 'MEDICAMENTOS') {
            if (isset($row->operationInventory) && $row->operationInventory !== null && $row->operationInventory->is_covered !== null) {
                return (bool) $row->operationInventory->is_covered;
            }

            return isset($row->is_covered) ? (bool) $row->is_covered : null;
        }

        if (isset($row->type) && is_string($row->type)) {
            return mb_strtoupper(trim($row->type)) === 'CUBIERTO';
        }

        return null;
    }

    public static function coverageLabel(?bool $isCovered): string
    {
        return match ($isCovered) {
            true => 'Cubierto',
            false => 'No cubierto',
            default => 'Sin dato',
        };
    }

    public static function manageServiceActionIsDisabled(OperationCoordinationService $record): bool
    {
        TelemedicineCaseTdgReassignmentCoordination::ensureAmdManagementItem($record);

        return self::manageServiceSelectableOptions($record) === [];
    }

    /**
     * @return Collection<int, array{
     *     key: string,
     *     category: string,
     *     label: string,
     *     detail: string,
     *     coverage: bool|null,
     *     coverage_label: string,
     *     status: string,
     *     selectable: bool
     * }>
     */
    public static function associatedServiceItemsForManagement(OperationCoordinationService $record): Collection
    {
        TelemedicineCaseTdgReassignmentCoordination::ensureAmdManagementItem($record);

        $items = collect();

        $record->telemedicinePatientMedications()
            ->orderBy('id')
            ->with('operationInventory:id,is_covered')
            ->get(['id', 'medicine', 'indications', 'status', 'is_covered', 'operation_inventory_id'])
            ->each(function (TelemedicinePatientMedications $item) use ($items): void {
                $coverage = self::coverageValue('MEDICAMENTOS', $item);
                $items->push([
                    'key' => 'medication:'.$item->id,
                    'category' => 'Medicamento',
                    'label' => (string) ($item->medicine ?? 'Medicamento sin nombre'),
                    'detail' => (string) ($item->indications ?? '—'),
                    'coverage' => $coverage,
                    'coverage_label' => self::coverageLabel($coverage),
                    'status' => (string) ($item->status ?? '—'),
                    'selectable' => ($item->status ?? null) !== 'EN GESTION',
                ]);
            });

        $record->telemedicinePatientLabs()
            ->orderBy('id')
            ->get(['id', 'laboratory', 'type', 'status'])
            ->each(function (TelemedicinePatientLab $item) use ($items): void {
                $coverage = self::coverageValue('LABORATORIOS', $item);
                $items->push([
                    'key' => 'lab:'.$item->id,
                    'category' => 'Laboratorio',
                    'label' => (string) ($item->laboratory ?? 'Laboratorio sin nombre'),
                    'detail' => (string) ($item->type ?? '—'),
                    'coverage' => $coverage,
                    'coverage_label' => self::coverageLabel($coverage),
                    'status' => (string) ($item->status ?? '—'),
                    'selectable' => ($item->status ?? null) !== 'EN GESTION',
                ]);
            });

        $record->telemedicinePatientStudies()
            ->orderBy('id')
            ->get(['id', 'study', 'type', 'status'])
            ->each(function (TelemedicinePatientStudy $item) use ($items): void {
                $coverage = self::coverageValue('IMAGENOLOGIA', $item);
                $items->push([
                    'key' => 'study:'.$item->id,
                    'category' => 'Estudio',
                    'label' => (string) ($item->study ?? 'Estudio sin nombre'),
                    'detail' => (string) ($item->type ?? '—'),
                    'coverage' => $coverage,
                    'coverage_label' => self::coverageLabel($coverage),
                    'status' => (string) ($item->status ?? '—'),
                    'selectable' => ($item->status ?? null) !== 'EN GESTION',
                ]);
            });

        $record->telemedicinePatientSpecialties()
            ->orderBy('id')
            ->get(['id', 'specialty', 'type', 'status'])
            ->each(function (TelemedicinePatientSpecialty $item) use ($items): void {
                $coverage = self::coverageValue('ESPECIALISTA', $item);
                $items->push([
                    'key' => 'specialty:'.$item->id,
                    'category' => 'Especialista',
                    'label' => (string) ($item->specialty ?? 'Especialidad sin nombre'),
                    'detail' => (string) ($item->type ?? '—'),
                    'coverage' => $coverage,
                    'coverage_label' => self::coverageLabel($coverage),
                    'status' => (string) ($item->status ?? '—'),
                    'selectable' => ($item->status ?? null) !== 'EN GESTION',
                ]);
            });

        return $items;
    }

    public static function manageServiceItemsContextHeader(OperationCoordinationService $record): HtmlString
    {
        return new HtmlString(
            '<div class="fi-manage-service-context-panel">'
            .'<div class="fi-manage-service-context-body">'
            .'<p><span class="fi-manage-service-context-label">Paciente:</span> '.e($record->patient ?? '—').'</p>'
            .'<p><span class="fi-manage-service-context-label">Referencia:</span> '.e($record->reference_number ?? '—')
            .' · <span class="fi-manage-service-context-label">Servicio:</span> '.e($record->specific_service ?? $record->servicie ?? '—').'</p>'
            .'<p class="fi-manage-service-context-hint">Seleccione ítems pendientes. Los cubiertos habilitan orden de servicio; los no cubiertos requieren cotización.</p>'
            .'</div>'
            .'</div>'
        );
    }

    public static function manageServiceEmptyState(): HtmlString
    {
        return new HtmlString(
            '<div class="fi-manage-service-notice fi-manage-service-notice--empty px-6 py-8">'
            .'<p class="text-base font-semibold">No hay ítems pendientes por gestionar</p>'
            .'<p class="fi-manage-service-notice-sub">Todos los ítems asociados ya están en gestión o aún no se han registrado para esta coordinación.</p>'
            .'</div>'
        );
    }

    public static function manageServiceMixedTypesWarning(): HtmlString
    {
        return new HtmlString(
            '<div class="fi-manage-service-notice fi-manage-service-notice--amber">'
            .'<p class="font-semibold">Tipos de servicio mezclados</p>'
            .'<p class="mt-1 leading-relaxed opacity-90">Los ítems cubiertos seleccionados pertenecen a distintos tipos de servicio. Seleccione ítems del mismo tipo para crear una única orden de servicio.</p>'
            .'</div>'
        );
    }

    public static function manageServiceMixedQuoteTypesWarning(): HtmlString
    {
        return new HtmlString(
            '<div class="fi-manage-service-notice fi-manage-service-notice--amber">'
            .'<p class="font-semibold">Tipos de servicio mezclados en ítems no cubiertos</p>'
            .'<p class="mt-1 leading-relaxed opacity-90">Los ítems no cubiertos seleccionados pertenecen a distintos tipos de servicio. Seleccione ítems del mismo tipo para generar una única cotización.</p>'
            .'</div>'
        );
    }

    public static function manageServiceNonCoveredItemsNotice(): HtmlString
    {
        return new HtmlString(
            '<div class="fi-manage-service-notice fi-manage-service-notice--rose px-5">'
            .'<p class="font-semibold">Cotización obligatoria</p>'
            .'<p class="mt-1 leading-relaxed opacity-90">Debe registrar la cotización de los ítems no cubiertos seleccionados antes de confirmar la gestión.</p>'
            .'</div>'
        );
    }

    public static function manageServiceNonCoveredItemsTable(OperationCoordinationService $record, mixed $selectedKeys): HtmlString
    {
        return self::manageServiceSelectedItemsTable(
            $record,
            self::nonCoveredSelectedManagementItemKeys($record, $selectedKeys)
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

    public static function manageQuoteSummaryPanel(Get $get): HtmlString
    {
        $subtotal = self::manageQuoteSubtotalFromLineItems(self::manageQuoteLineItemsState($get))
            ?? self::manageQuoteSubtotal(self::manageQuoteFormFieldState($get, 'manage_quote_costo_dolares'));
        $porcentaje = OperationCoordinationServicesTable::decimalOrNull(self::manageQuoteFormFieldState($get, 'manage_quote_porcentaje_ganancia')) ?? 0.0;
        $total = self::manageQuoteTotal($subtotal, $porcentaje);
        $bcvRate = OperationCoordinationServicesTable::decimalOrNull(self::manageQuoteFormFieldState($get, 'manage_quote_bcv_rate'));
        $ganancia = ($subtotal !== null && $total !== null) ? round($total - $subtotal, 2) : null;
        $totalBs = ($total !== null && $bcvRate !== null) ? round($total * $bcvRate, 2) : null;

        $rows = [
            [
                'label' => 'Subtotal ítems',
                'value' => CoordinationServiceQuoteManager::formatManageQuoteAmountPreview($subtotal),
                'tone' => 'slate',
            ],
            [
                'label' => 'Ganancia ('.number_format($porcentaje, 2, '.', '').'%)',
                'value' => CoordinationServiceQuoteManager::formatManageQuoteAmountPreview($ganancia),
                'tone' => 'amber',
            ],
        ];

        $html = '<div class="fi-manage-quote-summary h-full rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50/95 via-white to-white p-4 shadow-sm dark:border-amber-500/25 dark:from-amber-950/30 dark:via-zinc-900/95 dark:to-zinc-900/90">'
            .'<p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-amber-800/80 dark:text-amber-200/70">Resumen de cotización</p>'
            .'<div class="mt-3 space-y-2.5">';

        foreach ($rows as $row) {
            $html .= CoordinationServiceQuoteManager::manageQuoteSummaryRow($row['label'], $row['value'], $row['tone']);
        }

        $html .= '</div>'
            .'<div class="mt-4 rounded-xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50/95 to-white px-4 py-3.5 dark:border-emerald-500/30 dark:from-emerald-950/35 dark:to-zinc-900/90">'
            .'<p class="text-xs font-medium text-emerald-800/80 dark:text-emerald-200/75">Total cotización</p>'
            .'<p class="mt-1 text-2xl font-bold tracking-tight text-emerald-950 dark:text-emerald-50">'.e(CoordinationServiceQuoteManager::formatManageQuoteAmountPreview($total)).'</p>'
            .'<p class="mt-1 text-sm font-medium text-emerald-800/70 dark:text-emerald-200/65">'.e(CoordinationServiceQuoteManager::formatManageQuoteAmountPreview($totalBs, 'VES')).'</p>'
            .'<p class="mt-2 text-[11px] leading-relaxed text-emerald-900/60 dark:text-emerald-100/55">Costo base + ganancia aplicada, convertido con la tasa BCV del día.</p>'
            .'</div>'
            .'</div>';

        return new HtmlString($html);
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

    public static function manageQuoteSubtotal(mixed $costoDolares): ?float
    {
        $costo = OperationCoordinationServicesTable::decimalOrNull($costoDolares);

        if ($costo === null) {
            return null;
        }

        return round($costo, 2);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function manageQuoteLineItemsState(Get $get): array
    {
        foreach (['manage_quote_line_items', '../../manage_quote_line_items', '../../../manage_quote_line_items'] as $path) {
            $value = $get($path);

            if (is_array($value) && $value !== []) {
                return $value;
            }
        }

        return [];
    }

    public static function manageQuoteFormFieldState(Get $get, string $field): mixed
    {
        foreach (['', '../../', '../../../'] as $prefix) {
            $value = $get($prefix.$field);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $get($field);
    }

    public static function manageQuoteAggregateSetPathPrefix(Get $get): string
    {
        $rateAtRoot = OperationCoordinationServicesTable::decimalOrNull($get('manage_quote_bcv_rate'));
        $rateAtFormSibling = OperationCoordinationServicesTable::decimalOrNull($get('../../manage_quote_bcv_rate'));

        if ($rateAtFormSibling !== null && $rateAtRoot === null) {
            return '../../';
        }

        return '';
    }

    public static function syncManageQuoteAggregates(Get $get, Set $set): void
    {
        $lineItems = self::manageQuoteLineItemsState($get);
        $subtotal = self::manageQuoteSubtotalFromLineItems($lineItems);
        $rate = OperationCoordinationServicesTable::decimalOrNull(self::manageQuoteFormFieldState($get, 'manage_quote_bcv_rate'));
        $prefix = self::manageQuoteAggregateSetPathPrefix($get);

        $set($prefix.'manage_quote_costo_dolares', $subtotal);
        $set(
            $prefix.'manage_quote_costo_bolivares',
            ($subtotal !== null && $rate !== null) ? round($subtotal * $rate, 2) : null
        );
    }

    public static function ensureManageQuoteLineItemsPopulated(
        OperationCoordinationService $record,
        Get $get,
        Set $set,
        mixed $state
    ): void {
        $existing = is_array($state) ? $state : [];
        $expected = self::buildManageQuoteLineItemsDefault(
            $record,
            $get('managed_service_item_keys'),
            $existing
        );

        if ($expected === []) {
            return;
        }

        $existingKeys = array_values(array_map(
            fn (array $row): string => (string) ($row['key'] ?? ''),
            $existing
        ));
        $expectedKeys = array_values(array_map(
            fn (array $row): string => (string) ($row['key'] ?? ''),
            $expected
        ));

        if ($existingKeys !== $expectedKeys) {
            $set('manage_quote_line_items', $expected);
            self::syncManageQuoteAggregates($get, $set);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     */
    public static function manageQuoteSubtotalFromLineItems(array $lineItems): ?float
    {
        if ($lineItems === []) {
            return null;
        }

        $sum = 0.0;
        $hasPrice = false;

        foreach ($lineItems as $lineItem) {
            $unitUsd = OperationCoordinationServicesTable::decimalOrNull($lineItem['unit_price_usd'] ?? null);

            if ($unitUsd === null) {
                continue;
            }

            $hasPrice = true;
            $sum += $unitUsd;
        }

        if (! $hasPrice) {
            return null;
        }

        return round($sum, 2);
    }

    /**
     * @param  array<int, string>  $selectedKeys
     * @param  array<int, array<string, mixed>>  $existingLineItems
     * @return array<int, array<string, mixed>>
     */
    public static function buildManageQuoteLineItemsDefault(
        OperationCoordinationService $record,
        mixed $selectedKeys,
        array $existingLineItems = []
    ): array {
        $nonCoveredKeys = self::nonCoveredSelectedManagementItemKeys($record, $selectedKeys);
        $existingByKey = collect($existingLineItems)
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['key'] ?? null))
            ->keyBy(fn (array $row): string => (string) $row['key']);

        return self::associatedServiceItemsForManagement($record)
            ->filter(fn (array $item): bool => in_array($item['key'], $nonCoveredKeys, true))
            ->map(function (array $item) use ($existingByKey): array {
                $existing = $existingByKey->get($item['key']);
                $unitUsd = OperationCoordinationServicesTable::decimalOrNull($existing['unit_price_usd'] ?? null);

                return [
                    'key' => $item['key'],
                    'category' => $item['category'],
                    'label' => $item['label'],
                    'detail' => $item['detail'],
                    'unit_price_usd' => $unitUsd,
                    'unit_price_ves' => OperationCoordinationServicesTable::decimalOrNull($existing['unit_price_ves'] ?? null),
                ];
            })
            ->values()
            ->all();
    }

    public static function manageQuoteTotal(mixed $subtotalUsd, mixed $porcentajeGanancia): ?float
    {
        $subtotal = OperationCoordinationServicesTable::decimalOrNull($subtotalUsd);

        if ($subtotal === null) {
            return null;
        }

        $porcentaje = OperationCoordinationServicesTable::decimalOrNull($porcentajeGanancia) ?? 0.0;

        return round($subtotal + ($subtotal * $porcentaje / 100), 2);
    }

    public static function managementCategoryBadgeClass(string $category): string
    {
        return match ($category) {
            'Medicamento' => 'fi-manage-service-badge fi-manage-service-badge--medicamento',
            'Laboratorio' => 'fi-manage-service-badge fi-manage-service-badge--laboratorio',
            'Estudio' => 'fi-manage-service-badge fi-manage-service-badge--estudio',
            'Especialista' => 'fi-manage-service-badge fi-manage-service-badge--especialista',
            default => 'fi-manage-service-badge fi-manage-service-badge--default',
        };
    }

    public static function managementCoverageBadgeClass(?bool $coverage): string
    {
        return match ($coverage) {
            true => 'fi-manage-service-badge fi-manage-service-badge--covered',
            false => 'fi-manage-service-badge fi-manage-service-badge--not-covered',
            default => 'fi-manage-service-badge fi-manage-service-badge--default',
        };
    }

    public static function managementStatusBadgeClass(string $status): string
    {
        $normalized = mb_strtoupper(trim($status));

        return match ($normalized) {
            'FINALIZADO' => 'fi-manage-service-badge fi-manage-service-badge--status-done',
            'PENDIENTE' => 'fi-manage-service-badge fi-manage-service-badge--status-pending',
            'EN GESTION' => 'fi-manage-service-badge fi-manage-service-badge--status-progress',
            default => 'fi-manage-service-badge fi-manage-service-badge--status-default',
        };
    }

    /**
     * @param  Collection<int, array{key: string, category: string, label: string, detail: string, coverage: bool|null, coverage_label: string, status: string, selectable: bool}>  $items
     */
    public static function renderManagementItemsTable(Collection $items, bool $includeStatus = true): HtmlString
    {
        if ($items->isEmpty()) {
            return new HtmlString(
                '<div class="fi-manage-service-items-table--empty">'
                .'No hay ítems para mostrar.'
                .'</div>'
            );
        }

        $rows = $items->map(function (array $item) use ($includeStatus): string {
            $categoryClass = self::managementCategoryBadgeClass($item['category']);
            $coverageClass = self::managementCoverageBadgeClass($item['coverage']);
            $statusClass = self::managementStatusBadgeClass($item['status']);

            $row = '<tr>'
                .'<td><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide ring-1 ring-inset '.$categoryClass.'">'.e($item['category']).'</span></td>'
                .'<td class="fi-manage-service-items-table-item">'.e($item['label']).'</td>'
                .'<td>'.e($item['detail']).'</td>'
                .'<td><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset '.$coverageClass.'">'.e($item['coverage_label']).'</span></td>';

            if ($includeStatus) {
                $row .= '<td><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset '.$statusClass.'">'.e($item['status']).'</span></td>';
            }

            return $row.'</tr>';
        })->implode('');

        $statusHeader = $includeStatus
            ? '<th>Estatus</th>'
            : '';

        return new HtmlString(
            '<div class="fi-manage-service-items-table">'
            .'<div class="overflow-x-auto">'
            .'<table><thead><tr>'
            .'<th>Tipo</th>'
            .'<th>Ítem</th>'
            .'<th>Descripción</th>'
            .'<th>Cobertura</th>'
            .$statusHeader
            .'</tr></thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'</table>'
            .'</div>'
            .'</div>'
        );
    }

    public static function manageServiceSelectableOptions(OperationCoordinationService $record): array
    {
        return self::associatedServiceItemsForManagement($record)
            ->filter(fn (array $item): bool => $item['selectable'])
            ->mapWithKeys(fn (array $item): array => [$item['key'] => $item['category'].': '.$item['label']])
            ->all();
    }

    public static function manageServiceSelectableDescriptions(OperationCoordinationService $record): array
    {
        return self::associatedServiceItemsForManagement($record)
            ->filter(fn (array $item): bool => $item['selectable'])
            ->mapWithKeys(fn (array $item): array => [
                $item['key'] => $item['category'].': '.$item['label'].' · '.$item['coverage_label'].' · '.$item['status'],
            ])
            ->all();
    }

    public static function associatedServiceItemsOverviewTable(OperationCoordinationService $record): HtmlString
    {
        return self::renderManagementItemsTable(self::associatedServiceItemsForManagement($record));
    }

    public static function manageServiceSelectedItemsTable(OperationCoordinationService $record, mixed $selectedKeys): HtmlString
    {
        $keys = is_array($selectedKeys) ? $selectedKeys : [];

        if ($keys === []) {
            return new HtmlString(
                '<div class="fi-manage-service-notice fi-manage-service-notice--sky px-5 py-6 text-center">'
                .'<p class="text-sm font-medium">Seleccione al menos un ítem para ver la vista previa de gestión.</p>'
                .'</div>'
            );
        }

        $items = self::associatedServiceItemsForManagement($record)
            ->filter(fn (array $item): bool => in_array($item['key'], $keys, true))
            ->values();

        if ($items->isEmpty()) {
            return new HtmlString(
                '<div class="fi-manage-service-notice fi-manage-service-notice--amber px-5 py-6 text-center">'
                .'Los ítems seleccionados no están disponibles para esta coordinación.'
                .'</div>'
            );
        }

        return self::renderManagementItemsTable($items, includeStatus: false);
    }

    public static function manageServiceCoveredItemsNotice(OperationCoordinationService $record, mixed $selectedKeys): HtmlString
    {
        $coveredKeys = self::coveredSelectedManagementItemKeys($record, $selectedKeys);
        $nonCoveredCount = collect(is_array($selectedKeys) ? $selectedKeys : [])
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->reject(fn (string $key): bool => in_array($key, $coveredKeys, true))
            ->count();

        $message = 'Se creará una orden de servicio únicamente para los ítems cubiertos seleccionados.';

        if ($nonCoveredCount > 0) {
            $message .= ' Los ítems no cubiertos seleccionados pasarán a gestión sin orden de servicio.';
        }

        return new HtmlString(
            '<div class="fi-manage-service-notice fi-manage-service-notice--emerald px-5">'
            .'<p class="font-semibold">Próximo paso: orden de servicio</p>'
            .'<p class="mt-1 leading-relaxed opacity-90">'.e($message).'</p>'
            .'</div>'
        );
    }

    public static function manageServiceOrderTypeBadge(?string $serviceOrderType): HtmlString
    {
        if ($serviceOrderType === null) {
            return new HtmlString(
                '<div class="fi-manage-service-notice fi-manage-service-notice--amber">'
                .'No se detectó un tipo de servicio homogéneo entre los ítems cubiertos.'
                .'</div>'
            );
        }

        return new HtmlString(
            '<div class="fi-manage-service-notice fi-manage-service-notice--primary">'
            .'<span class="inline-flex h-2 w-2 shrink-0 rounded-full bg-primary-500"></span>'
            .'Tipo de orden detectado: <span class="ml-1 uppercase tracking-wide">'.e($serviceOrderType).'</span>'
            .'</div>'
        );
    }

    public static function manageServiceCoveredItemsTable(OperationCoordinationService $record, mixed $selectedKeys): HtmlString
    {
        $coveredKeys = self::coveredSelectedManagementItemKeys($record, $selectedKeys);

        return self::manageServiceSelectedItemsTable($record, $coveredKeys);
    }

    public static function serviceOrderTypeFromManagementKey(string $key): ?string
    {
        if (! str_contains($key, ':')) {
            return null;
        }

        [$type] = explode(':', $key, 2);

        return match ($type) {
            'medication' => 'MEDICAMENTOS',
            'lab' => 'LABORATORIOS',
            'study' => 'IMAGENOLOGIA',
            'specialty' => 'ESPECIALISTA',
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function nonCoveredSelectedManagementItemKeys(OperationCoordinationService $record, mixed $selectedKeys): array
    {
        $keys = is_array($selectedKeys) ? $selectedKeys : [];

        return self::associatedServiceItemsForManagement($record)
            ->filter(fn (array $item): bool => in_array($item['key'], $keys, true) && $item['coverage'] === false)
            ->pluck('key')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function coveredSelectedManagementItemKeys(OperationCoordinationService $record, mixed $selectedKeys): array
    {
        $keys = is_array($selectedKeys) ? $selectedKeys : [];

        return self::associatedServiceItemsForManagement($record)
            ->filter(fn (array $item): bool => in_array($item['key'], $keys, true) && $item['coverage'] === true)
            ->pluck('key')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $keys
     */
    public static function resolveServiceOrderTypeFromManagementKeys(array $keys): ?string
    {
        $types = collect($keys)
            ->map(fn (string $key): ?string => self::serviceOrderTypeFromManagementKey($key))
            ->filter()
            ->unique()
            ->values();

        return $types->count() === 1 ? $types->first() : null;
    }

    public static function nextServiceOrderNumber(): string
    {
        return 'ORD-'.str_pad((string) (((int) (OperationServiceOrder::max('id') ?? 0)) + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>
     */
    public static function formDefaults(OperationCoordinationService $record): array
    {
        return [
            'managed_service_item_keys' => [],
            'order_number' => self::nextServiceOrderNumber(),
            'telemedicine_priority_id' => $record->telemedicine_priority_id,
            'doctor_nurse_id' => null,
            'supplier_id' => null,
            'supplier_external' => null,
            'register_unregistered_provider' => false,
            'unregistered_provider_type' => null,
            'unregistered_name' => null,
            'unregistered_rif' => null,
            'unregistered_phone' => null,
            'unregistered_correo_principal' => null,
            'unregistered_ubicacion_principal' => null,
            'operation_inventory_ubication_id' => null,
            'service_order_description' => null,
            'service_order_observations' => null,
            'manage_quote_bcv_rate' => OperationCoordinationServicesTable::referenciaTasaBcvDesdeApi(),
            'manage_quote_line_items' => [],
            'manage_quote_costo_dolares' => null,
            'manage_quote_costo_bolivares' => null,
            'manage_quote_porcentaje_ganancia' => null,
        ];
    }

    public static function shouldShowUnregisteredProviderWizardStep(
        OperationCoordinationService $record,
        Get $get
    ): bool {
        if (! (bool) $get('register_unregistered_provider')) {
            return false;
        }

        $coveredKeys = self::coveredSelectedManagementItemKeys($record, $get('managed_service_item_keys'));

        if ($coveredKeys === []) {
            return false;
        }

        return self::resolveServiceOrderTypeFromManagementKeys($coveredKeys) !== null;
    }

    public static function save(OperationCoordinationService $record, array $data): bool
    {
        $selectedKeys = array_values(array_filter(
            (array) ($data['managed_service_item_keys'] ?? []),
            fn (mixed $key): bool => is_string($key) && $key !== ''
        ));

        if ($selectedKeys === []) {
            Notification::make()
                ->title('Gestionar servicio')
                ->body('Seleccione al menos un ítem para gestionar.')
                ->warning()
                ->send();

            return false;
        }

        $coveredKeys = self::coveredSelectedManagementItemKeys($record, $selectedKeys);
        $nonCoveredKeys = self::nonCoveredSelectedManagementItemKeys($record, $selectedKeys);
        $serviceOrderType = self::resolveServiceOrderTypeFromManagementKeys($coveredKeys);
        $quoteType = self::resolveServiceOrderTypeFromManagementKeys($nonCoveredKeys);
        $shouldCreateServiceOrder = $coveredKeys !== [] && $serviceOrderType !== null;
        $shouldCreateQuote = $nonCoveredKeys !== [] && $quoteType !== null;

        if ($coveredKeys !== [] && $serviceOrderType === null) {
            Notification::make()
                ->title('Orden de servicio')
                ->body('Los ítems cubiertos seleccionados pertenecen a distintos tipos de servicio. Se gestionarán los ítems, pero no se creará la orden.')
                ->warning()
                ->send();
        }

        if ($nonCoveredKeys !== [] && $quoteType === null) {
            Notification::make()
                ->title('Cotización')
                ->body('Los ítems no cubiertos seleccionados pertenecen a distintos tipos de servicio. Se gestionarán los ítems, pero no se registrará la cotización.')
                ->warning()
                ->send();
        }

        if ($shouldCreateQuote) {
            $lineItems = (array) ($data['manage_quote_line_items'] ?? []);

            if ($lineItems === []) {
                $lineItems = self::buildManageQuoteLineItemsDefault($record, $nonCoveredKeys, []);
                $data['manage_quote_line_items'] = $lineItems;
            }

            $costoUsd = self::manageQuoteSubtotalFromLineItems($lineItems)
                ?? OperationCoordinationServicesTable::decimalOrNull($data['manage_quote_costo_dolares'] ?? null);

            if ($costoUsd === null || $costoUsd <= 0) {
                Notification::make()
                    ->title('Cotización')
                    ->body('Indique el precio unitario en dólares (mayor a cero) para cada ítem no cubierto seleccionado.')
                    ->warning()
                    ->send();

                return false;
            }

            $data['manage_quote_costo_dolares'] = $costoUsd;

            $bcvRate = OperationCoordinationServicesTable::decimalOrNull($data['manage_quote_bcv_rate'] ?? null);

            if ($bcvRate === null || $bcvRate <= 0) {
                Notification::make()
                    ->title('Cotización')
                    ->body('No fue posible obtener una tasa BCV válida. Intente nuevamente.')
                    ->warning()
                    ->send();

                return false;
            }
        }

        $managedCount = 0;
        $quoteItemsPayload = $shouldCreateQuote
            ? self::buildManageQuoteItemsPayload($record, $nonCoveredKeys, (array) ($data['manage_quote_line_items'] ?? []))
            : [];

        DB::transaction(function () use (
            $record,
            $selectedKeys,
            $data,
            $shouldCreateQuote,
            $quoteType,
            $quoteItemsPayload,
            &$managedCount
        ): void {
            foreach ($selectedKeys as $key) {
                if (! str_contains($key, ':')) {
                    continue;
                }

                [$type, $id] = explode(':', $key, 2);
                $id = (int) $id;

                if ($id <= 0) {
                    continue;
                }

                $updated = match ($type) {
                    'medication' => TelemedicinePatientMedications::query()
                        ->where('operation_coordination_service_id', $record->id)
                        ->whereKey($id)
                        ->where('status', '!=', 'EN GESTION')
                        ->update(['status' => 'EN GESTION']),
                    'lab' => TelemedicinePatientLab::query()
                        ->where('operation_coordination_service_id', $record->id)
                        ->whereKey($id)
                        ->where('status', '!=', 'EN GESTION')
                        ->update(['status' => 'EN GESTION']),
                    'study' => TelemedicinePatientStudy::query()
                        ->where('operation_coordination_service_id', $record->id)
                        ->whereKey($id)
                        ->where('status', '!=', 'EN GESTION')
                        ->update(['status' => 'EN GESTION']),
                    'specialty' => TelemedicinePatientSpecialty::query()
                        ->where('operation_coordination_service_id', $record->id)
                        ->whereKey($id)
                        ->where('status', '!=', 'EN GESTION')
                        ->update(['status' => 'EN GESTION']),
                    default => 0,
                };

                $managedCount += (int) $updated;
            }

            if ($managedCount > 0) {
                $record->status = 'EN GESTION';
                $record->updated_by = Auth::user()?->name;
                $record->save();
            }

            if ($shouldCreateQuote && $quoteItemsPayload !== []) {
                self::persistManageQuote($record, $data, $quoteType, $quoteItemsPayload);
            }
        });

        if ($managedCount === 0) {
            Notification::make()
                ->title('Gestionar servicio')
                ->body('No fue posible gestionar los ítems seleccionados. Verifique que sigan pendientes.')
                ->warning()
                ->send();

            return false;
        }

        $body = $managedCount === 1
            ? 'Se gestionó 1 ítem y la coordinación pasó a EN GESTION.'
            : 'Se gestionaron '.$managedCount.' ítems y la coordinación pasó a EN GESTION.';

        if ($shouldCreateServiceOrder) {
            $orderCreated = self::createServiceOrderFromManageModal($record, $data, $coveredKeys, $serviceOrderType);

            if ($orderCreated) {
                $body .= ' Se creó la orden de servicio para los ítems cubiertos seleccionados.';
            }
        }

        if ($shouldCreateQuote) {
            $body .= ' Se registró la cotización para los ítems no cubiertos seleccionados.';
        }

        Notification::make()
            ->title('Ítems gestionados')
            ->body($body)
            ->success()
            ->send();

        return true;
    }

    public static function existingServiceOrdersTable(OperationCoordinationService $record): HtmlString
    {
        $orders = OperationServiceOrder::query()
            ->where('operation_coordination_service_id', $record->id)
            ->latest('id')
            ->limit(5)
            ->get(['order_number', 'service_type', 'status', 'created_at']);

        if ($orders->isEmpty()) {
            return new HtmlString(
                '<div class="rounded-xl border border-gray-200/90 bg-gray-50/90 px-4 py-3 text-sm text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">'
                .'Aún no hay órdenes registradas para esta coordinación.'
                .'</div>'
            );
        }

        $rows = $orders->map(function (OperationServiceOrder $order): string {
            return '<tr class="border-b border-gray-100 last:border-0 dark:border-white/10">'
                .'<td class="px-3 py-2 font-medium text-gray-900 dark:text-white">'.e((string) $order->order_number).'</td>'
                .'<td class="px-3 py-2 text-gray-700 dark:text-gray-300">'.e((string) ($order->service_type ?? '—')).'</td>'
                .'<td class="px-3 py-2 text-gray-700 dark:text-gray-300">'.e((string) ($order->status ?? '—')).'</td>'
                .'<td class="px-3 py-2 text-gray-600 dark:text-gray-400">'.e(optional($order->created_at)->format('d/m/Y H:i') ?? '—').'</td>'
                .'</tr>';
        })->implode('');

        return new HtmlString(
            '<div class="overflow-x-auto rounded-xl border border-gray-200/90 dark:border-white/10">'
            .'<table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-white/10">'
            .'<thead class="bg-gray-50/90 dark:bg-white/5"><tr>'
            .'<th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Orden</th>'
            .'<th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipo</th>'
            .'<th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Estatus</th>'
            .'<th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Creada</th>'
            .'</tr></thead>'
            .'<tbody class="bg-white/80 dark:bg-zinc-900/50">'.$rows.'</tbody>'
            .'</table>'
            .'</div>'
        );
    }

    /**
     * @param  array<int, string>  $coveredKeys
     */
    public static function createServiceOrderFromManageModal(
        OperationCoordinationService $record,
        array $data,
        array $coveredKeys,
        string $serviceOrderType
    ): bool {
        $records = OperationCoordinationServicesTable::selectedServiceOrderRecordsByType(
            $record,
            OperationCoordinationServicesTable::managementKeysToNumericIds($coveredKeys),
            $serviceOrderType
        );

        if ($records->isEmpty()) {
            Notification::make()
                ->title('Orden de servicio')
                ->body('Los ítems cubiertos seleccionados no están disponibles para crear la orden.')
                ->warning()
                ->send();

            return false;
        }

        $payload = OperationCoordinationServicesTable::buildServiceOrderPayload($record, $data, $serviceOrderType);

        if ($payload === null) {
            return false;
        }

        if ($serviceOrderType === 'MEDICAMENTOS') {
            $payload['medications_list'] = $records->map(fn (TelemedicinePatientMedications $item): array => [
                'quantity' => 1,
                'indications' => $item->indications ?? null,
            ])->values()->all();
        }

        OperationServiceOrderController::create($payload, $record->toArray(), $records);

        $record->service_order_number = $data['order_number'] ?? $record->service_order_number;
        $record->updated_by = Auth::user()?->name;
        $record->save();

        return true;
    }

    /**
     * @param  array<int, string>  $nonCoveredKeys
     * @param  array<int, array<string, mixed>>  $lineItems
     * @return array<int, array<string, mixed>>
     */
    public static function buildManageQuoteItemsPayload(
        OperationCoordinationService $record,
        array $nonCoveredKeys,
        array $lineItems = []
    ): array {
        $lineItemsByKey = collect($lineItems)
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['key'] ?? null))
            ->keyBy(fn (array $row): string => (string) $row['key']);

        return self::associatedServiceItemsForManagement($record)
            ->filter(fn (array $item): bool => in_array($item['key'], $nonCoveredKeys, true))
            ->map(function (array $item) use ($lineItemsByKey): array {
                $line = $lineItemsByKey->get($item['key']);

                return [
                    'key' => $item['key'],
                    'category' => $item['category'],
                    'label' => $item['label'],
                    'detail' => $item['detail'],
                    'coverage_label' => $item['coverage_label'],
                    'status' => $item['status'],
                    'unit_price_usd' => OperationCoordinationServicesTable::decimalOrNull($line['unit_price_usd'] ?? null),
                    'unit_price_ves' => OperationCoordinationServicesTable::decimalOrNull($line['unit_price_ves'] ?? null),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public static function persistManageQuote(
        OperationCoordinationService $record,
        array $data,
        string $quoteType,
        array $items
    ): void {
        $lineItems = (array) ($data['manage_quote_line_items'] ?? []);
        $costoUsd = self::manageQuoteSubtotalFromLineItems($lineItems)
            ?? OperationCoordinationServicesTable::decimalOrNull($data['manage_quote_costo_dolares'] ?? null);
        $bcvRate = OperationCoordinationServicesTable::decimalOrNull($data['manage_quote_bcv_rate'] ?? null);

        if ($costoUsd === null || $costoUsd <= 0 || $bcvRate === null || $bcvRate <= 0 || $items === []) {
            throw new \RuntimeException('No fue posible registrar la cotización con los datos proporcionados.');
        }

        $porcentaje = OperationCoordinationServicesTable::decimalOrNull($data['manage_quote_porcentaje_ganancia'] ?? 0) ?? 0.0;
        $subtotal = self::manageQuoteSubtotal($costoUsd) ?? 0.0;
        $total = self::manageQuoteTotal($subtotal, $porcentaje) ?? 0.0;
        $costoBs = round($total * $bcvRate, 2);

        $quote = OperationQuoteGenerator::query()->create([
            'telemedicine_patient_id' => $record->telemedicine_patient_id,
            'telemedicine_case_id' => $record->telemedicine_case_id,
            'operation_coordination_service_id' => $record->id,
            'type_service' => $quoteType,
            'status' => OperationQuoteGenerator::STATUS_PENDING,
            'items' => $items,
            'costo_dolares' => $subtotal,
            'costo_bolivares' => $costoBs,
            'porcentaje_ganancia' => $porcentaje,
            'subtotal' => $subtotal,
            'total' => $total,
            'created_by' => Auth::user()?->name ?? 'system',
            'updated_by' => Auth::user()?->name,
        ]);

        $quote->update([
            'quote_pdf_path' => OperationQuoteGeneratorPdfService::store($quote, $record, $bcvRate),
        ]);

        $record->neto = $subtotal;
        $record->porcen_tdec = $porcentaje;
        $record->quote_price = $total;
        $record->updated_by = Auth::user()?->name;
        $record->save();
    }

    /**
     * @param  array<int, string>  $nonCoveredKeys
     */
    public static function createQuoteFromManageModal(
        OperationCoordinationService $record,
        array $data,
        array $nonCoveredKeys,
        string $quoteType
    ): bool {
        $items = self::buildManageQuoteItemsPayload(
            $record,
            $nonCoveredKeys,
            (array) ($data['manage_quote_line_items'] ?? [])
        );

        if ($items === []) {
            Notification::make()
                ->title('Cotización')
                ->body('Los ítems no cubiertos seleccionados no están disponibles para registrar la cotización.')
                ->warning()
                ->send();

            return false;
        }

        try {
            self::persistManageQuote($record, $data, $quoteType, $items);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }
}
