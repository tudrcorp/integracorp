<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use App\Models\PlanGenerator;

final class PlanGeneratorPreviewBuilder
{
    /**
     * @return array{
     *     columns: array<int, array<string, mixed>>,
     *     rows: array<string, array<string, mixed>>,
     *     rate_rows: array<string, array<string, mixed>>
     * }
     */
    public static function fullMatrixFromModel(PlanGenerator $planGenerator): array
    {
        $formState = PlanGeneratorPersistence::formStateFromModel($planGenerator);

        return [
            'columns' => (array) ($formState['columns'] ?? []),
            'rows' => (array) ($formState['rows'] ?? []),
            'rate_rows' => (array) ($formState['rate_rows'] ?? []),
        ];
    }

    /**
     * @return array{columns: array<int, array<string, mixed>>, rows: array<string, array<string, mixed>>}
     */
    public static function matrixFromModel(PlanGenerator $planGenerator): array
    {
        $matrix = self::fullMatrixFromModel($planGenerator);

        return [
            'columns' => $matrix['columns'],
            'rows' => $matrix['rows'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(PlanGenerator $planGenerator): array
    {
        return PlanGeneratorMatrixState::normalizeColumns(
            self::fullMatrixFromModel($planGenerator)['columns'],
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function rows(PlanGenerator $planGenerator): array
    {
        return self::fullMatrixFromModel($planGenerator)['rows'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function rateRows(PlanGenerator $planGenerator): array
    {
        return self::fullMatrixFromModel($planGenerator)['rate_rows'];
    }

    public static function formatCoverageAmount(?float $amount): string
    {
        if ($amount === null) {
            return '';
        }

        return number_format($amount, 2, '.', ',');
    }

    public static function formatRateAmount(?float $amount): string
    {
        if ($amount === null) {
            return '';
        }

        if (floor($amount) == $amount) {
            return number_format($amount, 0, ',', '.');
        }

        return number_format($amount, 2, ',', '.');
    }
}
