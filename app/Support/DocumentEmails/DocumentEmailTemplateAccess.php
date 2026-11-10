<?php

declare(strict_types=1);

namespace App\Support\DocumentEmails;

use App\Enums\DocumentEmailType;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;

/**
 * Quién crea, edita, activa o desactiva los copys de cada documento. Cada documento tiene su
 * propio permiso de acción, asignable por separado en el editor de permisos.
 */
final class DocumentEmailTemplateAccess
{
    public static function canManage(DocumentEmailType $document): bool
    {
        return BusinessFilamentActionAccess::userCan(self::manageSlug($document));
    }

    public static function manageSlug(DocumentEmailType $document): string
    {
        return match ($document) {
            DocumentEmailType::ReceiptPayment => BusinessFilamentActionPermissionRegistry::MANAGE_SALE_RECEIPT_EMAIL_TEMPLATES,
            DocumentEmailType::CollectionNotice => BusinessFilamentActionPermissionRegistry::MANAGE_COLLECTION_NOTICE_EMAIL_TEMPLATES,
        };
    }
}
