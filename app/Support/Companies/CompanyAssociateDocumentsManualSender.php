<?php

declare(strict_types=1);

namespace App\Support\Companies;

use App\Models\CompanyAssociate;
use App\Support\SecurityAudit;
use RuntimeException;

final class CompanyAssociateDocumentsManualSender
{
    public static function canDeliver(CompanyAssociate $associate): bool
    {
        $associate->loadMissing('responsible');

        return filled($associate->email)
            || filled($associate->phone)
            || filled($associate->responsible?->email)
            || filled($associate->responsible?->phone);
    }

    public static function send(CompanyAssociate $associate): void
    {
        if (! self::canDeliver($associate)) {
            throw new RuntimeException('El asociado no tiene correo ni teléfono, y su responsable tampoco tiene datos de contacto.');
        }

        CompanyAssociateInclusionQrGenerator::ensurePublished();

        if (! CompanyAssociateInclusionQrCatalog::qrExists()) {
            throw new RuntimeException('El código QR de inclusión no está disponible. Genérelo desde el módulo de nuevos negocios.');
        }

        $associate = $associate->fresh(['company', 'responsible']);

        if ($associate === null) {
            throw new RuntimeException('No se encontró el asociado seleccionado.');
        }

        $carnet = CompanyAssociateCarnetGenerator::generate($associate);

        CompanyAssociateDocumentsDeliverer::deliver(
            $associate->fresh(['company', 'responsible']),
            $carnet,
            sendWhatsAppImmediately: true,
            includeAnalystRecipients: false,
        );

        SecurityAudit::log('AUDIT_BUSINESS_COMPANY_ASSOCIATE_DOCUMENTS_MANUAL_SENT', 'company-associates.manual-documents', [
            'associate_id' => $associate->getKey(),
            'company_id' => $associate->company_id,
            'company_responsible_id' => $associate->company_responsible_id,
            'carnet_filename' => $carnet['filename'],
        ]);
    }
}
