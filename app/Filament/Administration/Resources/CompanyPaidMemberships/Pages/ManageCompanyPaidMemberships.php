<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\CompanyPaidMemberships\Pages;

use App\Filament\Administration\Resources\CompanyPaidMemberships\CompanyPaidMembershipResource;
use Filament\Resources\Pages\ManageRecords;

class ManageCompanyPaidMemberships extends ManageRecords
{
    protected static string $resource = CompanyPaidMembershipResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
