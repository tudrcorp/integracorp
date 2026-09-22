<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Models\AffiliationCorporate;
use App\Models\Collection as BillingCollection;
use App\Models\PaidMembershipCorporate;

/**
 * Decide si una afiliación corporativa admite cargar otro comprobante de pago.
 *
 * Antes se contaban comprobantes contra un número fijo por frecuencia (1, 2 o 4),
 * lo que dejaba fuera a Mensual, rompía al cambiar de frecuencia a mitad de año
 * y ocultaba el botón para siempre tras la renovación. La regla ahora sale de la
 * cobranza real: se puede cargar mientras no haya pagos registrados o queden
 * avisos de cobro pendientes.
 */
final class CorporatePaymentUploadAvailability
{
    /**
     * Comprobantes que ya ocupan un pago: aprobados o esperando aprobación.
     *
     * @var list<string>
     */
    public const REGISTERED_PROOF_STATUSES = ['APROBADO', 'PENDIENTE'];

    public static function isFullyPaid(AffiliationCorporate $affiliation): bool
    {
        $hasRegisteredProof = PaidMembershipCorporate::query()
            ->where('affiliation_corporate_id', $affiliation->getKey())
            ->whereIn('status', self::REGISTERED_PROOF_STATUSES)
            ->exists();

        if (! $hasRegisteredProof) {
            return false;
        }

        if (blank($affiliation->code)) {
            return true;
        }

        return ! BillingCollection::query()
            ->where('affiliation_code', $affiliation->code)
            ->where('status', CorporateAffiliateUpgradeManager::PENDING_COLLECTION_STATUS)
            ->exists();
    }
}
