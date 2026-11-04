<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

final class PlanGeneratorGroupTotalCalculator
{
    public const ROW_ANNUAL = 'annual';

    public const ROW_SEMESTRAL = 'semestral';

    public const ROW_TRIMESTRAL = 'trimestral';

    public const ROW_MENSUAL = 'mensual';

    /**
     * Sufijo de la fila «pagos siguientes» de cada frecuencia (`semestral_next`, …).
     */
    public const NEXT_SUFFIX = '_next';

    /**
     * `$upgradesAnnual` es el costo anual de los upgrades de la cotización
     * (`PlanGeneratorUpgrades::quote()`): cubre a toda la población, así que se
     * suma igual a cada columna que tiene tarifa. Una columna sin tarifas sigue
     * en cero.
     *
     * Por defecto los upgrades se cobran Anual (`UpgradeInstallments`): completos en el primer
     * pago. Por eso cada frecuencia trae su primer pago (`semestral`, …: plan ÷ n + upgrades) y
     * sus pagos siguientes (`semestral_next`, …: solo plan ÷ n).
     *
     * Si el analista eligió cobrar los upgrades con una frecuencia (`$upgradeBillingFrequency`,
     * ver UpgradeBillingChoice), en esa fila van repartidos: todos los pagos valen
     * (plan + upgrades) ÷ n. Las demás frecuencias siguen con el cobro Anual, porque el upgrade
     * solo puede repartirse con la misma frecuencia de la afiliación.
     *
     * @param  array<int, array<string, mixed>>  $columns
     * @param  array<string, array<string, mixed>>  $rateRows
     * @return array<string, array<string, float>>
     */
    public static function totalsByColumn(array $columns, array $rateRows, float $upgradesAnnual = 0.0, array|string|null $upgradeBillingFrequency = null): array
    {
        $spreadRows = self::spreadRows($upgradeBillingFrequency);
        $columns = PlanGeneratorMatrixState::normalizeColumns($columns);
        $upgradesAnnual = max(0.0, $upgradesAnnual);
        $totals = [
            self::ROW_ANNUAL => [],
            self::ROW_SEMESTRAL => [],
            self::ROW_TRIMESTRAL => [],
            self::ROW_MENSUAL => [],
            self::ROW_SEMESTRAL.self::NEXT_SUFFIX => [],
            self::ROW_TRIMESTRAL.self::NEXT_SUFFIX => [],
            self::ROW_MENSUAL.self::NEXT_SUFFIX => [],
        ];

        foreach (PlanGeneratorMatrixState::extractColumnKeys($columns) as $columnKey) {
            $planAnnual = self::annualTotalForColumn($columnKey, $rateRows);
            $upgrades = $planAnnual > 0 ? $upgradesAnnual : 0.0;
            $totals[self::ROW_ANNUAL][$columnKey] = $planAnnual + $upgrades;

            foreach ([self::ROW_SEMESTRAL => 2, self::ROW_TRIMESTRAL => 4, self::ROW_MENSUAL => 12] as $row => $installments) {
                if (in_array($row, $spreadRows, true)) {
                    $totals[$row][$columnKey] = ($planAnnual + $upgrades) / $installments;
                    $totals[$row.self::NEXT_SUFFIX][$columnKey] = ($planAnnual + $upgrades) / $installments;

                    continue;
                }

                $totals[$row][$columnKey] = $planAnnual / $installments + $upgrades;
                $totals[$row.self::NEXT_SUFFIX][$columnKey] = $planAnnual / $installments;
            }
        }

        return $totals;
    }

