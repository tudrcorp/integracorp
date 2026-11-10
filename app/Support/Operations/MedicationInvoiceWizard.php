<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationCoordinationService;
use App\Models\OperationInventoryProduct;
use App\Support\BcvOfficialRate;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

/**
 * Estado y reglas del bloque «Facturar al paciente» del paso Cotización de «Gestionar servicio».
 *
 * Cada ítem NO CUBIERTO seleccionado tiene una fila con dos caminos excluyentes:
 * - Facturar al paciente (por defecto):
 *   - medicamentos: uno o varios productos del inventario (descuenta stock y finaliza);
 *   - laboratorios, estudios y especialistas: la fila de su catálogo, con su costo y % (queda
 *     EN GESTION y se crea la orden al proveedor que lo realiza).
 * - «Generar cotización»: la cotización de siempre. En medicamentos, además, queda en Fallas.
 *
 * Una gestión factura ítems de un solo tipo, como la cotización. Si el usuario no tiene el
 * permiso de facturar (o es usuario de un proveedor), el paso funciona exactamente como antes.
 */
final class MedicationInvoiceWizard
{
    public const ROWS_FIELD = 'manage_medication_resolutions';

    public static function userCanInvoice(): bool
    {
        $user = Auth::user();

        if ($user === null || filled($user->supplier_id ?? null)) {
            return false;
        }

        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::INVOICE_MEDICATIONS_FROM_INVENTORY);
    }

    /**
     * La existencia del inventario es información interna: no se muestra al usuario de un proveedor.
     */
    public static function userCanSeeInventoryAvailability(): bool
    {
        $user = Auth::user();

        return $user !== null && ! filled($user->supplier_id ?? null);
    }

    /**
     * Chip con lo que el inventario tiene para el medicamento recetado, para que el analista
     * lo sepa antes de seleccionarlo.
     */
    public static function inventoryAvailabilityBadge(string $medicineName): string
    {
        $summary = MedicationInventoryCatalog::searchSummary($medicineName);
        $chip = 'ms-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset';

        if ($summary['matches'] === 0) {
            return '<span class="'.$chip.' bg-rose-500/10 text-rose-700 ring-rose-600/25 dark:text-rose-300 dark:ring-rose-400/30"'
                .' title="La búsqueda por nombre no encontró ningún producto en la categoría Medicamentos.">'
                .'No está en el inventario</span>';
        }

        $matches = $summary['matches'] === 1 ? '1 coincidencia' : $summary['matches'].' coincidencias';

        if ($summary['sellable'] === 0) {
            return '<span class="'.$chip.' bg-amber-500/10 text-amber-700 ring-amber-600/25 dark:text-amber-300 dark:ring-amber-400/30"'
                .' title="Hay productos con ese nombre, pero ninguno tiene existencia en un almacén activo.">'
                .'En inventario: '.e($matches).' · sin existencia</span>';
        }

        $withStock = $summary['sellable'] === 1 ? '1 con existencia' : $summary['sellable'].' con existencia';
        $withoutCost = $summary['without_cost'] > 0 ? ' · '.$summary['without_cost'].' sin costo' : '';

        return '<span class="'.$chip.' bg-emerald-500/10 text-emerald-700 ring-emerald-600/20 dark:text-emerald-300 dark:ring-emerald-400/30"'
            .' title="Coincidencias por nombre en la categoría Medicamentos. Los que no tienen costo se pueden facturar cargándoles el costo al elegirlos.">'
            .'En inventario: '.e($matches).' · '.e($withStock).' ('.number_format($summary['units'], 0, ',', '.').' und.)'.e($withoutCost).'</span>';
    }

    /**
     * Chip del primer paso para un laboratorio, estudio o especialista no cubierto: si su fila
     * del catálogo tiene precio al paciente o falta cargarlo.
     */
    public static function catalogPriceBadge(string $kind, string $name): string
    {
        $chip = 'ms-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset';
        $entry = ClinicalServiceCatalog::find($kind, ClinicalServiceCatalog::matchId($kind, $name));

        if ($entry === null) {
            return '<span class="'.$chip.' bg-rose-500/10 text-rose-700 ring-rose-600/25 dark:text-rose-300 dark:ring-rose-400/30"'
                .' title="No hay una fila del catálogo con ese nombre; al facturar puede elegir otra.">No está en el catálogo</span>';
        }

        $price = ClinicalServiceCatalog::price($entry);

        if ($price === null) {
            return '<span class="'.$chip.' bg-amber-500/10 text-amber-700 ring-amber-600/25 dark:text-amber-300 dark:ring-amber-400/30"'
                .' title="Al facturar podrá cargar el costo y el % de utilidad; quedan guardados en el catálogo.">Catálogo sin precio</span>';
        }

        return '<span class="'.$chip.' bg-emerald-500/10 text-emerald-700 ring-emerald-600/20 dark:text-emerald-300 dark:ring-emerald-400/30"'
            .' title="Precio al paciente del catálogo: costo + % de utilidad.">Precio al paciente: US$ '.e(number_format($price, 2, '.', ',')).'</span>';
    }

    public const KIND_MEDICATION = 'medication';

    /**
     * Paso «Medicamentos desde inventario» / «Facturar al paciente» de «Gestionar servicio».
     *
     * Retirado el 10/11/2026 por decisión de Gustavo: lo NO CUBIERTO siempre va a cotización y la
     * factura nace al aprobarla ({@see QuoteInvoiceIssuer}). Cuando el medicamento está en el
     * almacén, el médico lo elige desde Telemedicina. El código queda para las facturas ya emitidas
     * (anular, pagar, PDF) y para poder reactivarlo sin reconstruirlo; sus tests lo encienden.
     */
    public static bool $directInvoicingEnabled = false;

    /**
     * Tipo de orden de cada clase de ítem que se puede facturar.
     *
     * @var array<string, string>
     */
    private const ORDER_TYPES = [
        self::KIND_MEDICATION => 'MEDICAMENTOS',
        ClinicalServiceCatalog::KIND_LAB => 'LABORATORIOS',
        ClinicalServiceCatalog::KIND_STUDY => 'IMAGENOLOGIA',
        ClinicalServiceCatalog::KIND_SPECIALTY => 'ESPECIALISTA',
    ];

    /**
     * Clase de ítem que se factura en esta gestión (medication, lab, study, specialty) o null si
     * no aplica: sin permiso, tipos mezclados o servicio TPA/RETAIL (su orden usa otro tipo).
     */
    public static function kind(OperationCoordinationService $record, mixed $selectedKeys): ?string
    {
        if (! self::$directInvoicingEnabled) {
            return null;
        }

        $nonCovered = CoordinationServiceItemsManager::nonCoveredSelectedManagementItemKeys($record, $selectedKeys);

        if ($nonCovered === [] || ! self::userCanInvoice()) {
            return null;
        }

        $type = CoordinationServiceItemsManager::resolveServiceOrderTypeFromManagementKeys($nonCovered);
        $kind = array_search($type, self::ORDER_TYPES, true);

        if ($kind === false || CoordinationServiceItemsManager::resolveManagementTypeForCoordination($record, $type) !== $type) {
            return null;
        }

        return (string) $kind;
    }

    public static function isServiceKind(?string $kind): bool
    {
        return ClinicalServiceCatalog::isServiceKind($kind);
    }

    /**
     * Ítems NO CUBIERTOS seleccionados de la clase que se factura.
     *
     * @return list<string>
     */
    public static function invoiceableKeys(OperationCoordinationService $record, mixed $selectedKeys): array
    {
        $kind = self::kind($record, $selectedKeys);

        if ($kind === null) {
            return [];
        }

        return array_values(array_filter(
            CoordinationServiceItemsManager::nonCoveredSelectedManagementItemKeys($record, $selectedKeys),
            fn (string $key): bool => ClinicalServiceCatalog::kindFromKey($key) === $kind,
        ));
    }

    /**
     * Servicios (no medicamentos) que se facturan: necesitan su orden al proveedor.
     *
     * @return list<string>
     */
    public static function invoicedServiceKeys(OperationCoordinationService $record, mixed $selectedKeys, mixed $rows): array
    {
        return self::isServiceKind(self::kind($record, $selectedKeys)) ? self::invoiceKeys($record, $selectedKeys, $rows) : [];
    }

    public static function sectionTitle(?string $kind): string
    {
        return $kind === self::KIND_MEDICATION || ! self::isServiceKind($kind)
            ? 'Medicamentos desde inventario'
            : 'Facturar al paciente: '.mb_strtolower(ClinicalServiceCatalog::definition((string) $kind)['plural']);
    }

    public static function medicationIdFromKey(string $key): int
    {
        if (! str_starts_with($key, 'medication:')) {
            return 0;
        }

        return max(0, (int) substr($key, strlen('medication:')));
    }

    /**
     * Medicamentos NO CUBIERTOS seleccionados.
     *
     * @return list<string>
     */
    public static function medicationKeys(OperationCoordinationService $record, mixed $selectedKeys): array
    {
        return self::kind($record, $selectedKeys) === self::KIND_MEDICATION ? self::invoiceableKeys($record, $selectedKeys) : [];
    }

    /**
     * El bloque aplica si todo lo no cubierto seleccionado es de un mismo tipo facturable
     * (una sola cotización por tipo, igual que hoy) y el usuario puede facturar.
     */
    public static function applies(OperationCoordinationService $record, mixed $selectedKeys): bool
    {
        return self::kind($record, $selectedKeys) !== null;
    }

    /**
     * Medicamentos que se facturan desde inventario y por tanto NO van a la cotización.
     *
     * @return list<string>
     */
    public static function invoiceKeys(OperationCoordinationService $record, mixed $selectedKeys, mixed $rows): array
    {
        if (! self::applies($record, $selectedKeys)) {
            return [];
        }

        $quoteKeys = self::quoteKeys($record, $selectedKeys, $rows);

        return array_values(array_diff(self::invoiceableKeys($record, $selectedKeys), $quoteKeys));
    }

    /**
     * Medicamentos marcados con «Generar cotización».
     *
     * @return list<string>
     */
    public static function quoteKeys(OperationCoordinationService $record, mixed $selectedKeys, mixed $rows): array
    {
        if (! self::applies($record, $selectedKeys)) {
            return [];
        }

        $medicationKeys = self::invoiceableKeys($record, $selectedKeys);

        return array_values(array_filter(
            array_map(fn (array $row): string => (string) ($row['key'] ?? ''), self::rowsList($rows)),
            fn (string $key): bool => in_array($key, $medicationKeys, true)
                && (bool) (self::rowByKey($rows, $key)['generate_quote'] ?? false),
        ));
    }

    /**
     * Filas del bloque para la selección actual, conservando lo ya cargado por clave.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function buildRows(OperationCoordinationService $record, mixed $selectedKeys, mixed $existingRows): array
    {
        if (! self::applies($record, $selectedKeys)) {
            return [];
        }

        $kind = (string) self::kind($record, $selectedKeys);
        $items = CoordinationServiceItemsManager::associatedServiceItemsForManagement($record)->keyBy('key');
        $rows = [];

        foreach (self::invoiceableKeys($record, $selectedKeys) as $key) {
            $existing = self::rowByKey($existingRows, $key);
            $item = $items->get($key);
            $rowKey = 'row-'.$kind.'-'.ClinicalServiceCatalog::idFromKey($key);

            if ($existing === null && self::isServiceKind($kind)) {
                $existing = [
                    'key' => $key,
                    'kind' => $kind,
                    'prescribed_name' => trim((string) ($item['label'] ?? '')),
                    'prescribed_detail' => trim((string) ($item['detail'] ?? '')),
                    'generate_quote' => false,
                    'catalog_id' => ClinicalServiceCatalog::matchId($kind, (string) ($item['label'] ?? '')),
                    'new_cost' => null,
                    'new_percentage' => null,
                    'products' => [],
                ];
            }

            $rows[$rowKey] = $existing ?? [
                'key' => $key,
                'kind' => self::KIND_MEDICATION,
                'prescribed_name' => trim((string) ($item['label'] ?? '')),
                'prescribed_detail' => trim((string) ($item['detail'] ?? '')),
                'search_term' => trim((string) ($item['label'] ?? '')),
                'generate_quote' => false,
                'failure_reason' => null,
                'failure_notes' => null,
                'products' => [
                    'line-1' => ['product_id' => null, 'ubication_id' => null, 'quantity' => 1, 'new_cost' => null],
                ],
            ];
        }

        return $rows;
    }

    /**
     * Sugerencias del buscador para un medicamento: lo que contenga su nombre en el inventario.
     *
     * @return array<int, string>
     */
    public static function productOptions(?string $term, mixed $selectedProductId = null): array
    {
        $products = MedicationInventoryCatalog::search((string) $term);
        $selectedProductId = (int) $selectedProductId;

        if ($selectedProductId > 0 && ! $products->contains('id', $selectedProductId)) {
            $products = $products->concat(MedicationInventoryCatalog::productsById([$selectedProductId])->values());
        }

        return MedicationInventoryCatalog::optionLabels($products);
    }

    public static function productIsSelectable(mixed $productId): bool
    {
        $product = MedicationInventoryCatalog::productsById([(int) $productId])->first();

        return $product instanceof OperationInventoryProduct
            && MedicationInventoryCatalog::unsellableReason($product, MedicationInventoryCatalog::totalAvailable((int) $product->id), allowMissingCost: true) === null;
    }

    /**
     * El producto elegido no tiene costo: la línea pide cargarlo.
     */
    public static function productNeedsCost(mixed $productId): bool
    {
        $product = MedicationInventoryCatalog::productsById([(int) $productId])->first();

        return $product instanceof OperationInventoryProduct && MedicationInventoryCatalog::needsCost($product);
    }

    public static function categoryPercentageFor(mixed $productId): ?float
    {
        $product = MedicationInventoryCatalog::productsById([(int) $productId])->first();

        return $product instanceof OperationInventoryProduct ? OperationInventoryProductPricing::profitPercentageFor($product) : null;
    }

    /**
     * Costo con el que se factura la línea: el del producto o, si no tiene, el que cargó el analista.
     */
    public static function effectiveCost(OperationInventoryProduct $product, mixed $newCost): ?float
    {
        if (! MedicationInventoryCatalog::needsCost($product)) {
            return (float) $product->cost;
        }

        return is_numeric($newCost) && (float) $newCost > 0 ? round((float) $newCost, 2) : null;
    }

    /**
     * Almacenes con existencia del producto: id => «Almacén (N disp.)».
     *
     * @return array<int, string>
     */
    public static function ubicationOptions(mixed $productId): array
    {
        $productId = (int) $productId;

        if ($productId <= 0) {
            return [];
        }

        return collect(MedicationInventoryCatalog::availabilityByProduct([$productId])[$productId] ?? [])
            ->mapWithKeys(fn (array $row): array => [$row['ubication_id'] => $row['ubication_name'].' ('.$row['available'].' disp.)'])
            ->all();
    }

    public static function availableAt(mixed $productId, mixed $ubicationId): int
    {
        $productId = (int) $productId;

        return (int) (MedicationInventoryCatalog::availabilityByProduct([$productId])[$productId][(int) $ubicationId]['available'] ?? 0);
    }

    /**
     * Almacén a preseleccionar: el único con existencia, si solo hay uno.
     */
    public static function singleUbicationId(mixed $productId): ?int
    {
        $options = self::ubicationOptions($productId);

        return count($options) === 1 ? (int) array_key_first($options) : null;
    }

    public static function lineSummary(mixed $productId, mixed $quantity, mixed $newCost = null): HtmlString
    {
        $product = MedicationInventoryCatalog::productsById([(int) $productId])->first();

        if (! $product instanceof OperationInventoryProduct) {
            return new HtmlString('<span class="text-sm text-gray-500 dark:text-gray-400">Elija un producto para ver su precio.</span>');
        }

        $cost = self::effectiveCost($product, $newCost);
        $price = $cost !== null ? MedicationInventoryCatalog::sellingPrice($product, $cost) : null;

        if ($price === null && MedicationInventoryCatalog::needsCost($product)) {
            return new HtmlString('<span class="text-sm font-medium text-amber-700 dark:text-amber-300">'
                .'Este producto no tiene costo. Escríbalo arriba: el precio se calcula con el '.e(number_format((float) OperationInventoryProductPricing::profitPercentageFor($product), 2, ',', '.')).' % de la categoría.</span>');
        }

        if ($price === null) {
            return new HtmlString('<span class="text-sm font-medium text-rose-600 dark:text-rose-400">'
                .e(OperationInventoryProductPricing::missingPriceReason($product) ?? 'Sin precio de venta: cargue el costo del producto.')
                .'</span>');
        }

        $quantity = max(0, (int) $quantity);

        return new HtmlString(
            '<span class="text-sm text-gray-600 dark:text-gray-300">US$ '.e(number_format($price, 2, '.', ',')).' c/u'
            .' · <span class="font-semibold text-gray-950 dark:text-white">Subtotal US$ '.e(number_format($price * $quantity, 2, '.', ',')).'</span></span>'
        );
    }

    /**
     * Resumen en vivo de la factura que se emitirá.
     */
    public static function invoiceSummary(OperationCoordinationService $record, mixed $selectedKeys, mixed $rows): HtmlString
    {
        $invoiceKeys = self::invoiceKeys($record, $selectedKeys, $rows);
        $quoteKeys = self::quoteKeys($record, $selectedKeys, $rows);
        $productIds = [];

        foreach ($invoiceKeys as $key) {
            foreach ((array) (self::rowByKey($rows, $key)['products'] ?? []) as $line) {
                $productIds[] = (int) ($line['product_id'] ?? 0);
            }
        }

        $products = MedicationInventoryCatalog::productsById($productIds);
        $total = 0.0;
        $lines = 0;
        $kind = self::kind($record, $selectedKeys);
        $isService = self::isServiceKind($kind);

        if ($isService) {
            $entries = ClinicalServiceCatalog::findMany((string) $kind, array_map(fn (string $key): int => (int) (self::rowByKey($rows, $key)['catalog_id'] ?? 0), $invoiceKeys));

            foreach ($invoiceKeys as $key) {
                $row = self::rowByKey($rows, $key) ?? [];
                $price = ClinicalServiceCatalog::price($entries->get((int) ($row['catalog_id'] ?? 0)), $row['new_cost'] ?? null, $row['new_percentage'] ?? null);

                if ($price !== null) {
                    $total += $price;
                    $lines++;
                }
            }
        }

        foreach ($isService ? [] : $invoiceKeys as $key) {
            foreach ((array) (self::rowByKey($rows, $key)['products'] ?? []) as $line) {
                $product = $products->get((int) ($line['product_id'] ?? 0));
                $cost = $product instanceof OperationInventoryProduct ? self::effectiveCost($product, $line['new_cost'] ?? null) : null;
                $price = $cost !== null ? MedicationInventoryCatalog::sellingPrice($product, $cost) : null;

                if ($price !== null && (int) ($line['quantity'] ?? 0) > 0) {
                    $total += $price * (int) $line['quantity'];
                    $lines++;
                }
            }
        }

        $rate = BcvOfficialRate::suggested();
        $ves = $rate !== null ? 'Bs. '.number_format($total * $rate, 2, '.', ',').' (tasa BCV '.number_format($rate, 2, '.', ',').')' : 'Tasa BCV no disponible';
        $quoteNote = match (true) {
            $quoteKeys === [] => '',
            $isService => '<p class="mt-2 text-sm text-amber-700 dark:text-amber-300">'.count($quoteKeys).' ítem(s) irán a la cotización con el proveedor.</p>',
            default => '<p class="mt-2 text-sm text-amber-700 dark:text-amber-300">'.count($quoteKeys).' medicamento(s) irán a la cotización y quedarán registrados en Fallas de medicamentos.</p>',
        };

        if ($invoiceKeys === []) {
            return new HtmlString('<div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">'
                .'<p class="text-sm font-semibold text-amber-800 dark:text-amber-200">No se emitirá factura</p>'
                .'<p class="mt-1 text-sm text-amber-700 dark:text-amber-300">Todos los ítems irán a cotización.</p>'
                .$quoteNote.'</div>');
        }

        $consequence = $isService
            ? 'Al confirmar: los ítems quedan EN GESTION, se crea la orden de servicio al proveedor que los realiza (complete el paso «Orden de servicio») y la cuenta por cobrar. Factura interna, no fiscal.'
            : 'Al confirmar: se descuenta el inventario, los medicamentos quedan FINALIZADOS y se crea la cuenta por cobrar. Factura interna, no fiscal.';

        return new HtmlString('<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10">'
            .'<p class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">Factura a emitir al paciente</p>'
            .'<p class="mt-1 text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300">US$ '.e(number_format($total, 2, '.', ',')).'</p>'
            .'<p class="text-sm text-emerald-700/90 dark:text-emerald-300/90">'.e($ves).' · '.$lines.' línea(s)</p>'
            .'<p class="mt-2 text-xs text-emerald-800/80 dark:text-emerald-200/80">'.e($consequence).'</p>'
            .$quoteNote.'</div>');
    }

    /**
     * Precio de una línea de servicio y cómo se calculó.
     */
    public static function serviceLineSummary(?string $kind, mixed $catalogId, mixed $newCost, mixed $newPercentage): HtmlString
    {
        $entry = ClinicalServiceCatalog::find((string) $kind, $catalogId);

        if ($entry === null) {
            return new HtmlString('<span class="text-sm text-gray-500 dark:text-gray-400">Elija el ítem del catálogo para ver su precio.</span>');
        }

        $price = ClinicalServiceCatalog::price($entry, $newCost, $newPercentage);

        if ($price === null) {
            return new HtmlString('<span class="text-sm font-medium text-amber-700 dark:text-amber-300">'
                .'Este ítem no tiene '.(ClinicalServiceCatalog::needsCost($entry) ? 'costo' : '').(ClinicalServiceCatalog::needsCost($entry) && ClinicalServiceCatalog::needsPercentage($entry) ? ' ni ' : '').(ClinicalServiceCatalog::needsPercentage($entry) ? '% de utilidad' : '')
                .' en el catálogo. Cárguelo arriba: quedará guardado en el catálogo.</span>');
        }

        $cost = ClinicalServiceCatalog::needsCost($entry) ? (float) $newCost : (float) $entry->getAttribute('cost');
        $percentage = ClinicalServiceCatalog::needsPercentage($entry) ? (float) $newPercentage : (float) $entry->getAttribute('profit_percentage');

        return new HtmlString('<span class="text-sm text-gray-600 dark:text-gray-300">Costo US$ '.e(number_format($cost, 2, '.', ',')).' + '.e(number_format($percentage, 2, ',', '.')).' %'
            .' · <span class="font-semibold text-gray-950 dark:text-white">Precio al paciente US$ '.e(number_format($price, 2, '.', ',')).'</span></span>');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function rowsList(mixed $rows): array
    {
        return array_values(array_filter(is_array($rows) ? $rows : [], 'is_array'));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function rowByKey(mixed $rows, string $key): ?array
    {
        foreach (self::rowsList($rows) as $row) {
            if ((string) ($row['key'] ?? '') === $key) {
                return $row;
            }
        }

        return null;
    }
}
