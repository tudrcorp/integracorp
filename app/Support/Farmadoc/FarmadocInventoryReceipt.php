<?php

declare(strict_types=1);

namespace App\Support\Farmadoc;

use App\Models\FarmadocOrder;
use App\Models\OperationInventory;
use App\Models\OperationInventoryPrincipleActive;
use App\Models\OperationInventoryProduct;
use App\Models\OperationInventoryProductCategory;
use App\Models\OperationInventoryUbication;
use App\Models\User;
use App\Services\OperationInventoryProductStockAdjuster;
use App\Support\Operations\MedicationInventoryCatalog;
use App\Support\SecurityAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Entrada a inventario de un pedido que Farmadoc ya envió.
 *
 * - Coincidencia exacta (código de barras o SKU con un producto nuestro): solo se suma existencia.
 * - Sin coincidencia exacta: el analista elige qué producto nuestro corresponde (sugeridos por
 *   principio activo y nombre) y ese producto se actualiza con los datos de Farmadoc; o crea uno nuevo.
 * - Todo en una transacción con el pedido bloqueado: nunca se carga dos veces ni queda a medias.
 * - El pedido pasa a «Registrado en inventario» y queda la nota de recibido con los valores anteriores.
 */
final class FarmadocInventoryReceipt
{
    public const TARGET_NEW = 'new';

    public const TARGET_PRODUCT_PREFIX = 'product:';

    public const ACTION_EXACT = 'exact';

    public const ACTION_UPDATE = 'update';

    public const ACTION_CREATE = 'create';

    public const ACTION_SKIPPED = 'skipped';

    public const MAX_SUGGESTIONS = 8;

    public const SEARCH_LIMIT = 30;

    public const ENTRY_TYPE_PREFIX = 'PEDIDO FARMADOC ';

    public const DEFAULT_CATEGORY = 'Medicamentos';

    public const DEFAULT_UNIT = 'UNIDAD';

