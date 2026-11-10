<?php

declare(strict_types=1);

namespace App\Filament\Shared\DocumentEmailTemplates;

use App\Enums\DocumentEmailType;
use App\Filament\Administration\Resources\CollectionNoticeEmailTemplates\CollectionNoticeEmailTemplateResource;
use App\Filament\Administration\Resources\SaleReceiptEmailTemplates\SaleReceiptEmailTemplateResource;
use App\Models\DocumentEmailTemplate;
use App\Support\DocumentEmails\DocumentEmailTemplateAccess;
use Filament\Resources\Resource;

/**
 * Dónde vive cada copy según el documento elegido en «Se usará en», y si el usuario puede
 * dejarlo ahí. El formulario solo ofrece documentos permitidos; esto lo repite en el servidor.
 */
final class DocumentEmailTemplateDestinations
{
    /**
     * @return class-string<resource>
     */
    public static function resourceFor(DocumentEmailType $document): string
    {
        return match ($document) {
            DocumentEmailType::ReceiptPayment => SaleReceiptEmailTemplateResource::class,
            DocumentEmailType::CollectionNotice => CollectionNoticeEmailTemplateResource::class,
        };
    }

    /**
     * Documento a guardar, o null si el usuario no puede gestionar copys de ese documento
     * (al editar, también debe poder gestionar el documento actual del copy).
     *
     * @param  array<string, mixed>  $data
     */
    public static function allowedDocument(array $data, DocumentEmailType $fallback, ?DocumentEmailTemplate $record = null): ?DocumentEmailType
    {
        $raw = $data['document'] ?? $fallback->value;
        $document = $raw instanceof DocumentEmailType ? $raw : DocumentEmailType::tryFrom((string) $raw);

        if ($document === null || ! DocumentEmailTemplateAccess::canManage($document)) {
            return null;
        }

        if ($record !== null && ! DocumentEmailTemplateAccess::canManage($record->document)) {
            return null;
        }

        return $document;
    }

    /**
     * Listado donde quedó el copy; si el usuario no entra a esa pantalla, el de la pantalla actual.
     */
    public static function indexUrlFor(DocumentEmailType $document, string $currentIndexUrl): string
    {
        $resource = self::resourceFor($document);

        return $resource::canAccess() ? $resource::getUrl('index') : $currentIndexUrl;
    }

    public static function savedMessage(DocumentEmailType $document, DocumentEmailType $screen): ?string
    {
        if ($document === $screen) {
            return null;
        }

        return 'El copy quedó en «'.self::resourceFor($document)::getNavigationLabel().'», porque se usará en el envío de '
            .($document === DocumentEmailType::ReceiptPayment ? 'recibos de pago' : 'avisos de cobro').'.';
    }
}
