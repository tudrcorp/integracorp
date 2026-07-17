<?php

namespace App\Support\Imports;

use Carbon\Carbon;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;

class CorporateQuoteBirthDateParser
{
    /**
     * @throws RowImportFailedException
     */
    public function parse(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        if (! is_string($value) && ! is_numeric($value)) {
            throw new RowImportFailedException('La fecha de nacimiento está vacía o es inválida.');
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            throw new RowImportFailedException('La fecha de nacimiento está vacía.');
        }

        if ($excelDate = $this->parseExcelSerial($raw)) {
            return $excelDate;
        }

        $normalized = $this->stripTime($raw);

        if ($isoDate = $this->parseIsoDate($normalized)) {
            return $this->guardReasonableBirthDate($isoDate, $raw);
        }

        if ($numericDate = $this->parseDayMonthYear($normalized)) {
            return $this->guardReasonableBirthDate($numericDate, $raw);
        }

        throw new RowImportFailedException("No se pudo interpretar la fecha de nacimiento [{$raw}]. Use formato DD/MM/AAAA.");
    }

    private function stripTime(string $raw): string
    {
        return trim((string) preg_replace('/[ T]\d{1,2}:\d{2}(:\d{2})?(\.\d+)?(Z)?$/i', '', $raw));
    }

    private function parseIsoDate(string $value): ?Carbon
    {
        if (! preg_match('/^(?<year>\d{4})[-\/.](?<month>\d{1,2})[-\/.](?<day>\d{1,2})$/', $value, $matches)) {
            return null;
        }

        return $this->makeDate(
            (int) $matches['year'],
            (int) $matches['month'],
            (int) $matches['day'],
        );
    }

    private function parseDayMonthYear(string $value): ?Carbon
    {
        if (! preg_match('/^(?<first>\d{1,2})[-\/.](?<second>\d{1,2})[-\/.](?<year>\d{2,4})$/', $value, $matches)) {
            return null;
        }

        $first = (int) $matches['first'];
        $second = (int) $matches['second'];
        $year = $this->normalizeYear((int) $matches['year'], (string) $matches['year']);

        // Prefer DD/MM/YYYY (Venezuela). Fall back to MM/DD/YYYY when day would be invalid.
        if ($second >= 1 && $second <= 12 && checkdate($second, $first, $year)) {
            return $this->makeDate($year, $second, $first);
        }

        if ($first >= 1 && $first <= 12 && checkdate($first, $second, $year)) {
            return $this->makeDate($year, $first, $second);
        }

        return null;
    }

    private function normalizeYear(int $year, string $rawYear): int
    {
        if (strlen($rawYear) === 4) {
            return $year;
        }

        // Excel/CSV 2-digit years: 00-30 => 2000-2030, 31-99 => 1931-1999.
        return $year <= 30 ? 2000 + $year : 1900 + $year;
    }

    private function makeDate(int $year, int $month, int $day): ?Carbon
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return Carbon::create($year, $month, $day, 0, 0, 0)->startOfDay();
    }

    private function parseExcelSerial(string $raw): ?Carbon
    {
        if (! preg_match('/^\d+(\.\d+)?$/', $raw)) {
            return null;
        }

        $serial = (float) $raw;

        // Excel serial dates for people (~1910-2100). Avoid treating small IDs as dates.
        if ($serial < 2000 || $serial > 73000) {
            return null;
        }

        try {
            $date = Carbon::create(1899, 12, 30, 0, 0, 0)
                ->addDays((int) floor($serial))
                ->startOfDay();

            return $this->guardReasonableBirthDate($date, $raw);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @throws RowImportFailedException
     */
    private function guardReasonableBirthDate(Carbon $date, string $raw): Carbon
    {
        $date = $date->startOfDay();

        if ($date->year < 1900 || $date->greaterThan(now()->startOfDay())) {
            throw new RowImportFailedException("La fecha de nacimiento [{$raw}] quedó fuera de rango ({$date->format('Y-m-d')}).");
        }

        return $date;
    }
}
