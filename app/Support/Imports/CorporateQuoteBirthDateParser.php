<?php

namespace App\Support\Imports;

use Carbon\Carbon;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;

class CorporateQuoteBirthDateParser
{
    /**
     * @var list<string>
     */
    private const FORMATS = [
        'd/m/Y',
        'd-m-Y',
        'Y-m-d',
        'd/m/y',
        'd-m-y',
        'Y/m/d',
    ];

    /**
     * @throws RowImportFailedException
     */
    public function parse(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        if (! is_string($value) && ! is_numeric($value)) {
            throw new RowImportFailedException('La fecha de nacimiento está vacía o es inválida.');
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            throw new RowImportFailedException('La fecha de nacimiento está vacía.');
        }

        foreach (self::FORMATS as $format) {
            if (! Carbon::hasFormat($raw, $format)) {
                continue;
            }

            try {
                return Carbon::createFromFormat('!'.$format, $raw)->startOfDay();
            } catch (\Throwable) {
                continue;
            }
        }

        throw new RowImportFailedException("No se pudo interpretar la fecha de nacimiento [{$raw}]. Use formato DD/MM/AAAA.");
    }
}
