<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Filament\Business\Resources\Companies\CompanyResource;
use App\Filament\Business\Resources\CompanyAssociates\CompanyAssociateResource;
use App\Models\CompanyAssociate;
use App\Models\CompanyResponsible;

final class CompanyAssociatesTableContext
{
    public const GROUPING_RESPONSIBLE = 'company_responsible_id';

    public static function forResponsible(CompanyResponsible $responsible): string
    {
        $query = http_build_query([
            'filters' => [
                'company_responsible_id' => [
                    'value' => (string) $responsible->getKey(),
                ],
                'company_id' => [
                    'value' => (string) $responsible->company_id,
                ],
            ],
            'grouping' => self::GROUPING_RESPONSIBLE,
            'contextCompany' => (string) $responsible->company_id,
            'contextResponsible' => (string) $responsible->getKey(),
        ]);

        return self::indexUrl().'?'.$query;
    }

    public static function indexUrl(): string
    {
        return CompanyAssociateResource::getUrl('index', panel: 'business');
    }

    public static function associateViewUrl(int|CompanyAssociate $associate): string
    {
        return CompanyAssociateResource::getUrl('view', ['record' => $associate], panel: 'business');
    }

    public static function companyViewUrl(int $companyId): string
    {
        return CompanyResource::getUrl('view', ['record' => $companyId], panel: 'business');
    }
}
