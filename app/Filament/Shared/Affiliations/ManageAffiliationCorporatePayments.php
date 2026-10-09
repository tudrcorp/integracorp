<?php

declare(strict_types=1);

namespace App\Filament\Shared\Affiliations;

use App\Models\AffiliationCorporate;
use App\Support\Filament\RecordPageHeader;
use Illuminate\Database\Eloquent\Model;

/**
 * Pagos realizados de una afiliación corporativa: misma página que la
 * individual sobre `paid_membership_corporates`, con la empresa en el encabezado.
 */
abstract class ManageAffiliationCorporatePayments extends ManageAffiliationPayments
{
    protected static string $relationship = 'paid_membership_corporates';

    /**
     * @return array{title: string, chips: list<array{label: string, tone: string}|null>, facts: array<string, string|null>}
     */
    protected static function ownerHeader(Model $owner): array
    {
        /** @var AffiliationCorporate $owner */
        return [
            'title' => (string) ($owner->name_corporate ?: 'Sin razón social'),
            'chips' => [
                filled($owner->payment_frequency) ? RecordPageHeader::tag('Pago '.mb_strtolower((string) $owner->payment_frequency), RecordPageHeader::TONE_NEUTRAL) : null,
                filled($owner->white_company_id) ? RecordPageHeader::tag('Empresa aliada', RecordPageHeader::TONE_VIOLET) : null,
            ],
            'facts' => [
                'RIF' => $owner->rif,
            ],
        ];
    }
}
