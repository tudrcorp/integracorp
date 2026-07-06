<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use App\Models\PlanGenerator;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

final class PlanGeneratorCompanyRates
{
    /**
     * @return array<string, string>
     */
    public static function paymentFrequencyOptions(?array $payload): array
    {
        $options = [
            'ANUAL' => 'ANUAL',
            'SEMESTRAL' => 'SEMESTRAL',
            'TRIMESTRAL' => 'TRIMESTRAL',
        ];

        if ((bool) ($payload['plan']['include_monthly_total'] ?? false)) {
            $options['MENSUAL'] = 'MENSUAL';
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function columnOptions(?array $payload): array
    {
        $options = [];
        $records = is_array($payload['data_records'] ?? null) ? $payload['data_records'] : [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $columnKey = $record['column_key'] ?? null;

            if (! is_string($columnKey) || $columnKey === '') {
                continue;
            }

            $label = (string) ($record['header_label'] ?? $columnKey);
            $options[$columnKey] = $label;
        }

        if ($options !== []) {
            return $options;
        }

        $columns = is_array($payload['columns'] ?? null) ? $payload['columns'] : [];

        foreach ($columns as $column) {
            if (! is_array($column)) {
                continue;
            }

            $columnKey = $column['column_key'] ?? null;

            if (! is_string($columnKey) || $columnKey === '') {
                continue;
            }

            $options[$columnKey] = (string) ($column['header_label'] ?? $columnKey);
        }

        return $options;
    }

    public static function defaultColumnKey(?array $payload): ?string
    {
        $options = self::columnOptions($payload);

        if ($options === []) {
            return null;
        }

        return array_key_first($options);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function dataRecordForColumn(?array $payload, ?string $columnKey): ?array
    {
        $records = is_array($payload['data_records'] ?? null) ? $payload['data_records'] : [];

        if ($columnKey === null) {
            $first = $records[0] ?? null;

            return is_array($first) ? $first : null;
        }

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            if (($record['column_key'] ?? null) === $columnKey) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $dataRecord
     * @return array{fee_anual: float, total_amount: float}
     */
    public static function amountsFor(string $frequency, array $dataRecord): array
    {
        $feeAnual = (float) ($dataRecord['subtotal_anual'] ?? 0);

        $totalAmount = match (strtoupper($frequency)) {
            'SEMESTRAL' => (float) ($dataRecord['subtotal_biannual'] ?? 0),
            'TRIMESTRAL' => (float) ($dataRecord['subtotal_quarterly'] ?? 0),
            'MENSUAL' => (float) ($dataRecord['subtotal_monthly'] ?? 0),
            default => $feeAnual,
        };

        return [
            'fee_anual' => $feeAnual,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function payloadForPlan(PlanGenerator $plan): array
    {
        return PlanGeneratorPreAffiliationSession::buildPayloadForPlan(
            $plan,
            PlanGeneratorPreAffiliationSession::TYPE_NEW_BUSINESS,
        );
    }

    public static function syncAmounts(Get $get, Set $set, ?array $payload = null): void
    {
        $payload ??= PlanGeneratorPreAffiliationSession::get();

        if ($payload === null) {
            return;
        }

        $columnKey = $get('plan_generator_column_key');
        $frequency = (string) ($get('payment_frequency') ?? 'ANUAL');
        $dataRecord = self::dataRecordForColumn($payload, is_string($columnKey) ? $columnKey : null);

        if ($dataRecord === null) {
            return;
        }

        $amounts = self::amountsFor($frequency, $dataRecord);

        $set('fee_anual', $amounts['fee_anual']);
        $set('total_amount', $amounts['total_amount']);
        $set('plan_generator_column_label', (string) ($dataRecord['header_label'] ?? ''));
    }
}
