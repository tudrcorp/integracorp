<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\WhiteCompanies\Pages;

use App\Filament\Administration\Resources\WhiteCompanies\WhiteCompanyResource;
use App\Filament\Business\Resources\WhiteCompanies\Pages\EditWhiteCompany as BusinessEditWhiteCompany;
use App\Models\WhiteCompany;
use App\Support\Filament\RecordPageHeader;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Hereda la edición de Negocios para no duplicar la sincronización de documentos
 * de marca ni la auditoría.
 */
class EditWhiteCompany extends BusinessEditWhiteCompany
{
    protected static string $resource = WhiteCompanyResource::class;

    public function getHeading(): string|Htmlable
    {
        /** @var WhiteCompany $company */
        $company = $this->getRecord();

        $assignedCredit = (float) ($company->assigned_credit ?? 0);
        $remainingCredit = $assignedCredit > 0 ? $company->remainingAssignedCredit() : null;

        return RecordPageHeader::render(
            eyebrow: 'Editar empresa aliada',
            title: (string) ($company->name ?: 'Sin nombre'),
            chips: [
                $remainingCredit !== null && $remainingCredit <= 0
                    ? RecordPageHeader::tag('Crédito agotado', RecordPageHeader::TONE_DANGER)
                    : null,
            ],
            facts: [
                'RIF' => $company->rif,
                'Correo' => $company->email,
                'Teléfono' => $company->phone,
                'Crédito asignado' => $assignedCredit > 0 ? RecordPageHeader::money($assignedCredit) : null,
                'Crédito disponible' => $remainingCredit !== null ? RecordPageHeader::money($remainingCredit) : null,
            ],
            imageUrl: $company->logoAbsolutePath() !== null ? asset('storage/'.ltrim((string) $company->logo, '/')) : null,
        );
    }
}
