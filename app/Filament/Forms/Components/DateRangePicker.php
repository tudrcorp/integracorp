<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use Carbon\CarbonImmutable;
use Filament\Forms\Components\Field;

/**
 * Selector de rango de fechas en un solo calendario: el primer clic marca el inicio, el
 * segundo el fin, y los días entre ambos quedan sombreados. Todo ocurre en el navegador
 * (Alpine); el estado viaja al servidor recién al aplicar, como cualquier filtro diferido.
 *
 * Estado: `['from' => 'Y-m-d'|null, 'until' => 'Y-m-d'|null]`. Leerlo siempre con
 * {@see self::normalize()}: el cliente puede mandar cualquier cosa.
 */
class DateRangePicker extends Field
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.forms.components.date-range-picker';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(['from' => null, 'until' => null]);

        $this->afterStateHydrated(function (DateRangePicker $component, mixed $state): void {
            $range = self::normalize($state);
            $component->state(['from' => $range['from']?->toDateString(), 'until' => $range['until']?->toDateString()]);
        });
    }

    /**
     * Rango válido y ordenado. Con una sola fecha elegida, el rango es ese día.
     *
     * @return array{from: ?CarbonImmutable, until: ?CarbonImmutable}
     */
    public static function normalize(mixed $state): array
    {
        $state = is_array($state) ? $state : [];
        $from = self::parse($state['from'] ?? null);
        $until = self::parse($state['until'] ?? null);

        if ($from === null && $until === null) {
            return ['from' => null, 'until' => null];
        }

        $from ??= $until;
        $until ??= $from;

        return $from->greaterThan($until)
            ? ['from' => $until, 'until' => $from]
            : ['from' => $from, 'until' => $until];
    }

    /**
     * «01/07/2026 – 17/07/2026», o una sola fecha si el rango es de un día.
     */
    public static function describe(mixed $state): ?string
    {
        ['from' => $from, 'until' => $until] = self::normalize($state);

        if ($from === null || $until === null) {
            return null;
        }

        return $from->isSameDay($until)
            ? $from->format('d/m/Y')
            : $from->format('d/m/Y').' – '.$until->format('d/m/Y');
    }

    private static function parse(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        if (! checkdate($month, $day, $year) || $year < 1900 || $year > 2999) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day)->startOfDay();
    }
}
