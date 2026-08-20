<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\WhiteCompanies\Pages;

use App\Filament\Administration\Resources\WhiteCompanies\WhiteCompanyResource;
use App\Filament\Business\Resources\WhiteCompanies\Pages\CreateWhiteCompany as BusinessCreateWhiteCompany;

/**
 * Hereda el alta de Negocios: documentos de marca, usuario asociado y auditoría
 * quedan idénticos. Solo cambia el recurso al que pertenece la página.
 */
class CreateWhiteCompany extends BusinessCreateWhiteCompany
{
    protected static string $resource = WhiteCompanyResource::class;
}
