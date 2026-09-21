<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\TelemedicinePatient;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

final class TelemedicinePatientPageHeader
{
    public static function forPatient(TelemedicinePatient $patient, string $context = 'view'): string|Htmlable
    {
        $name = mb_strtoupper(self::displayOrFallback($patient->full_name, 'Paciente sin nombre'));
        $status = strtoupper(self::displayOrFallback($patient->status_affiliation, 'SIN AFILIACIÓN'));
        $type = self::displayOrFallback($patient->type_affiliation, '');
        $plan = $patient->relationLoaded('plan')
            ? self::displayOrFallback($patient->plan?->description, '')
            : '';

        $chips = [];

        if ($type !== '') {
            $chips[] = [
                'label' => mb_strtoupper($type),
                'bg' => '#0284c7',
                'shadow' => '0 8px 20px rgba(2,132,199,.35)',
            ];
        }

        if ($plan !== '') {
            $chips[] = [
                'label' => $plan,
                'bg' => '#4338ca',
                'shadow' => '0 8px 20px rgba(67,56,202,.35)',
            ];
        }

        $age = filled($patient->age) ? ((int) $patient->age).' años' : null;

        return self::render(
            heading: $context === 'edit' ? 'Editar paciente' : 'Ficha del paciente',
            name: $name,
            status: $status,
            chips: $chips,
            details: array_values(array_filter([
                self::optionalLabel('C.I.', $patient->nro_identificacion),
                $age,
                filled($patient->phone) ? (string) $patient->phone : null,
                filled($patient->email) ? (string) $patient->email : null,
                self::optionalLabel('Afiliación', $patient->code_affiliation),
            ])),
        );
    }

    /**
     * @param  list<array{label: string, bg: string, shadow: string}>  $chips
     * @param  list<string>  $details
     */
    private static function render(
        string $heading,
        string $name,
        string $status,
        array $chips,
        array $details,
    ): HtmlString {
        $statusStyle = self::badgeStyleForStatus($status);

        $chipsHtml = '';
        foreach ($chips as $chip) {
            $chipsHtml .= '<span style="background-color: '.$chip['bg'].';color:#fff;padding:5px 14px;border-radius:999px;font-size:.78rem;font-weight:700;box-shadow:'.$chip['shadow'].';">'
                .e($chip['label'])
                .'</span>';
        }

        $detailsHtml = '';
        foreach ($details as $detail) {
            $detailsHtml .= '<span class="text-sm text-gray-600 dark:text-gray-300">'.e($detail).'</span>';
        }

        return new HtmlString(
            '<div style="display:flex;flex-direction:column;gap:8px;padding:10px 0;">'
            .'<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white">'
            .e($heading)
            .'</span>'
            .'<span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">'
            .e($name)
            .'</span>'
            .'<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">'
            .'<span style="background-color: '.$statusStyle['bg'].';color:#fff;padding:5px 14px;border-radius:999px;font-size:.78rem;font-weight:700;box-shadow:'.$statusStyle['shadow'].';">'
            .e($status)
            .'</span>'
            .$chipsHtml
            .'</div>'
            .'<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">'
            .$detailsHtml
            .'</div>'
            .'</div>'
        );
    }

    private static function displayOrFallback(mixed $value, string $fallback): string
    {
        $display = trim((string) ($value ?? ''));

        return $display !== '' ? $display : $fallback;
    }

    private static function optionalLabel(string $label, mixed $value): ?string
    {
        $display = trim((string) ($value ?? ''));

        if ($display === '') {
            return null;
        }

        return $label.': '.$display;
    }

    /**
     * @return array{bg: string, shadow: string}
     */
    private static function badgeStyleForStatus(string $status): array
    {
        return match ($status) {
            'ACTIVO', 'ACTIVA' => ['bg' => '#16a34a', 'shadow' => '0 8px 20px rgba(22,163,74,.35)'],
            'SUSPENDIDO', 'SUSPENDIDA', 'PENDIENTE' => ['bg' => '#d97706', 'shadow' => '0 8px 20px rgba(217,119,6,.35)'],
            'INACTIVO', 'INACTIVA', 'CANCELADO', 'CANCELADA' => ['bg' => '#dc2626', 'shadow' => '0 8px 20px rgba(220,38,38,.35)'],
            default => ['bg' => '#6b7280', 'shadow' => '0 8px 20px rgba(107,114,128,.35)'],
        };
    }
}