    /**
     * Filas que se dibujan en el total grupal. Sin upgrades son las de `groupTotalRows()`;
     * con upgrades, cada frecuencia en cuotas se abre en «1.er pago» (con los upgrades) y
     * «pagos siguientes» (solo el plan). `frequency_key` es la fila de la que salen: es la
     * que se quita o se restaura.
     *
     * @return list<array{key: string, frequency_key: string, label: string, bold: bool}>
     */
    public static function displayRows(bool $includeMonthlyTotal, mixed $hiddenRows, float $upgradesAnnual = 0.0, array|string|null $upgradeBillingFrequency = null): array
    {
        $rows = [];
        $spreadRows = self::spreadRows($upgradeBillingFrequency);
        [$includeMonthlyTotal, $hiddenRows] = self::visibilityForUpgradeBilling($includeMonthlyTotal, $hiddenRows, $upgradeBillingFrequency, $upgradesAnnual);

        foreach (self::groupTotalRows($includeMonthlyTotal, $hiddenRows) as $row) {
            if ($upgradesAnnual > 0 && in_array($row['key'], $spreadRows, true)) {
                $rows[] = [...$row, 'frequency_key' => $row['key'], 'label' => $row['label'].' (upgrades en cada cuota)'];

                continue;
            }

            if ($upgradesAnnual <= 0 || $row['key'] === self::ROW_ANNUAL) {
                $rows[] = [...$row, 'frequency_key' => $row['key'], 'label' => $row['key'] === self::ROW_ANNUAL && $upgradesAnnual > 0 ? $row['label'].' (pago único)' : $row['label']];

                continue;
            }

            $installments = match ($row['key']) {
                self::ROW_SEMESTRAL => 2,
                self::ROW_TRIMESTRAL => 4,
                default => 12,
            };

            $rows[] = ['key' => $row['key'], 'frequency_key' => $row['key'], 'label' => $row['label'].' · 1.er pago', 'bold' => $row['bold']];
            $rows[] = [
                'key' => $row['key'].self::NEXT_SUFFIX,
                'frequency_key' => $row['key'],
                'label' => $row['label'].($installments === 2 ? ' · 2.º pago' : ' · pagos 2 a '.$installments),
                'bold' => false,
            ];
        }

        return $rows;
    }

