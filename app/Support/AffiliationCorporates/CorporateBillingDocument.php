<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Models\AffiliationCorporate;

/**
 * Documento que identifica a la empresa en sus ventas y cuotas (`affiliate_ci_rif`).
 *
 * Es el RIF de la afiliación corporativa. Hasta el 03/11/2026 el pago corporativo copiaba la
 * cédula de la persona de contacto, y las cuotas y reportes salían con un documento que no es
 * el de la empresa. La cédula del contacto queda solo como respaldo si no hay RIF cargado.
 * La factura «a nombre del tomador» sigue usando al contacto a propósito
 * ({@see \App\Services\SaleInvoicePdfService::resolveBillingParty()}).
 */
final class CorporateBillingDocument
{
    public static function for(?AffiliationCorporate $affiliation): ?string
    {
        if (! $affiliation instanceof AffiliationCorporate) {
            return null;
        }

        foreach ([$affiliation->rif, $affiliation->nro_identificacion_contact] as $value) {
            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
