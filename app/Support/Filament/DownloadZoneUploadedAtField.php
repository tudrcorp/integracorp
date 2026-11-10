<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\DownloadZone;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\Carbon;

/**
 * Campo de solo lectura con la fecha/hora de subida del documento en Zona de descargas.
 */
final class DownloadZoneUploadedAtField
{
    public static function make(): Placeholder
    {
        return Placeholder::make('document_uploaded_at')
            ->label('Fecha de subida')
            ->helperText('Fecha y hora en que se registró este documento.')
            ->content(fn (?DownloadZone $record): string => self::format($record?->created_at))
            ->hiddenOn('create');
    }

    public static function format(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $carbon = $value instanceof Carbon
            ? $value
            : Carbon::parse($value);

        return $carbon
            ->timezone(config('app.timezone'))
            ->format('d/m/Y H:i');
    }
}