    /**
     * Filas cuyos pagos llevan los upgrades repartidos (las frecuencias marcadas, salvo la
     * anual, que siempre es un pago único).
     *
     * @return list<string>
     */
    public static function spreadRows(array|string|null $upgradeBillingFrequencies): array
    {
        $rows = [];

        foreach (\App\Support\Upgrades\UpgradeBillingChoice::normalizeList(is_array($upgradeBillingFrequencies) ? $upgradeBillingFrequencies : [$upgradeBillingFrequencies]) as $frequency) {
            $row = array_search($frequency, self::ROW_PAYMENT_FREQUENCIES, true);

            if (is_string($row) && $row !== self::ROW_ANNUAL) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Las filas de las frecuencias con upgrades repartidos siempre se muestran: son los pagos
     * que el cliente tiene que ver, aunque el analista haya ocultado esas frecuencias.
     *
     * @return array{0: bool, 1: list<string>} incluir mensual, filas ocultas
     */
    public static function visibilityForUpgradeBilling(bool $includeMonthlyTotal, mixed $hiddenRows, array|string|null $upgradeBillingFrequency, float $upgradesAnnual): array
    {
        $hidden = self::normalizeHiddenRows($hiddenRows);
        $spreadRows = $upgradesAnnual > 0 ? self::spreadRows($upgradeBillingFrequency) : [];

        if ($spreadRows === []) {
            return [$includeMonthlyTotal, $hidden];
        }

        return [
            $includeMonthlyTotal || in_array(self::ROW_MENSUAL, $spreadRows, true),
            array_values(array_diff($hidden, $spreadRows)),
        ];
    }

    /**
     * Texto de la regla de cobro de upgrades para la cotización.
     */
    public static function upgradeRuleText(array|string|null $upgradeBillingFrequency): string
    {
        $rows = self::spreadRows($upgradeBillingFrequency);

        if ($rows === []) {
            return \App\Support\Upgrades\UpgradeInstallments::RULE.' Los pagos siguientes llevan solo el plan.';
        }

        $labels = array_map(
            fn (string $row): string => mb_strtolower(\App\Support\AffiliationCorporates\CorporatePaymentFrequency::label(self::ROW_PAYMENT_FREQUENCIES[$row])),
            $rows,
        );
        $last = array_pop($labels);
        $list = $labels === [] ? $last : implode(', ', $labels).' o '.$last;

        return 'Con pago '.$list.' los upgrades van repartidos en cada cuota. Con pago anual se pagan una sola vez, completos'
            .(count($rows) < 3 ? '; con cualquier otra frecuencia, completos en el primer pago.' : '.');
    }

    /**
     * Filas que el analista puede quitar del total grupal. La mensual no está:
     * la gobierna `include_monthly_total`.
     *
     * @var list<string>
     */
    public const HIDEABLE_ROWS = [self::ROW_ANNUAL, self::ROW_SEMESTRAL, self::ROW_TRIMESTRAL];

    /**
     * Frecuencia de pago que ofrece cada fila al registrar la empresa.
     *
     * @var array<string, string>
     */
    public const ROW_PAYMENT_FREQUENCIES = [
        self::ROW_ANNUAL => 'ANUAL',
        self::ROW_SEMESTRAL => 'SEMESTRAL',
        self::ROW_TRIMESTRAL => 'TRIMESTRAL',
        self::ROW_MENSUAL => 'MENSUAL',
    ];

    /**
     * Filas visibles del total grupal, en su orden fijo.
     *
     * Nunca devuelve una tabla vacía: si se quitaron todas, queda la anual, que
     * es el precio de referencia de la cotización.
     *
     * @param  mixed  $hiddenRows  claves quitadas (`group_total_hidden_rows`)
     * @return array<int, array{key: string, label: string, bold: bool}>
     */
    public static function groupTotalRows(bool $includeMonthlyTotal = false, mixed $hiddenRows = []): array
    {
        $hidden = self::normalizeHiddenRows($hiddenRows);

        $rows = [
            ['key' => self::ROW_ANNUAL, 'label' => 'Tarifa anual', 'bold' => true],
            ['key' => self::ROW_SEMESTRAL, 'label' => 'Tarifa Semestral', 'bold' => false],
            ['key' => self::ROW_TRIMESTRAL, 'label' => 'Tarifa Trimestral', 'bold' => false],
        ];

        if ($includeMonthlyTotal) {
            $rows[] = ['key' => self::ROW_MENSUAL, 'label' => 'Total Mensual', 'bold' => false];
        }

        $visible = array_values(array_filter(
            $rows,
            static fn (array $row): bool => ! in_array($row['key'], $hidden, true),
        ));

        return $visible !== [] ? $visible : [$rows[0]];
    }

    /**
     * Claves válidas y sin repetir. Llega del navegador (estado Livewire) o de
     * la columna JSON, así que se filtra contra la lista blanca.
     *
     * @return list<string>
     */
    public static function normalizeHiddenRows(mixed $hiddenRows): array
    {
        if (is_string($hiddenRows)) {
            $decoded = json_decode($hiddenRows, true);
            $hiddenRows = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($hiddenRows)) {
            return [];
        }

        return array_values(array_filter(
            self::HIDEABLE_ROWS,
            static fn (string $key): bool => in_array($key, $hiddenRows, true),
        ));
    }

    /**
     * Filas quitadas que se pueden volver a agregar, con su etiqueta. La
     * mensual no aparece: vuelve con el interruptor «Incluir cálculo mensual».
     *
     * @return list<array{key: string, label: string}>
     */
    public static function removedRows(bool $includeMonthlyTotal, mixed $hiddenRows): array
    {
        $visible = array_column(self::groupTotalRows($includeMonthlyTotal, $hiddenRows), 'key');
        $all = self::groupTotalRows(false);

        return array_values(array_map(
            static fn (array $row): array => ['key' => $row['key'], 'label' => $row['label']],
            array_filter($all, static fn (array $row): bool => ! in_array($row['key'], $visible, true)),
        ));
    }

    /**
     * Frecuencias de pago que admite la cotización: las de sus filas visibles.
     *
     * @return array<string, string>
     */
    public static function paymentFrequencies(bool $includeMonthlyTotal, mixed $hiddenRows): array
    {
        $options = [];

        foreach (self::groupTotalRows($includeMonthlyTotal, $hiddenRows) as $row) {
            $frequency = self::ROW_PAYMENT_FREQUENCIES[$row['key']];
            $options[$frequency] = $frequency;
        }

        return $options;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rateRows
     */
    public static function annualTotalForColumn(string $columnKey, array $rateRows): float
    {
        $total = 0.0;

        foreach ($rateRows as $rateRow) {
            if (! is_array($rateRow)) {
                continue;
            }

            $population = (int) ($rateRow['population'] ?? 0);
            if ($population <= 0) {
                continue;
            }

            $rate = self::parseAmount(data_get($rateRow, "cells.{$columnKey}.rate_amount"));
            $total += $rate * $population;
        }

        return $total;
    }

    public static function parseAmount(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = trim((string) $value);
        $normalized = str_replace([' ', '$'], '', $normalized);

        if ($normalized === '' || ! is_numeric(str_replace(',', '.', $normalized))) {
            return 0.0;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return (float) $normalized;
    }

    public static function formatGroupTotal(?float $amount): string
    {
        if ($amount === null || $amount <= 0) {
            return '—';
        }

        return '$'.number_format($amount, 0, ',', '.');
    }
}
