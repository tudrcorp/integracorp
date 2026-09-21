<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\Agency;
use App\Models\Agent;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

final class CommercialStructurePageHeader
{
    public static function forAgency(Agency $agency, string $context = 'view'): string|Htmlable
    {
        if (! $agency->relationLoaded('typeAgency') && $agency->exists) {
            $agency->loadMissing('typeAgency');
        }

        $code = self::displayOrFallback($agency->code, 'Sin código');
        $name = self::displayOrFallback($agency->name_corporative, 'Sin razón social');
        $status = strtoupper(self::displayOrFallback($agency->status, 'SIN ESTADO'));
        $type = self::displayOrFallback($agency->typeAgency?->definition, 'Sin tipo');
        $email = self::displayOrFallback($agency->email, 'Sin correo');
        $phone = self::displayOrFallback($agency->phone, 'Sin teléfono');
        $rif = self::optionalLabel('RIF', $agency->rif);

        $heading = $context === 'edit'
            ? 'Editar agencia · '.$code
            : 'Agencia · '.$code;

        return self::render(
            heading: $heading,
            name: $name,
            status: $status,
            chips: self::metaChips($type, self::isReferidorFlag($agency->is_referidor)),
            details: array_values(array_filter([
                '📧 '.$email,
                '📞 '.$phone,
                $rif,
            ])),
        );
    }

    public static function forAgent(Agent $agent, string $context = 'view'): string|Htmlable
    {
        if (! $agent->relationLoaded('typeAgent') && $agent->exists) {
            $agent->loadMissing('typeAgent');
        }

        $code = self::displayOrFallback($agent->code_agent, 'Sin código');
        $name = self::displayOrFallback($agent->name, 'Sin nombre');
        $status = strtoupper(self::displayOrFallback($agent->status, 'SIN ESTADO'));
        $type = self::displayOrFallback($agent->typeAgent?->definition, 'Sin tipo');
        $email = self::displayOrFallback($agent->email, 'Sin correo');
        $phone = self::displayOrFallback($agent->phone, 'Sin teléfono');
        $document = self::agentDocumentLabel($agent);
        $agencyCode = self::optionalLabel(
            'Agencia',
            $agent->owner_code ?: $agent->code_agency,
        );

        $heading = $context === 'edit'
            ? 'Editar agente · '.$code
            : 'Agente · '.$code;

        return self::render(
            heading: $heading,
            name: $name,
            status: $status,
            chips: self::metaChips($type, self::isReferidorFlag($agent->is_referidor)),
            details: array_values(array_filter([
                '📧 '.$email,
                '📞 '.$phone,
                $document,
                $agencyCode,
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

    /**
     * @return list<array{label: string, bg: string, shadow: string}>
     */
    private static function metaChips(string $type, bool $isReferidor): array
    {
        $chips = [
            [
                'label' => $type,
                'bg' => '#2563eb',
                'shadow' => '0 8px 20px rgba(37,99,235,.35)',
            ],
        ];

        if ($isReferidor) {
            $chips[] = [
                'label' => 'Referidor',
                'bg' => '#7c3aed',
                'shadow' => '0 8px 20px rgba(124,58,237,.35)',
            ];
        }

        return $chips;
    }

    private static function isReferidorFlag(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) === true;
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

    private static function agentDocumentLabel(Agent $agent): ?string
    {
        $ci = trim((string) ($agent->ci ?? ''));
        if ($ci !== '') {
            return 'C.I.: '.$ci;
        }

        $rif = trim((string) ($agent->rif ?? ''));
        if ($rif !== '') {
            return 'RIF: '.$rif;
        }

        return null;
    }

    /**
     * @return array{bg: string, shadow: string}
     */
    private static function badgeStyleForStatus(string $status): array
    {
        return match ($status) {
            'ACTIVO' => ['bg' => '#16a34a', 'shadow' => '0 8px 20px rgba(22,163,74,.35)'],
            'POR REVISION' => ['bg' => '#f59e0b', 'shadow' => '0 8px 20px rgba(245,158,11,.35)'],
            'INACTIVO' => ['bg' => '#dc2626', 'shadow' => '0 8px 20px rgba(220,38,38,.35)'],
            default => ['bg' => '#6b7280', 'shadow' => '0 8px 20px rgba(107,114,128,.35)'],
        };
    }
}
