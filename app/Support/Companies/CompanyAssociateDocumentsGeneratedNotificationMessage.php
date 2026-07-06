<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Models\CompanyAssociate;

final class CompanyAssociateDocumentsGeneratedNotificationMessage
{
    public static function title(): string
    {
        return 'Carnet generado';
    }

    public static function failureTitle(): string
    {
        return 'No se generaron los documentos del asociado';
    }

    public static function toastBody(CompanyAssociate $associate): string
    {
        $name = self::value($associate->full_name);
        $identityCard = self::value($associate->identity_card);
        $voucher = self::value($associate->vaucher_ils);
        $company = self::value($associate->company?->name);

        return 'La tarjeta de '.$name.' ('.$identityCard.') con voucher '.$voucher.' de '.$company.' está lista. Use «Abrir carnet» en el menú de acciones.';
    }

    public static function failureBody(CompanyAssociate $associate, string $error): string
    {
        return 'No se pudieron generar la tarjeta ni el QR de '
            .self::value($associate->full_name).' ('.self::value($associate->identity_card).'). '
            .'Por favor contacte al equipo de sistemas para revisar el caso. '
            .'Detalle técnico: '.$error;
    }

    private static function value(mixed $value): string
    {
        return filled($value) ? (string) $value : '—';
    }
}
