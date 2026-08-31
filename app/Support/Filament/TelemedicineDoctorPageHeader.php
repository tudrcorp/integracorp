<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\TelemedicineDoctor;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

final class TelemedicineDoctorPageHeader
{
    public static function forDoctor(TelemedicineDoctor $doctor, string $context = 'edit'): string|Htmlable
    {
        $name = self::displayOrFallback($doctor->full_name, 'Médico sin nombre');
        $status = strtoupper(self::displayOrFallback($doctor->status, 'SIN ESTADO'));
        $specialty = self::displayOrFallback($doctor->specialty, 'Sin especialidad');
        $managedBy = self::displayOrFallback($doctor->managed_by, '');

        $heading = $context === 'profile' ? 'Mi perfil médico' : 'Editar médico';
        $code = trim((string) ($doctor->code ?? ''));
        if ($code !== '') {
            $heading .= ' · '.$code;
        }

        $chips = [
            [
                'label' => $specialty,
                'bg' => '#2563eb',
                'shadow' => '0 8px 20px rgba(37,99,235,.35)',
            ],
        ];

        if ($managedBy !== '') {
            $chips[] = [
                'label' => $managedBy,
                'bg' => '#0f766e',
                'shadow' => '0 8px 20px rgba(15,118,110,.35)',
            ];
        }

        return self::render(
            heading: $heading,
            name: $name,
            status: $status,
            chips: $chips,
            details: array_values(array_filter([
                self::optionalLabel('C.I.', $doctor->nro_identificacion),
                filled($doctor->email) ? (string) $doctor->email : null,
                filled($doctor->phone) ? (string) $doctor->phone : null,
                self::optionalLabel('MPPS', $doctor->code_mpps),
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
            'ACTIVO' => ['bg' => '#16a34a', 'shadow' => '0 8px 20px rgba(22,163,74,.35)'],
            'INACTIVO' => ['bg' => '#dc2626', 'shadow' => '0 8px 20px rgba(220,38,38,.35)'],
            default => ['bg' => '#6b7280', 'shadow' => '0 8px 20px rgba(107,114,128,.35)'],
        };
    }
}
