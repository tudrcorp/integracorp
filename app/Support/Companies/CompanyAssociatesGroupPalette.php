<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Models\CompanyAssociate;

final class CompanyAssociatesGroupPalette
{
    /**
     * Paleta azul (navy → celeste), asignada de forma estable por responsable.
     *
     * @var list<array{tone: string, hex: string}>
     */
    public const PALETTE = [
        ['tone' => 'tone-1', 'hex' => '#03045E'],
        ['tone' => 'tone-2', 'hex' => '#023E8A'],
        ['tone' => 'tone-3', 'hex' => '#0077B6'],
        ['tone' => 'tone-4', 'hex' => '#0096C7'],
        ['tone' => 'tone-5', 'hex' => '#00B4D8'],
        ['tone' => 'tone-6', 'hex' => '#48CAE4'],
        ['tone' => 'tone-7', 'hex' => '#90E0EF'],
        ['tone' => 'tone-8', 'hex' => '#ADE8F4'],
    ];

    /**
     * @return array{tone: string, hex: string}
     */
    public static function forResponsibleId(?int $responsibleId): array
    {
        if ($responsibleId === null || $responsibleId <= 0) {
            return [
                'tone' => 'tone-neutral',
                'hex' => '#64748B',
            ];
        }

        return self::PALETTE[$responsibleId % count(self::PALETTE)];
    }

    /**
     * @return list<string>
     */
    public static function recordRowClasses(CompanyAssociate $record): array
    {
        $palette = self::forResponsibleId($record->company_responsible_id);

        return [
            'associate-group-row',
            'associate-group-row--'.$palette['tone'],
        ];
    }

    public static function groupTitleLabel(CompanyAssociate $record): string
    {
        return (string) ($record->responsible?->full_name ?? 'Sin responsable');
    }

    public static function groupDescriptionLabel(CompanyAssociate $record): string
    {
        $parts = array_filter([
            filled($record->responsible?->identity_card)
                ? 'Cédula: '.$record->responsible->identity_card
                : null,
            filled($record->company?->name)
                ? (string) $record->company->name
                : null,
            filled($record->responsible?->contracted_days)
                ? number_format((int) $record->responsible->contracted_days, 0, ',', '.').' días contratados'
                : null,
        ]);

        return implode(' · ', $parts) ?: 'Grupo de asociados';
    }
}
