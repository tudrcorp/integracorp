<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceItems;
use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTable;
use App\Filament\Operations\Resources\OperationServiceOrders\OperationServiceOrderResource;
use App\Http\Controllers\OperationServiceOrderController;
use App\Models\OperationCoordinationService;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Models\Supplier;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use App\Services\OperationQuoteGeneratorPdfService;
use App\Support\Telemedicine\TelemedicineCaseTdgReassignmentCoordination;
use App\Support\Telemedicine\TelemedicineMedicationCoverage;
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
            if ($row instanceof TelemedicinePatientMedications) {
                return TelemedicineMedicationCoverage::isCovered($row);
            }

            if (isset($row->operationInventory) && $row->operationInventory !== null && $row->operationInventory->is_covered !== null) {
                return (bool) $row->operationInventory->is_covered;
            }

            if (! filled($row->operation_inventory_id ?? null)) {
                return false;
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

        return ! self::hasManageServiceSelectableItems($record);
    }

    public static function isManagementItemSelectable(string $status): bool
    {
        return ! in_array(mb_strtoupper(trim($status)), ['EN GESTION', 'FINALIZADO'], true);
    }

    public static function hasManageServiceItems(OperationCoordinationService $record): bool
    {
        return self::associatedServiceItemsForManagement($record)->isNotEmpty();
    }

    public static function hasManageServiceSelectableItems(OperationCoordinationService $record): bool
    {
        return self::associatedServiceItemsForManagement($record)
            ->contains(fn (array $item): bool => $item['selectable']);
    }

    public static function isManagementItemKeySelectable(OperationCoordinationService $record, string $key): bool
    {
        $item = self::associatedServiceItemsForManagement($record)->firstWhere('key', $key);

        return is_array($item) && $item['selectable'];
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
                    'selectable' => self::isManagementItemSelectable((string) ($item->status ?? '')),
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
                    'selectable' => self::isManagementItemSelectable((string) ($item->status ?? '')),
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
                    'selectable' => self::isManagementItemSelectable((string) ($item->status ?? '')),
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
                    'selectable' => self::isManagementItemSelectable((string) ($item->status ?? '')),
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
            'CANCELADA', 'CANCELADO' => 'fi-manage-service-badge fi-manage-service-badge--status-pending',
            'CADUCADA' => 'fi-manage-service-badge fi-manage-service-badge--status-default',
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

    public static function manageServiceItemOptions(OperationCoordinationService $record): array
    {
        return self::associatedServiceItemsForManagement($record)
            ->mapWithKeys(fn (array $item): array => [$item['key'] => $item['category'].': '.$item['label']])
            ->all();
    }

    public static function manageServiceItemDescriptions(OperationCoordinationService $record): array
    {
        return self::associatedServiceItemsForManagement($record)
            ->mapWithKeys(fn (array $item): array => [
                $item['key'] => $item['category'].': '.$item['label'].' · '.$item['coverage_label'].' · '.$item['status']
                    .($item['selectable'] ? '' : ' · No disponible para gestión'),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function manageServiceSelectableOptions(OperationCoordinationService $record): array
    {
        return self::associatedServiceItemsForManagement($record)
            ->filter(fn (array $item): bool => $item['selectable'])
            ->mapWithKeys(fn (array $item): array => [$item['key'] => $item['category'].': '.$item['label']])
            ->all();
    }

    /**
     * @return array<string, string>
     */
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

    public static function clinicalItemCategoryAbbrev(string $category): string
    {
        return match ($category) {
            'Medicamento' => 'MED',
            'Laboratorio' => 'LAB',
            'Estudio' => 'IMG',
            'Especialista' => 'ESP',
            default => 'SRV',
        };
    }

    public static function clinicalItemCoverageAbbrev(?bool $coverage): string
    {
        return match ($coverage) {
            true => 'CUB',
            false => 'NC',
            default => '—',
        };
    }

    public static function clinicalItemStatusAbbrev(string $status): string
    {
        return match (mb_strtoupper(trim($status))) {
            'PENDIENTE' => 'PEND',
            'EN GESTION' => 'GEST',
            'FINALIZADO' => 'FIN',
            'CANCELADA', 'CANCELADO' => 'CAN',
            'CADUCADA' => 'CAD',
            default => mb_strlen(trim($status)) > 6
                ? mb_strtoupper(mb_substr(trim($status), 0, 4))
                : mb_strtoupper(trim($status)),
        };
    }

    public static function clinicalItemStatusToneClass(string $status): string
    {
        return match (mb_strtoupper(trim($status))) {
            'PENDIENTE' => 'text-rose-700 dark:text-rose-300',
            'EN GESTION' => 'text-amber-700 dark:text-amber-300',
            'FINALIZADO' => 'text-emerald-700 dark:text-emerald-300',
            'CANCELADA', 'CANCELADO' => 'text-rose-700 dark:text-rose-300',
            'CADUCADA' => 'text-gray-600 dark:text-gray-400',
            default => 'text-gray-600 dark:text-gray-300',
        };
    }

    public static function clinicalCategoryToServiceType(string $category): string
    {
        return match ($category) {
            'Medicamento' => 'MEDICAMENTOS',
            'Laboratorio' => 'LABORATORIOS',
            'Estudio' => 'IMAGENOLOGIA',
            'Especialista' => 'ESPECIALISTA',
            default => 'OTRO',
        };
    }

    public static function clinicalItemServiceOrderKey(string $category, string $label): string
    {
        return self::clinicalCategoryToServiceType($category).'|'.mb_strtoupper(trim($label));
    }

    /**
     * @param  Collection<int, array{status: string}>  $items
     */
    public static function clinicalItemsCollectionAllClosed(Collection $items): bool
    {
        if ($items->isEmpty()) {
            return false;
        }

        $closedStatuses = array_map(
            static fn (string $status): string => mb_strtoupper(trim($status)),
            self::closedItemStatuses()
        );

        return $items->every(
            fn (array $item): bool => in_array(mb_strtoupper(trim($item['status'])), $closedStatuses, true)
        );
    }

    /**
     * @return Collection<int, array{key: string, category: string, label: string, detail: string, coverage: bool|null, coverage_label: string, status: string, selectable: bool}>
     */
    public static function clinicalItemsWithEffectiveDisplayStatus(OperationCoordinationService $record): Collection
    {
        $orderLinks = self::serviceOrderLinksByClinicalItemKey($record);

        return self::associatedServiceItemsForManagement($record)
            ->map(function (array $item) use ($orderLinks): array {
                $orderKey = self::clinicalItemServiceOrderKey($item['category'], (string) $item['label']);
                $orderLink = $orderLinks[$orderKey] ?? null;
                $item['status'] = self::effectiveClinicalItemDisplayStatus($item, is_array($orderLink) ? $orderLink : null);

                return $item;
            });
    }

    /**
     * @param  Collection<int, array{status: string}>  $items
     * @return array{PENDIENTE: int, EN GESTION: int, CANCELADA: int, FINALIZADO: int, CADUCADA: int}
     */
    public static function clinicalItemsStatusCounts(Collection $items): array
    {
        $counts = [
            'PENDIENTE' => 0,
            'EN GESTION' => 0,
            'CANCELADA' => 0,
            'FINALIZADO' => 0,
            'CADUCADA' => 0,
        ];

        foreach ($items as $item) {
            $status = mb_strtoupper(trim((string) ($item['status'] ?? '')));
            $normalized = match ($status) {
                'CANCELADO' => 'CANCELADA',
                default => $status,
            };

            if (array_key_exists($normalized, $counts)) {
                $counts[$normalized]++;
            }
        }

        return $counts;
    }

    public static function clinicalItemStatusCounterLabel(string $status, int $count): string
    {
        return match ($status) {
            'PENDIENTE' => $count === 1 ? '1 PENDIENTE' : $count.' PENDIENTES',
            'EN GESTION' => $count === 1 ? '1 EN GESTIÓN' : $count.' EN GESTIÓN',
            'CANCELADA' => $count === 1 ? '1 CANCELADO' : $count.' CANCELADOS',
            'FINALIZADO' => $count === 1 ? '1 FINALIZADO' : $count.' FINALIZADOS',
            'CADUCADA' => $count === 1 ? '1 CADUCADA' : $count.' CADUCADAS',
            default => $count.' '.$status,
        };
    }

    /**
     * @return array{bg: string, shadow: string}
     */
    public static function clinicalItemStatusCounterPillStyle(string $status): array
    {
        return match ($status) {
            'EN GESTION' => [
                'bg' => '#ffc107',
                'shadow' => '0 4px 12px rgba(255, 193, 7, 0.35)',
            ],
            'CANCELADA' => [
                'bg' => '#ff3b30',
                'shadow' => '0 4px 12px rgba(255, 59, 48, 0.35)',
            ],
            'FINALIZADO' => [
                'bg' => '#28cd41',
                'shadow' => '0 4px 12px rgba(40, 205, 65, 0.35)',
            ],
            'PENDIENTE' => [
                'bg' => '#ffcc00',
                'shadow' => '0 4px 12px rgba(255, 204, 0, 0.35)',
            ],
            'CADUCADA' => [
                'bg' => '#8e8e93',
                'shadow' => '0 4px 12px rgba(142, 142, 147, 0.35)',
            ],
            default => [
                'bg' => '#8e8e93',
                'shadow' => '0 4px 12px rgba(142, 142, 147, 0.35)',
            ],
        };
    }

    /**
     * @param  Collection<int, array{status: string}>  $items
     */
    public static function renderClinicalItemsStatusCounterPills(Collection $items): string
    {
        if ($items->isEmpty()) {
            return '';
        }

        $counts = self::clinicalItemsStatusCounts($items);
        $pills = [];

        foreach (['PENDIENTE', 'EN GESTION', 'CANCELADA', 'FINALIZADO', 'CADUCADA'] as $status) {
            $count = $counts[$status] ?? 0;

            if ($count === 0) {
                continue;
            }

            $style = self::clinicalItemStatusCounterPillStyle($status);
            $label = self::clinicalItemStatusCounterLabel($status, $count);

            $pills[] = '<span style="background:linear-gradient(180deg,'.$style['bg'].' 0%,'.$style['bg'].' 100%);color:#ffffff;padding:8px 16px;border-radius:9999px;font-size:.8rem;font-weight:800;letter-spacing:.02em;display:inline-flex;align-items:center;gap:6px;box-shadow:'.$style['shadow'].',inset 0 1px 0 rgba(255,255,255,.25);border:1px solid rgba(255,255,255,.24);">'
                .'<span style="font-size:10px;opacity:.95;">●</span> '.e($label)
                .'</span>';
        }

        if ($pills === []) {
            return '';
        }

        return '<div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">'.implode('', $pills).'</div>';
    }

    /**
     * @param  Collection<int, array{status: string}>  $items
     */
    public static function clinicalItemsCompactHeaderSummary(Collection $items): ?string
    {
        if ($items->isEmpty() || self::clinicalItemsCollectionAllClosed($items)) {
            return null;
        }

        $counts = self::clinicalItemsStatusCounts($items);
        $parts = [];

        if ($counts['PENDIENTE'] > 0) {
            $pendingLabel = $counts['PENDIENTE'] === 1 ? 'PENDIENTE' : 'PENDIENTES';
            $parts[] = '<span class="font-semibold text-rose-700 dark:text-rose-300">'.$counts['PENDIENTE'].' '.$pendingLabel.'</span>';
        }

        if ($counts['EN GESTION'] > 0) {
            $parts[] = '<span class="font-semibold text-amber-700 dark:text-amber-300">'.$counts['EN GESTION'].' EN GESTIÓN</span>';
        }

        if ($counts['CANCELADA'] > 0) {
            $cancelLabel = $counts['CANCELADA'] === 1 ? 'CANCELADO' : 'CANCELADOS';
            $parts[] = '<span class="font-semibold text-red-700 dark:text-red-300">'.$counts['CANCELADA'].' '.$cancelLabel.'</span>';
        }

        if ($counts['FINALIZADO'] > 0) {
            $finalizedLabel = $counts['FINALIZADO'] === 1 ? 'FINALIZADO' : 'FINALIZADOS';
            $parts[] = '<span class="font-semibold text-emerald-700 dark:text-emerald-300">'.$counts['FINALIZADO'].' '.$finalizedLabel.'</span>';
        }

        if ($counts['CADUCADA'] > 0) {
            $expiredLabel = $counts['CADUCADA'] === 1 ? 'CADUCADA' : 'CADUCADAS';
            $parts[] = '<span class="font-semibold text-gray-600 dark:text-gray-300">'.$counts['CADUCADA'].' '.$expiredLabel.'</span>';
        }

        if ($parts === []) {
            return null;
        }

        return implode(' <span class="text-gray-400 dark:text-gray-500">·</span> ', $parts);
    }

    /**
     * @param  array{id?: int, order_number?: string, status?: string, url?: string}|null  $orderLink
     */
    public static function effectiveClinicalItemDisplayStatus(array $item, ?array $orderLink): string
    {
        $itemStatus = mb_strtoupper(trim((string) ($item['status'] ?? '')));
        $orderStatus = is_array($orderLink)
            ? mb_strtoupper(trim((string) ($orderLink['status'] ?? '')))
            : '';

        if ($itemStatus !== 'EN GESTION' || $orderStatus === '') {
            return $itemStatus;
        }

        return match ($orderStatus) {
            'CANCELADA', 'CANCELADO' => 'CANCELADA',
            'CADUCADA' => 'CADUCADA',
            'FINALIZADO' => 'FINALIZADO',
            default => $itemStatus,
        };
    }

    /**
     * @param  array<string, array{id: int, order_number: string, status: string, url: string}>|null  $orderLinks
     */
    public static function effectiveDisplayStatusForClinicalItem(
        OperationCoordinationService $record,
        string $category,
        string $label,
        string $rawStatus,
        ?array $orderLinks = null,
    ): string {
        $orderLinks ??= self::serviceOrderLinksByClinicalItemKey($record);
        $orderKey = self::clinicalItemServiceOrderKey($category, $label);
        $orderLink = $orderLinks[$orderKey] ?? null;

        return self::effectiveClinicalItemDisplayStatus(
            ['status' => $rawStatus],
            is_array($orderLink) ? $orderLink : null,
        );
    }

    /**
     * @return array<string, array{id: int, order_number: string, status: string, url: string}>
     */
    public static function serviceOrderLinksByClinicalItemKey(OperationCoordinationService $record): array
    {
        $map = [];

        OperationServiceOrder::query()
            ->where('operation_coordination_service_id', $record->id)
            ->with(['operationServiceOrderItems:id,operation_service_order_id,item_name,category'])
            ->orderByDesc('id')
            ->get(['id', 'order_number', 'status'])
            ->each(function (OperationServiceOrder $order) use (&$map): void {
                foreach ($order->operationServiceOrderItems as $orderItem) {
                    $serviceType = mb_strtoupper(trim((string) ($orderItem->category ?? '')));
                    $name = mb_strtoupper(trim((string) ($orderItem->item_name ?? '')));

                    if ($name === '' || $serviceType === '') {
                        continue;
                    }

                    $key = $serviceType.'|'.$name;

                    if (isset($map[$key])) {
                        continue;
                    }

                    $map[$key] = [
                        'id' => (int) $order->id,
                        'order_number' => (string) ($order->order_number ?? ''),
                        'status' => mb_strtoupper(trim((string) ($order->status ?? ''))),
                        'url' => OperationServiceOrderResource::getUrl('view', ['record' => $order->id]),
                    ];
                }
            });

        return $map;
    }

    /**
     * @return list<string>
     */
    public static function closedItemStatuses(): array
    {
        return [
            'FINALIZADO',
            'CANCELADO',
            'CANCELADA',
            'CADUCADA',
        ];
    }

    public static function coordinationClosedRowClasses(): string
    {
        return 'border-l-4 border-gray-400 bg-gray-100/90 dark:border-gray-500 dark:bg-gray-900/50';
    }

    public static function allAssociatedItemsAreClosed(OperationCoordinationService $record): bool
    {
        return self::clinicalItemsCollectionAllClosed(self::clinicalItemsWithEffectiveDisplayStatus($record));
    }

    /**
     * @param  array{key?: string, selectable?: bool}  $item
     */
    public static function clinicalItemPendingManageLinkHtml(
        string $displayStatus,
        array $item,
        string $manageServiceUrl,
        bool $canShowManageLink,
    ): string {
        if (! $canShowManageLink || mb_strtoupper(trim($displayStatus)) !== 'PENDIENTE' || ! ($item['selectable'] ?? false)) {
            return '';
        }

        return '<a href="'.e($manageServiceUrl).'" '
            .'class="fi-coordination-clinical-item-manage-link" '
            .'title="Gestionar servicio" '
            .'aria-label="Gestionar servicio" '
            .'onclick="event.stopPropagation();">'
            .'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5" aria-hidden="true">'
            .'<path fill-rule="evenodd" d="M4.5 2A1.5 1.5 0 0 0 3 3.5v13A1.5 1.5 0 0 0 4.5 18h11a1.5 1.5 0 0 0 1.5-1.5V7.621a1.5 1.5 0 0 0-.44-1.06l-4.5-4.5A1.5 1.5 0 0 0 11.378 2H4.5Zm5.75 4.5a.75.75 0 0 0-1.5 0v3.59L7.47 9.28a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.06 0l2.25-2.25a.75.75 0 0 0-1.06-1.06l-1.28 1.28V6.5Z" clip-rule="evenodd" />'
            .'</svg>'
            .'</a>';
    }

    public static function renderCoordinationClinicalItemsCompactList(OperationCoordinationService $record): HtmlString
    {
        $itemsForDisplay = self::clinicalItemsWithEffectiveDisplayStatus($record)
            ->sortBy(fn (array $item): int => match (mb_strtoupper(trim($item['status']))) {
                'PENDIENTE' => 0,
                'EN GESTION' => 1,
                'FINALIZADO' => 3,
                default => 2,
            })
            ->values();

        if ($itemsForDisplay->isEmpty()) {
            return new HtmlString(
                '<span class="text-[10px] text-gray-500 dark:text-gray-400">Sin ítems</span>'
            );
        }

        $orderLinks = self::serviceOrderLinksByClinicalItemKey($record);
        $quoteLinks = CoordinationServiceQuoteManager::quoteLinksByClinicalItemKey($record);
        $headerSummary = self::clinicalItemsCompactHeaderSummary($itemsForDisplay);

        $manageServiceUrl = ManageCoordinationServiceItems::getUrl(['record' => $record]);
        $canShowManageLink = ! self::manageServiceActionIsDisabled($record)
            && ! in_array('ATENMEDI', Auth::user()?->departament ?? [], true);

        $rows = $itemsForDisplay->map(function (array $item) use ($orderLinks, $quoteLinks, $manageServiceUrl, $canShowManageLink): string {
            $categoryAbbrev = self::clinicalItemCategoryAbbrev($item['category']);
            $categoryClass = self::managementCategoryBadgeClass($item['category']);
            $coverageAbbrev = self::clinicalItemCoverageAbbrev($item['coverage']);
            $coverageClass = self::managementCoverageBadgeClass($item['coverage']);
            $displayStatus = (string) $item['status'];
            $statusAbbrev = self::clinicalItemStatusAbbrev($displayStatus);
            $statusClass = self::managementStatusBadgeClass($displayStatus);
            $label = (string) $item['label'];
            $itemToneClass = match (mb_strtoupper(trim($displayStatus))) {
                'PENDIENTE' => 'fi-coordination-clinical-item--pending',
                'EN GESTION' => 'fi-coordination-clinical-item--progress',
                'FINALIZADO' => 'fi-coordination-clinical-item--done',
                'CANCELADA', 'CANCELADO', 'CADUCADA' => 'fi-coordination-clinical-item--closed',
                default => '',
            };
            $orderKey = self::clinicalItemServiceOrderKey($item['category'], $label);
            $orderLink = $orderLinks[$orderKey] ?? null;
            $orderNumber = is_array($orderLink) ? trim((string) ($orderLink['order_number'] ?? '')) : '';

            $referenceHtml = '';
            if ($orderNumber !== '' && is_array($orderLink)) {
                $referenceHtml = '<a href="'.e((string) $orderLink['url']).'" '
                    .'class="fi-coordination-clinical-item-order-link" '
                    .'title="Ver orden '.e($orderNumber).'" '
                    .'onclick="event.stopPropagation();">'
                    .e($orderNumber)
                    .'</a>';
            } elseif ($item['coverage'] === false) {
                $quoteLink = $quoteLinks[(string) ($item['key'] ?? '')] ?? null;
                $quoteNumber = is_array($quoteLink) ? trim((string) ($quoteLink['quote_number'] ?? '')) : '';

                if ($quoteNumber !== '' && is_array($quoteLink)) {
                    $referenceHtml = '<a href="'.e((string) $quoteLink['url']).'" '
                        .'class="fi-coordination-clinical-item-quote-link" '
                        .'title="Aprobar cotización '.e($quoteNumber).'" '
                        .'onclick="event.stopPropagation();">'
                        .e($quoteNumber)
                        .'</a>';
                }
            }

            $manageLinkHtml = self::clinicalItemPendingManageLinkHtml(
                $displayStatus,
                $item,
                $manageServiceUrl,
                $canShowManageLink,
            );

            $tooltip = $label.' · '.$item['coverage_label'].' · '.$displayStatus;
            if ($orderNumber !== '') {
                $tooltip .= ' · '.$orderNumber;
            } elseif ($item['coverage'] === false) {
                $quoteLink = $quoteLinks[(string) ($item['key'] ?? '')] ?? null;
                $quoteNumber = is_array($quoteLink) ? trim((string) ($quoteLink['quote_number'] ?? '')) : '';
                if ($quoteNumber !== '') {
                    $tooltip .= ' · '.$quoteNumber;
                }
            }

            return '<li class="fi-coordination-clinical-item '.$itemToneClass.'" title="'.e($tooltip).'">'
                .'<span class="fi-coordination-clinical-item__lead">'
                .'<span class="fi-coordination-clinical-item__category">'
                .'<span class="'.$categoryClass.'">'.e($categoryAbbrev).'</span>'
                .'</span>'
                .'<span class="fi-coordination-clinical-item__label">'.e($label).'</span>'
                .'</span>'
                .'<span class="fi-coordination-clinical-item__trail">'
                .'<span class="fi-coordination-clinical-item__meta">'
                .'<span class="'.$coverageClass.'" title="'.e((string) $item['coverage_label']).'">'.e($coverageAbbrev).'</span>'
                .'<span class="'.$statusClass.'" title="'.e($displayStatus).'">'.e($statusAbbrev).'</span>'
                .'</span>'
                .($manageLinkHtml !== '' ? '<span class="fi-coordination-clinical-item__manage">'.$manageLinkHtml.'</span>' : '')
                .($referenceHtml !== '' ? '<span class="fi-coordination-clinical-item__order">'.$referenceHtml.'</span>' : '')
                .'</span>'
                .'</li>';
        })->implode('');

        $headerHtml = $headerSummary !== null
            ? '<p class="fi-coordination-clinical-items-compact__header">'.$headerSummary.'</p>'
            : '';

        return new HtmlString(
            '<div class="fi-coordination-clinical-items-compact">'
            .$headerHtml
            .'<ul class="fi-coordination-clinical-items-list">'.$rows.'</ul>'
            .'</div>'
        );
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
            ->filter(fn (array $item): bool => in_array($item['key'], $keys, true) && $item['selectable'] && $item['coverage'] === false)
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
            ->filter(fn (array $item): bool => in_array($item['key'], $keys, true) && $item['selectable'] && $item['coverage'] === true)
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

    public static function resolveManageQuoteSupplierAddress(mixed $supplierId): ?string
    {
        if (! filled($supplierId)) {
            return null;
        }

        $address = Supplier::query()
            ->whereKey((int) $supplierId)
            ->value('ubicacion_principal');

        return filled($address) ? (string) $address : null;
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
            'manage_quote_supplier_id' => null,
            'manage_quote_supplier_address' => null,
            'manage_quote_observations' => null,
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
            if (! filled($data['manage_quote_supplier_id'] ?? null)) {
                Notification::make()
                    ->title('Cotización')
                    ->body('Seleccione el proveedor en los parámetros de cotización.')
                    ->warning()
                    ->send();

                return false;
            }

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
        $supplierId = (int) ($data['manage_quote_supplier_id'] ?? 0);
        $supplierAddress = filled($data['manage_quote_supplier_address'] ?? null)
            ? (string) $data['manage_quote_supplier_address']
            : self::resolveManageQuoteSupplierAddress($supplierId);

        $quote = OperationQuoteGenerator::query()->create([
            'telemedicine_patient_id' => $record->telemedicine_patient_id,
            'telemedicine_case_id' => $record->telemedicine_case_id,
            'operation_coordination_service_id' => $record->id,
            'type_service' => $quoteType,
            'supplier_id' => $supplierId > 0 ? $supplierId : null,
            'supplier_address' => $supplierAddress,
            'observations' => filled($data['manage_quote_observations'] ?? null)
                ? trim((string) $data['manage_quote_observations'])
                : null,
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

        AccountsReceivableManager::syncFromQuote($quote->fresh() ?? $quote);

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
