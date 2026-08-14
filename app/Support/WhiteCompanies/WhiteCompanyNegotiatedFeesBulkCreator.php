<?php

declare(strict_types=1);

namespace App\Support\WhiteCompanies;

use App\Models\WhiteCompany;
use App\Models\WhiteCompanyFee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WhiteCompanyNegotiatedFeesBulkCreator
{
    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<WhiteCompanyFee>
     */
    public static function createForCompany(WhiteCompany $company, array $items, ?string $createdBy): array
    {
        $alreadyUsed = $company->negotiatedFees()
            ->pluck('fee_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $prepared = self::normalize($items, $alreadyUsed);

        return DB::transaction(function () use ($company, $prepared, $createdBy): array {
            $created = [];

            foreach ($prepared as $item) {
                $created[] = $company->negotiatedFees()->create([
                    'fee_id' => $item['fee_id'],
                    'sale_price' => $item['sale_price'],
                    'neta' => $item['neta'],
                    'status' => 'ACTIVO',
                    'created_by' => $createdBy,
                ]);
            }

            return $created;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<int>  $alreadyUsedFeeIds
     * @return list<array{fee_id: int, sale_price: float, neta: float}>
     */
    public static function normalize(array $items, array $alreadyUsedFeeIds = []): array
    {
        $prepared = [];
        $seen = [];
        $used = array_flip(array_map('intval', $alreadyUsedFeeIds));

        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $feeId = isset($item['fee_id']) ? (int) $item['fee_id'] : 0;

            if ($feeId <= 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.fee_id" => 'Debe seleccionar la tarifa del catálogo.',
                ]);
            }

            if (isset($seen[$feeId]) || isset($used[$feeId])) {
                throw ValidationException::withMessages([
                    "items.{$index}.fee_id" => 'Esta tarifa ya tiene neta pactada para la empresa aliada.',
                ]);
            }

            if (! isset($item['sale_price']) || $item['sale_price'] === '' || $item['sale_price'] === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.sale_price" => 'Debe indicar el precio de venta.',
                ]);
            }

            if (! isset($item['neta']) || $item['neta'] === '' || $item['neta'] === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.neta" => 'Debe indicar la neta.',
                ]);
            }

            $salePrice = (float) $item['sale_price'];
            $neta = (float) $item['neta'];

            if ($salePrice < 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.sale_price" => 'El precio de venta no puede ser negativo.',
                ]);
            }

            if ($neta < 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.neta" => 'La neta no puede ser negativa.',
                ]);
            }

            if ($neta > $salePrice) {
                throw ValidationException::withMessages([
                    "items.{$index}.neta" => 'La neta no puede ser mayor que el precio de venta.',
                ]);
            }

            $seen[$feeId] = true;
            $prepared[] = [
                'fee_id' => $feeId,
                'sale_price' => $salePrice,
                'neta' => $neta,
            ];
        }

        if ($prepared === []) {
            throw ValidationException::withMessages([
                'items' => 'Debe agregar al menos una tarifa.',
            ]);
        }

        return $prepared;
    }
}
