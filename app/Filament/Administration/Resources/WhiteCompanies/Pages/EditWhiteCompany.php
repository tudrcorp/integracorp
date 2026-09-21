<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\WhiteCompanies\Pages;

use App\Filament\Administration\Resources\WhiteCompanies\WhiteCompanyResource;
use App\Filament\Business\Resources\WhiteCompanies\Pages\EditWhiteCompany as BusinessEditWhiteCompany;

/**
 * Hereda la edición de Negocios para no duplicar la sincronización de documentos
 * de marca ni la auditoría.
 */
class EditWhiteCompany extends BusinessEditWhiteCompany
{
    protected static string $resource = WhiteCompanyResource::class;
}