    /**
     * Ubicaciones activas del inventario, para elegir de dónde sale o a dónde entra un pedido.
     *
     * @return array<int, string>
     */
    public static function ubicationOptions(): array
    {
        return OperationInventoryUbication::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, int|string $id): array => [(int) $id => $name])
            ->all();
    }

    /**
     * Medicamentos del pedido que entran (los que Farmadoc rechazó quedan fuera), con la
     * coincidencia exacta si existe y los productos sugeridos si no.
     *
     * @return list<array{
     *     key: string,
     *     name: string,
     *     barcode: string|null,
     *     sku: string|null,
     *     active_ingredient: string|null,
     *     laboratory: string|null,
     *     concentration: string|null,
     *     quantity: int,
     *     exact_product_id: int|null,
     *     exact_label: string|null,
     *     suggestions: array<string, string>
     * }>
     */
    public static function plan(FarmadocOrder $order): array
    {
        $rejected = self::rejectedKeys($order);
        $lines = [];

        foreach (FarmadocOrderItems::forDisplay($order) as $index => $item) {
            if (isset($rejected['sku'][(string) $item['sku']]) || isset($rejected['name'][self::normalize($item['name'])])) {
                continue;
            }

            $exact = self::exactProduct($item['barcode'], $item['sku']);

            $lines[] = [
                'key' => 'line-'.$index,
                'name' => $item['name'],
                'barcode' => $item['barcode'],
                'sku' => $item['sku'],
                'active_ingredient' => $item['active_ingredient'],
                'laboratory' => $item['laboratory'],
                'concentration' => $item['concentration'],
                'quantity' => max(0, $item['quantity']),
                'exact_product_id' => $exact !== null ? (int) $exact->getKey() : null,
                'exact_label' => $exact !== null ? self::productLabel($exact) : null,
                'suggestions' => $exact !== null ? [] : self::suggestions($item),
            ];
        }

        return $lines;
    }

    /**
     * Producto nuestro con el mismo código de barras o SKU del medicamento entrante.
     */
    public static function exactProduct(?string $barcode, ?string $sku): ?OperationInventoryProduct
    {
        $codes = array_values(array_unique(array_filter([
            self::code($barcode),
            self::code($sku),
            self::code(is_string($sku) ? (string) preg_replace('/^[A-Z]+-/i', '', $sku) : null),
        ])));

        if ($codes === []) {
            return null;
        }

        $product = OperationInventoryProduct::query()->whereIn('code', $codes)->orderBy('id')->first();

        if ($product !== null) {
            return $product;
        }

        $productId = OperationInventory::query()
            ->whereIn('barcode', $codes)
            ->whereNotNull('operation_inventory_product_id')
            ->orderBy('id')
            ->value('operation_inventory_product_id');

        return $productId !== null ? OperationInventoryProduct::query()->find($productId) : null;
    }

    /**
     * Productos nuestros que podrían ser el medicamento entrante, del más parecido al menos:
     * cuenta el principio activo, las palabras del nombre y la dosis.
     *
     * @param  array{name: string, active_ingredient?: string|null, laboratory?: string|null}  $item
     * @return array<string, string> «product:ID» => etiqueta
     */
    public static function suggestions(array $item): array
    {
        $ingredientTokens = self::significantTokens((string) ($item['active_ingredient'] ?? ''));
        $laboratoryTokens = self::significantTokens((string) ($item['laboratory'] ?? ''));
        $nameTokens = array_values(array_diff(self::significantTokens($item['name']), $laboratoryTokens));
        $doseTokens = self::doseTokens($item['name']);
        $searchTokens = array_values(array_unique([...$ingredientTokens, ...array_slice($nameTokens, 0, 3)]));

        if ($searchTokens === []) {
            return [];
        }

        $candidates = OperationInventoryProduct::query()
            ->where(function (Builder $query) use ($searchTokens): void {
                foreach ($searchTokens as $token) {
                    $like = '%'.self::escapeLike($token).'%';
                    $query->orWhere('name', 'like', $like)
                        ->orWhereHas('inventories', fn (Builder $inventory): Builder => $inventory->where('name', 'like', $like));
                }
            })
            ->withSum('stocks', 'existence')
            ->limit(60)
            ->get();

        $scored = $candidates
            ->map(function (OperationInventoryProduct $product) use ($ingredientTokens, $nameTokens, $doseTokens): array {
                $name = self::plain((string) $product->name);
                $score = 0;

                foreach ($ingredientTokens as $token) {
                    $score += str_contains($name, $token) ? 3 : 0;
                }

                foreach ($nameTokens as $token) {
                    $score += str_contains($name, $token) ? 2 : 0;
                }

                foreach ($doseTokens as $token) {
                    $score += str_contains(str_replace(' ', '', $name), $token) ? 1 : 0;
                }

                return ['product' => $product, 'score' => $score];
            })
            ->filter(fn (array $row): bool => $row['score'] > 0)
            ->sortBy([['score', 'desc'], [fn (array $row): string => (string) $row['product']->name, 'asc']])
            ->take(self::MAX_SUGGESTIONS);

        return $scored
            ->mapWithKeys(fn (array $row): array => [
                self::TARGET_PRODUCT_PREFIX.$row['product']->getKey() => self::productLabel($row['product']),
            ])
            ->all();
    }

    /**
     * Búsqueda libre de productos nuestros (nombre o código) para el selector.
     *
     * @return array<string, string>
     */
    public static function search(string $term): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.self::escapeLike($term).'%';

        return OperationInventoryProduct::query()
            ->where(fn (Builder $query): Builder => $query->where('name', 'like', $like)->orWhere('code', 'like', $like))
            ->withSum('stocks', 'existence')
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT)
            ->get()
            ->mapWithKeys(fn (OperationInventoryProduct $product): array => [
                self::TARGET_PRODUCT_PREFIX.$product->getKey() => self::productLabel($product),
            ])
            ->all();
    }

    public static function targetLabel(?string $target): ?string
    {
        if ($target === self::TARGET_NEW) {
            return self::newProductLabel();
        }

        $productId = self::productIdFromTarget($target);

        if ($productId === null) {
            return null;
        }

        $product = OperationInventoryProduct::query()->withSum('stocks', 'existence')->find($productId);

        return $product !== null ? self::productLabel($product) : null;
    }

    public static function newProductLabel(): string
    {
        return '➕ Crear producto nuevo con los datos de Farmadoc';
    }

    public static function productLabel(OperationInventoryProduct $product): string
    {
        $existence = $product->stocks_sum_existence ?? $product->totalExistence();

        return Str::limit(trim((string) $product->name), 70).' · '.$product->code.' · Existencia '.(int) $existence;
    }

    public static function productIdFromTarget(mixed $target): ?int
    {
        if (! is_string($target) || ! str_starts_with($target, self::TARGET_PRODUCT_PREFIX)) {
            return null;
        }

        $id = substr($target, strlen(self::TARGET_PRODUCT_PREFIX));

        return ctype_digit($id) && (int) $id > 0 ? (int) $id : null;
    }

    /**
     * Da entrada al pedido. Vuelve a calcular las coincidencias en el servidor: nunca confía
     * en lo que llegó del formulario más allá de la elección del analista y las cantidades.
     *
     * @param  array<string, array{target?: mixed, quantity?: mixed}>  $choices  por clave de línea
     * @return array{receipt_number: string, units: int, lines: int, created: int, updated: int, exact: int}
     *
     * @throws InvalidArgumentException con un mensaje para el analista
     */
    public static function register(FarmadocOrder $order, int $ubicationId, array $choices, User $user, ?string $notes = null): array
    {
        if (! FarmadocOrder::receiptColumnsExist()) {
            throw new InvalidArgumentException('Falta correr la migración de la entrada a inventario de pedidos Farmadoc.');
        }

        $ubication = OperationInventoryUbication::query()->where('is_active', true)->find($ubicationId);

        if ($ubication === null) {
            throw new InvalidArgumentException('Elija una ubicación activa del inventario.');
        }

        $result = DB::transaction(function () use ($order, $ubication, $choices, $user, $notes): array {
            /** @var FarmadocOrder $locked */
            $locked = FarmadocOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->canBeReceived()) {
                throw new InvalidArgumentException($locked->isReceived()
                    ? 'Este pedido ya se registró en el inventario (nota '.$locked->receipt_number.').'
                    : 'Solo se puede dar entrada a un pedido que Farmadoc ya envió.');
            }

            $lines = self::validatedLines(self::plan($locked), $choices);
            $entryType = self::ENTRY_TYPE_PREFIX.$locked->order_number;
            $adjuster = app(OperationInventoryProductStockAdjuster::class);
            $receiptLines = [];

            foreach ($lines as $line) {
                $receiptLines[] = self::applyLine($line, $ubication, $adjuster, $entryType, $user);
            }

            $receiptNumber = FarmadocOrder::receiptNumberFor((int) $locked->getKey());

            $locked->forceFill([
                'status' => FarmadocOrder::STATUS_RECEIVED,
                'receipt_number' => $receiptNumber,
                'received_at' => now(),
                'received_by_user_id' => $user->getKey(),
                'received_by_name' => Str::limit((string) $user->name, 250, ''),
                'receipt_ubication_id' => $ubication->getKey(),
                'receipt_items' => $receiptLines,
                'receipt_notes' => self::cleanNotes($notes),
            ])->save();

            $counts = array_count_values(array_column($receiptLines, 'action'));

            return [
                'receipt_number' => $receiptNumber,
                'units' => array_sum(array_column($receiptLines, 'quantity_received')),
                'lines' => count(array_filter($receiptLines, fn (array $line): bool => $line['quantity_received'] > 0)),
                'created' => $counts[self::ACTION_CREATE] ?? 0,
                'updated' => $counts[self::ACTION_UPDATE] ?? 0,
                'exact' => $counts[self::ACTION_EXACT] ?? 0,
            ];
        });

        SecurityAudit::log('AUDIT_FARMADOC_ORDER_RECEIVED_INTO_INVENTORY', 'operations.farmadoc-orders.receive', [
            'farmadoc_order_id' => $order->getKey(),
            'order_number' => $order->order_number,
            'receipt_number' => $result['receipt_number'],
            'ubication_id' => $ubication->getKey(),
            'units' => $result['units'],
            'created' => $result['created'],
            'updated' => $result['updated'],
            'exact' => $result['exact'],
        ], $user);

        $order->refresh();

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $plan
     * @param  array<string, array{target?: mixed, quantity?: mixed}>  $choices
     * @return list<array<string, mixed>>
     */
    private static function validatedLines(array $plan, array $choices): array
    {
        if ($plan === []) {
            throw new InvalidArgumentException('Farmadoc no aceptó ningún medicamento de este pedido: no hay nada que cargar.');
        }

        $lines = [];
        $updateTargets = [];

        foreach ($plan as $line) {
            $choice = $choices[$line['key']] ?? null;

            if (! is_array($choice)) {
                throw new InvalidArgumentException('Falta la información de «'.$line['name'].'». Recargue la página e intente de nuevo.');
            }

            $quantity = filter_var($choice['quantity'] ?? null, FILTER_VALIDATE_INT);

            if ($quantity === false || $quantity < 0 || $quantity > $line['quantity']) {
                throw new InvalidArgumentException('La cantidad recibida de «'.$line['name'].'» debe estar entre 0 y '.$line['quantity'].'.');
            }

            $line['quantity_received'] = $quantity;

            if ($quantity === 0) {
                $line['action'] = self::ACTION_SKIPPED;
                $line['product_id'] = null;
                $lines[] = $line;

                continue;
            }

            if ($line['exact_product_id'] !== null) {
                $line['action'] = self::ACTION_EXACT;
                $line['product_id'] = $line['exact_product_id'];
            } elseif (($choice['target'] ?? null) === self::TARGET_NEW) {
                $line['action'] = self::ACTION_CREATE;
                $line['product_id'] = null;
            } else {
                $productId = self::productIdFromTarget($choice['target'] ?? null);

                if ($productId === null || ! OperationInventoryProduct::query()->whereKey($productId)->exists()) {
                    throw new InvalidArgumentException('Elija qué producto del inventario corresponde a «'.$line['name'].'», o cree uno nuevo.');
                }

                if (isset($updateTargets[$productId])) {
                    throw new InvalidArgumentException('Eligió el mismo producto del inventario para «'.$updateTargets[$productId].'» y «'.$line['name'].'». Cada producto se actualiza con un solo medicamento.');
                }

                $updateTargets[$productId] = $line['name'];
                $line['action'] = self::ACTION_UPDATE;
                $line['product_id'] = $productId;
            }

            $lines[] = $line;
        }

        if (array_sum(array_column($lines, 'quantity_received')) < 1) {
            throw new InvalidArgumentException('Indique al menos una unidad recibida.');
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed> línea de la nota de recibido
     */
    private static function applyLine(
        array $line,
        OperationInventoryUbication $ubication,
        OperationInventoryProductStockAdjuster $adjuster,
        string $entryType,
        User $user,
    ): array {
        $receipt = [
            'name' => $line['name'],
            'barcode' => $line['barcode'],
            'sku' => $line['sku'],
            'quantity_ordered' => $line['quantity'],
            'quantity_received' => $line['quantity_received'],
            'action' => $line['action'],
            'product_id' => null,
            'product_name' => null,
            'previous' => null,
        ];

        if ($line['action'] === self::ACTION_SKIPPED) {
            return $receipt;
        }

        $userName = Str::limit((string) $user->name, 250, '');

        $product = match ($line['action']) {
            self::ACTION_CREATE => self::createProduct($line, $userName),
            default => OperationInventoryProduct::query()->lockForUpdate()->findOrFail($line['product_id']),
        };

        if ($line['action'] === self::ACTION_UPDATE) {
            $receipt['previous'] = self::snapshot($product);
            self::updateProduct($product, $line);
        }

        $adjuster->increase(new Collection([$product]), (int) $ubication->getKey(), (int) $line['quantity_received'], $entryType);

        if ($line['action'] !== self::ACTION_EXACT) {
            self::updateInventoryRows($product, $line, $userName);
        }

        $receipt['product_id'] = (int) $product->getKey();
        $receipt['product_name'] = (string) $product->name;

        return $receipt;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private static function createProduct(array $line, string $userName): OperationInventoryProduct
    {
        $categoryId = MedicationInventoryCatalog::medicationCategoryId()
            ?? OperationInventoryProductCategory::query()->where('name', self::DEFAULT_CATEGORY)->value('id');

        return OperationInventoryProduct::query()->create([
            'operation_inventory_product_category_id' => $categoryId,
            'code' => self::availableCode($line, null),
            'name' => Str::limit((string) $line['name'], 250, ''),
            'cost' => 0,
            'unit' => self::DEFAULT_UNIT,
            'presentation' => self::DEFAULT_UNIT,
            'is_active' => true,
            'created_by' => $userName,
        ]);
    }

    /**
     * Nombre y código del producto con los datos de Farmadoc. Nunca borra un dato con un vacío.
     *
     * @param  array<string, mixed>  $line
     */
    private static function updateProduct(OperationInventoryProduct $product, array $line): void
    {
        $product->name = Str::limit((string) $line['name'], 250, '');
        $product->code = self::availableCode($line, (int) $product->getKey()) ?? $product->code;
        $product->save();
    }

    /**
     * Las filas del producto en todas las ubicaciones quedan con los datos de Farmadoc.
     *
     * @param  array<string, mixed>  $line
     */
    private static function updateInventoryRows(OperationInventoryProduct $product, array $line, string $userName): void
    {
        $principleId = self::principleActiveId($line['active_ingredient'] ?? null, $userName);
        $changes = array_filter([
            'name' => Str::limit((string) $product->name, 250, ''),
            'barcode' => self::code($line['barcode'] ?? null) ?? $product->code,
            'operation_inventory_principle_active_id' => $principleId,
            'concentration' => self::text($line['concentration'] ?? null),
            'laboratory' => self::text($line['laboratory'] ?? null),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        if ($changes === []) {
            return;
        }

        OperationInventory::query()
            ->where('operation_inventory_product_id', $product->getKey())
            ->update([...$changes, 'updated_by' => $userName, 'updated_at' => now()]);
    }

    /**
     * Código para el producto: el de barras de Farmadoc (o su SKU), solo si ningún otro producto lo usa.
     *
     * @param  array<string, mixed>  $line
     */
    private static function availableCode(array $line, ?int $productId): ?string
    {
        foreach ([self::code($line['barcode'] ?? null), self::code($line['sku'] ?? null)] as $code) {
            if ($code === null) {
                continue;
            }

            $taken = OperationInventoryProduct::query()
                ->where('code', $code)
                ->when($productId !== null, fn (Builder $query): Builder => $query->whereKeyNot($productId))
                ->exists();

            if (! $taken) {
                return $code;
            }
        }

        if ($productId !== null) {
            return null;
        }

        do {
            $code = 'FD-'.Str::upper(Str::random(10));
        } while (OperationInventoryProduct::query()->where('code', $code)->exists());

        return $code;
    }

    private static function principleActiveId(mixed $name, string $userName): ?int
    {
        $name = self::text($name);

        if ($name === null) {
            return null;
        }

        $name = mb_strtoupper(Str::limit($name, 250, ''));
        $existing = OperationInventoryPrincipleActive::query()->where('name', $name)->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) OperationInventoryPrincipleActive::query()->forceCreate([
            'name' => $name,
            'is_active' => true,
            'created_by' => $userName,
            'updated_by' => $userName,
        ])->getKey();
    }

    /**
     * Valores del producto antes de actualizarlo, para la auditoría de la nota.
     *
     * @return array<string, mixed>
     */
    private static function snapshot(OperationInventoryProduct $product): array
    {
        $row = OperationInventory::query()
            ->where('operation_inventory_product_id', $product->getKey())
            ->orderBy('id')
            ->first(['name', 'barcode', 'operation_inventory_principle_active_id', 'concentration', 'laboratory']);

        return [
            'name' => (string) $product->name,
            'code' => (string) $product->code,
            'barcode' => $row?->barcode,
            'principle_active_id' => $row?->operation_inventory_principle_active_id,
            'concentration' => $row?->concentration,
            'laboratory' => $row?->laboratory,
        ];
    }

    /**
     * @return array{sku: array<string, true>, name: array<string, true>}
     */
    private static function rejectedKeys(FarmadocOrder $order): array
    {
        $keys = ['sku' => [], 'name' => []];

        foreach (is_array($order->acceptance_items) ? $order->acceptance_items : [] as $item) {
            if (! is_array($item) || (bool) ($item['accepted'] ?? true)) {
                continue;
            }

            if (($sku = self::text($item['sku'] ?? null)) !== null) {
                $keys['sku'][$sku] = true;
            } elseif (($name = self::text($item['name'] ?? null)) !== null) {
                $keys['name'][self::normalize($name)] = true;
            }
        }

        return $keys;
    }

    /**
     * Palabras con peso para buscar: sin dosis, sin palabras cortas ni de relleno.
     *
     * @return list<string>
     */
    private static function significantTokens(string $text): array
    {
        $tokens = [];

        foreach (MedicationInventoryCatalog::tokens($text) as $token) {
            if (MedicationInventoryCatalog::isDoseToken($token)) {
                continue;
            }

            $plain = self::plain($token);

            if (mb_strlen($plain) >= 4 && ! in_array($plain, ['PARA', 'CON', 'SOL', 'TABLETAS', 'CAPSULAS', 'COMP', 'UNID'], true)) {
                $tokens[] = $plain;
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @return list<string>
     */
    private static function doseTokens(string $text): array
    {
        $tokens = [];

        foreach (MedicationInventoryCatalog::tokens($text) as $token) {
            if (MedicationInventoryCatalog::isDoseToken($token)) {
                $tokens[] = str_replace(' ', '', self::plain($token));
            }
        }

        return array_values(array_unique($tokens));
    }

    private static function plain(string $text): string
    {
        return mb_strtoupper(Str::ascii($text));
    }

    private static function normalize(string $text): string
    {
        return self::plain((string) preg_replace('/\s+/u', ' ', trim($text)));
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private static function code(mixed $value): ?string
    {
        $value = self::text($value);

        return $value === null ? null : Str::limit(preg_replace('/\s+/', '', $value) ?? $value, 250, '');
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function cleanNotes(?string $notes): ?string
    {
        $notes = self::text($notes);

        return $notes === null ? null : Str::limit($notes, 1000, '');
    }
}
