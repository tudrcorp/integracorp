<?php

declare(strict_types=1);

namespace App\Support\DocumentEmails;

use App\Enums\DocumentEmailAudience;
use App\Enums\DocumentEmailType;
use App\Models\DocumentEmailTemplate;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Piezas comunes del envío de un documento con copys elegidos por el analista: qué copys ofrecer a
 * cada destinatario, validar el que llega del navegador y frenar el mismo envío repetido.
 */
final class DocumentEmailCopies
{
    /**
     * Ventana en la que un mismo envío repetido (doble clic, reintento del navegador) se rechaza.
     */
    public const DUPLICATE_WINDOW_SECONDS = 20;

    /**
     * Copys usables por destinatario, ya renderizados con los datos del documento.
     *
     * Una sola consulta para ambos destinatarios. Si el destinatario no tiene correo no se
     * preselecciona nada, para que el analista no confirme un envío que va a fallar.
     *
     * @param  array<string, string>  $values
     * @param  array{affiliate: list<string>, responsible: list<string>}  $emails
     * @return array{
     *     affiliate: array{label: string, emails: list<string>, default_id: ?int, templates: list<array{id: int, name: string, subject: string, body_html: string}>},
     *     responsible: array{label: string, emails: list<string>, default_id: ?int, templates: list<array{id: int, name: string, subject: string, body_html: string}>}
     * }
     */
    public static function options(DocumentEmailType $document, array $values, array $emails): array
    {
        $templates = DocumentEmailTemplate::query()->forDocument($document)->active()->ordered()->get();

        $build = function (DocumentEmailAudience $recipient, string $label, array $recipientEmails) use ($templates, $values, $document): array {
            $usable = $templates->filter(fn (DocumentEmailTemplate $template): bool => $template->servesRecipient($document, $recipient));

            $default = $usable->first(fn (DocumentEmailTemplate $template): bool => $template->is_default && $template->audience === $recipient)
                ?? $usable->first(fn (DocumentEmailTemplate $template): bool => $template->is_default);

            return [
                'label' => $label,
                'emails' => $recipientEmails,
                'default_id' => $recipientEmails === [] ? null : $default?->id,
                'templates' => $usable->map(fn (DocumentEmailTemplate $template): array => [
                    'id' => $template->id,
                    'name' => $template->name,
                    ...DocumentEmailTemplateRenderer::render($template, $values),
                ])->values()->all(),
            ];
        };

        return [
            'affiliate' => $build(DocumentEmailAudience::Affiliate, 'Afiliado', $emails['affiliate']),
            'responsible' => $build(DocumentEmailAudience::Responsible, 'Agente / agencia', $emails['responsible']),
        ];
    }

    /**
     * @throws RuntimeException si el copy no existe, está inactivo, es de otro documento o destinatario, o no tiene texto
     */
    public static function usableTemplate(DocumentEmailType $document, int $templateId, DocumentEmailAudience $recipient): DocumentEmailTemplate
    {
        $template = DocumentEmailTemplate::query()->find($templateId);

        if (! $template instanceof DocumentEmailTemplate || ! $template->servesRecipient($document, $recipient)) {
            $who = $recipient === DocumentEmailAudience::Affiliate ? 'el afiliado' : 'el agente / agencia';

            throw new RuntimeException("El copy elegido para {$who} ya no está disponible. Cierre la ventana y vuelva a abrirla para ver los copys vigentes.");
        }

        if (DocumentEmailTemplateRenderer::plainText((string) $template->body) === '') {
            throw new RuntimeException("El copy «{$template->name}» no tiene texto. Complételo antes de usarlo.");
        }

        return $template;
    }

    /**
     * Marca el envío para rechazar el mismo envío repetido en segundos. Devuelve la clave para
     * liberarla si el encolado falla.
     *
     * @param  array<int, mixed>  $fingerprint
     *
     * @throws RuntimeException si el mismo envío se acaba de hacer
     */
    public static function guardAgainstDuplicate(DocumentEmailType $document, int|string|null $recordKey, array $fingerprint): string
    {
        $key = 'document-email:'.$document->value.':'.$recordKey.':'.sha1(json_encode($fingerprint) ?: '');

        if (! Cache::add($key, true, self::DUPLICATE_WINDOW_SECONDS)) {
            throw new RuntimeException('Este documento se acaba de enviar con los mismos copys. Espere unos segundos antes de repetir el envío.');
        }

        return $key;
    }

    public static function releaseDuplicateGuard(string $key): void
    {
        Cache::forget($key);
    }

    /**
     * Aviso que encabeza un correo de prueba: a quién llegaría en el envío real. Así el analista
     * valida el contenido y también los destinatarios antes de enviar de verdad.
     *
     * @param  list<string>  $realTo
     * @param  array{cc: list<string>, bcc: list<string>, phones: list<string>}  $witnesses
     */
    public static function testNoticeHtml(DocumentEmailAudience $recipient, array $realTo, array $witnesses): string
    {
        $row = static fn (string $label, array $values, string $empty): string => '<tr>'
            .'<td style="padding:2px 10px 2px 0;color:#92400e;white-space:nowrap;vertical-align:top;">'.e($label).'</td>'
            .'<td style="padding:2px 0;color:#78350f;font-weight:600;">'.e($values === [] ? $empty : implode(', ', $values)).'</td>'
            .'</tr>';

        return '<div style="margin:0 0 18px 0;padding:12px 14px;border:1px solid #fcd34d;border-radius:12px;background:#fffbeb;font-size:13px;line-height:1.5;">'
            .'<p style="margin:0 0 6px 0;color:#92400e;font-weight:700;">Correo de prueba · así llegará a «'.e($recipient->label()).'»</p>'
            .'<table role="presentation" cellpadding="0" cellspacing="0" border="0">'
            .$row('Para:', $realTo, 'sin correo válido: no se le enviaría')
            .$row('Copia visible (CC):', $witnesses['cc'], 'ninguna')
            .$row('Copia oculta (CCO):', $witnesses['bcc'], 'ninguna')
            .$row('WhatsApp testigo:', $witnesses['phones'], 'ninguno')
            .'</table>'
            .'<p style="margin:6px 0 0 0;color:#92400e;">Este aviso no aparece en el envío real.</p>'
            .'</div>';
    }
}
