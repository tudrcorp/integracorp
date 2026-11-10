<?php

declare(strict_types=1);

namespace App\Filament\Shared\DocumentEmailTemplates;

use App\Enums\DocumentEmailType;
use App\Models\DocumentEmailTemplate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;

/**
 * Encabezados de las pantallas de copys (ficha y crear): dicen en qué envío se
 * usa el copy y su estado, para que el analista no tenga que adivinarlo por el título.
 */
final class DocumentEmailTemplatePageHeader
{
    /**
     * Encabezado de crear: dónde se usará el copy y qué debe incluir.
     */
    public static function forCreate(DocumentEmailType $document): HtmlString
    {
        return self::render([
            'icon' => self::icon($document),
            'eyebrow' => 'Nuevo copy',
            'title' => 'Se usará en el envío de '.self::documentPlural($document),
            'description' => self::usageDescription($document).' Escriba el saludo, el texto y la firma: el logo y el número del documento se agregan solos.',
            'chips' => [
                ['label' => $document->label(), 'tone' => 'primary'],
                ['label' => 'Puede cambiarlo en «Se usará en»', 'tone' => 'gray'],
            ],
            'meta' => null,
        ]);
    }

    /**
     * Encabezado de la ficha y de editar: estado del copy y su última edición.
     */
    public static function forRecord(DocumentEmailTemplate $template, string $eyebrow): HtmlString
    {
        $chips = [
            ['label' => $template->document->label(), 'tone' => 'primary'],
            ['label' => 'Para: '.$template->audience->label(), 'tone' => 'info'],
            $template->is_active
                ? ['label' => 'Activo', 'tone' => 'success']
                : ['label' => 'Inactivo · no aparece al enviar', 'tone' => 'danger'],
        ];

        if ($template->is_default) {
            $chips[] = ['label' => 'Predeterminado', 'tone' => 'warning'];
        }

        $updatedAt = $template->updated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i');

        return self::render([
            'icon' => self::icon($template->document),
            'eyebrow' => $eyebrow,
            'title' => $template->name,
            'description' => 'Asunto: '.$template->subject,
            'chips' => $chips,
            'meta' => $updatedAt !== null
                ? 'Última edición: '.$updatedAt.(filled($template->updated_by) ? ' · '.$template->updated_by : '')
                : null,
        ]);
    }

    public static function documentPlural(DocumentEmailType $document): string
    {
        return match ($document) {
            DocumentEmailType::ReceiptPayment => 'recibos de pago',
            DocumentEmailType::CollectionNotice => 'avisos de cobro',
        };
    }

    private static function usageDescription(DocumentEmailType $document): string
    {
        return match ($document) {
            DocumentEmailType::ReceiptPayment => 'El analista los elige en Administración → Ventas al enviar el recibo de pago al afiliado y al agente / agencia.',
            DocumentEmailType::CollectionNotice => 'El analista los elige en Gestión de Cobranza y Cobranza Por Mes al enviar el aviso de cobro al afiliado y al agente / agencia.',
        };
    }

    private static function icon(DocumentEmailType $document): string
    {
        return match ($document) {
            DocumentEmailType::ReceiptPayment => 'heroicon-o-receipt-percent',
            DocumentEmailType::CollectionNotice => 'heroicon-o-banknotes',
        };
    }

    /**
     * @param  array{icon: string, eyebrow: string, title: string, description: string, chips: list<array{label: string, tone: string}>, meta: ?string}  $data
     */
    private static function render(array $data): HtmlString
    {
        return new HtmlString(View::make('filament.administration.document-emails.page-header', $data)->render());
    }
}
