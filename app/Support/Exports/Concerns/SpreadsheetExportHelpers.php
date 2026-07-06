<?php

declare(strict_types=1);

namespace App\Support\Exports\Concerns;

use Illuminate\Support\Carbon;

trait SpreadsheetExportHelpers
{
    protected static function stringValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    protected static function numericValue(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return str_contains((string) $value, '.') ? (float) $value : (int) $value;
        }

        return null;
    }

    /**
     * @param  iterable<object>  $entries
     * @param  callable(object): string|null  $textResolver
     * @param  callable(object): string|null  $authorResolver
     * @param  callable(object): mixed  $dateResolver
     */
    protected static function concatObservationEntries(
        iterable $entries,
        callable $textResolver,
        callable $authorResolver,
        callable $dateResolver,
    ): ?string {
        $parts = [];

        foreach ($entries as $entry) {
            $text = trim((string) ($textResolver($entry) ?? ''));

            if ($text === '') {
                continue;
            }

            $author = trim((string) ($authorResolver($entry) ?? ''));
            $date = $dateResolver($entry);
            $formattedDate = self::formatObservationDate($date);

            $parts[] = sprintf('[%s | %s] %s', $formattedDate, $author !== '' ? $author : '—', $text);
        }

        if ($parts === []) {
            return null;
        }

        return implode(' || ', $parts);
    }

    protected static function formatObservationDate(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->timezone(config('app.timezone'))->format('Y-m-d H:i');
        }

        if ($value === null || $value === '') {
            return '—';
        }

        try {
            return Carbon::parse((string) $value)->timezone(config('app.timezone'))->format('Y-m-d H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    /**
     * @param  array<int, mixed>|null  $value
     */
    protected static function stringifyList(mixed $value, string $separator = '; '): ?string
    {
        if (! is_array($value)) {
            return self::stringValue($value);
        }

        $parts = [];

        foreach ($value as $item) {
            if (is_string($item)) {
                $item = trim($item);

                if ($item !== '') {
                    $parts[] = $item;
                }

                continue;
            }

            if (is_scalar($item)) {
                $parts[] = (string) $item;
            }
        }

        if ($parts === []) {
            return null;
        }

        return implode($separator, array_values(array_unique($parts)));
    }

    protected function buildFilename(string $prefix): string
    {
        return sprintf('%s_%s.xlsx', $prefix, now()->format('Y-m-d_His'));
    }

    protected function regionLabel(mixed $record): ?string
    {
        $relation = $record->region ?? null;

        if (is_object($relation) && isset($relation->definition)) {
            return self::stringValue($relation->definition);
        }

        return self::stringValue($record->getAttribute('region'));
    }
}
