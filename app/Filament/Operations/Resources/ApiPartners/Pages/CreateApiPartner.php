<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\ApiPartners\Pages;

use App\Filament\Operations\Resources\ApiPartners\ApiPartnerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateApiPartner extends CreateRecord
{
    protected static string $resource = ApiPartnerResource::class;

    protected static ?string $title = 'Nuevo aliado con acceso API';

    /**
     * A la ficha: ahí se genera el token, que es el paso siguiente.
     */
    protected function getRedirectUrl(): string
    {
        return ApiPartnerResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Aliado creado. Ahora genera su token con «Generar token».';
    }
}
