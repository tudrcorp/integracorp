<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\SystemOtpCodes\Pages;

use App\Filament\Business\Resources\SystemOtpCodes\SystemOtpCodeResource;
use App\Models\SystemOtpCode;
use Filament\Resources\Pages\ListRecords;

class ListSystemOtpCodes extends ListRecords
{
    protected static string $resource = SystemOtpCodeResource::class;

    public function getSubheading(): ?string
    {
        return 'Claves de las últimas '.SystemOtpCode::RETENTION_HOURS.' horas. Úselas solo para dictárselas a quien la pidió cuando no le llegó por WhatsApp o correo. Cada vez que se muestra una clave queda registrado en la auditoría.';
    }
}
