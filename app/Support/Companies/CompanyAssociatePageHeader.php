<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Enums\CompanyAssociateStatus;
use App\Models\CompanyAssociate;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

final class CompanyAssociatePageHeader
{
    public static function make(CompanyAssociate $associate, string $contextLabel = 'Nuevos Negocios'): Htmlable
    {
        $associate->loadMissing(['company', 'responsible']);

        $fullName = (string) ($associate->full_name ?: 'Sin nombre');
        $identityCard = (string) ($associate->identity_card ?: '—');
        $companyName = (string) ($associate->company?->name ?: 'Sin empresa');
        $responsibleName = (string) ($associate->responsible?->full_name ?: 'Sin responsable');
        $status = CompanyAssociateStatusManager::resolveStatus($associate)
            ?? CompanyAssociateStatus::ActivoSinVaucherIls;
        $badge = self::badgeStyleForStatus($status);

        $meta = [
            'Cédula: '.$identityCard,
            'Empresa: '.$companyName,
            'Responsable: '.$responsibleName,
        ];

        $annulmentBlock = '';

        if ($status === CompanyAssociateStatus::Anulado) {
            $reason = filled($associate->annulment_reason)
                ? (string) $associate->annulment_reason
                : 'Sin razón registrada';
            $annulledAt = $associate->annulled_at?->format('d/m/Y H:i:s');

            $annulmentBlock = '<div style="margin-top:10px;padding:12px 14px;border-radius:14px;border:1px solid rgba(220,38,38,.28);background:linear-gradient(180deg,rgba(254,242,242,.95),rgba(254,226,226,.75));box-shadow:0 10px 24px -16px rgba(220,38,38,.45);">'
                .'<div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:6px;">'
                .'<span style="font-size:.72rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#b91c1c;">Razón de anulación</span>'
                .($annulledAt !== null
                    ? '<span style="font-size:.75rem;font-weight:600;color:#9f1239;">Anulado el '.e($annulledAt).'</span>'
                    : '')
                .'</div>'
                .'<p style="margin:0;font-size:.9rem;line-height:1.45;font-weight:600;color:#7f1d1d;">'.e($reason).'</p>'
                .'</div>';
        }

        return new HtmlString(
            '<div style="display:flex;flex-direction:column;gap:8px;padding:10px 0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;">'
            .'<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white">'
            .'Ficha del asociado — '.e($contextLabel)
            .'</span>'
            .'<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">'
            .'<span class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">'
            .e($fullName)
            .'</span>'
            .'<span style="background-color:'.$badge['bg'].';color:#fff;padding:6px 16px;border-radius:999px;font-size:.8rem;font-weight:700;display:inline-flex;align-items:center;gap:6px;box-shadow:'.$badge['shadow'].';border:1px solid rgba(255,255,255,.2);">'
            .'<span style="font-size:10px;">●</span> '.e(mb_strtoupper($status->label()))
            .'</span>'
            .'</div>'
            .'<div style="display:flex;flex-direction:column;gap:4px;">'
            .collect($meta)
                ->map(fn (string $line): string => '<span class="text-sm text-gray-600 dark:text-gray-300">'.e($line).'</span>')
                ->implode('')
            .'</div>'
            .$annulmentBlock
            .'</div>'
        );
    }

    /**
     * @return array{bg: string, shadow: string}
     */
    private static function badgeStyleForStatus(CompanyAssociateStatus $status): array
    {
        return match ($status) {
            CompanyAssociateStatus::Activo => [
                'bg' => '#16a34a',
                'shadow' => '0 8px 20px rgba(22,163,74,.35)',
            ],
            CompanyAssociateStatus::ActivoSinVaucherIls => [
                'bg' => '#d97706',
                'shadow' => '0 8px 20px rgba(217,119,6,.35)',
            ],
            CompanyAssociateStatus::Anulado => [
                'bg' => '#dc2626',
                'shadow' => '0 8px 20px rgba(220,38,38,.35)',
            ],
        };
    }
}
