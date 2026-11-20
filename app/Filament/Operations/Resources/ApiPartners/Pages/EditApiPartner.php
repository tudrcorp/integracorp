<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\ApiPartners\Pages;

use App\Filament\Operations\Resources\ApiPartners\ApiPartnerResource;
use App\Support\SecurityAudit;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditApiPartner extends EditRecord
{
    protected static string $resource = ApiPartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        SecurityAudit::log('AUDIT_API_PARTNER_UPDATED', 'operations.api-partners.update', [
            'api_partner_id' => $record->getKey(),
            'api_partner_name' => $record->name,
            'changes' => array_keys($record->getChanges()),
        ]);
    }

    protected function getRedirectUrl(): ?string
    {
        return ApiPartnerResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
