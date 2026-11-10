<?php

declare(strict_types=1);

namespace App\Support\Sales;

use App\Enums\DocumentEmailAudience;
use App\Enums\DocumentEmailType;
use App\Enums\SystemNotificationKey;
use App\Filament\Administration\Resources\Sales\Tables\SalesTable;
use App\Mail\MailSaleReciboPago;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Sale;
use App\Support\DocumentEmails\DocumentEmailCopies;
use App\Support\DocumentEmails\DocumentEmailTemplateRenderer;
use App\Support\DocumentEmails\DocumentEmailWitnesses;
use App\Support\Filament\Administration\SaleReciboPagoEmailRecipients;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Envío del recibo de pago con el copy que el analista elige para cada destinatario.
 *
 * Dos correos independientes, cada uno con su copy y el PDF adjunto:
 * - al afiliado (correo de la afiliación individual o de la empresa);
 * - al responsable: agente y agencia juntos, como siempre.
 * Los testigos (Centro de notificaciones → «Testigos de recibos de pago») van en CC o CCO según
 * cada correo, y sus teléfonos reciben por WhatsApp el PDF con el detalle del envío.
 * Cualquiera de los dos destinatarios puede omitirse, pero no los dos.
 *
 * Se valida todo antes de encolar: si falta el correo de un destinatario elegido o un copy ya
 * no está disponible, no sale ninguno. Así nunca queda un envío a medias.
 */
final class SaleReceiptEmailDelivery
{
    private const DOCUMENT = DocumentEmailType::ReceiptPayment;

    /**
     * Opciones para la pantalla: copys usables por destinatario, ya renderizados con los datos
     * de la venta, el predeterminado y los correos que recibirían el envío.
     *
     * @return array{
     *     affiliate: array{label: string, emails: list<string>, default_id: ?int, templates: list<array{id: int, name: string, subject: string, body_html: string}>},
     *     responsible: array{label: string, emails: list<string>, default_id: ?int, templates: list<array{id: int, name: string, subject: string, body_html: string}>},
     *     cc_emails: list<string>,
     *     bcc_emails: list<string>,
     *     witness_phones: list<string>
     * }
     */
    public static function options(Sale $sale): array
    {
        $recipients = SaleReciboPagoEmailRecipients::resolve($sale);

        return [
            ...DocumentEmailCopies::options(self::DOCUMENT, self::valuesForSale($sale), [
                'affiliate' => array_values(array_filter([$recipients['affiliate']['email']])),
                'responsible' => $recipients['emails'],
            ]),
            'cc_emails' => $recipients['cc_emails'],
            'bcc_emails' => $recipients['bcc_emails'],
            'witness_phones' => DocumentEmailWitnesses::for(SystemNotificationKey::SaleReceiptWitnessCopy)['phones'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function valuesForSale(Sale $sale): array
    {
        $agent = $sale->relationLoaded('agent')
            ? $sale->agent
            : (filled($sale->agent_id) ? Agent::query()->find($sale->agent_id, ['id', 'name']) : null);

        $agency = $sale->relationLoaded('agency')
            ? $sale->agency
            : (filled($sale->code_agency) ? Agency::query()->where('code', $sale->code_agency)->first(['id', 'code', 'name_corporative']) : null);

        $plan = $sale->relationLoaded('plan')
            ? $sale->plan?->description
            : (filled($sale->plan_id) ? $sale->plan()->value('description') : null);

        return [
            'afiliado' => trim((string) $sale->affiliate_full_name),
            'nro_recibo' => trim((string) $sale->invoice_number),
            'monto' => self::usd($sale->total_amount),
            'fecha_emision' => $sale->created_at?->format('d/m/Y') ?? '',
            'plan' => trim((string) $plan),
            'metodo_pago' => trim((string) $sale->payment_method),
            'agente' => trim((string) $agent?->name),
            'agencia' => trim((string) $agency?->name_corporative),
        ];
    }

    /**
     * @return list<array{recipient: string, to: list<string>, cc: list<string>, bcc: list<string>, template_id: int, template_name: string, subject: string}>
     *
     * @throws RuntimeException con un mensaje en español listo para mostrar al analista
     */
    public static function deliver(
        Sale $sale,
        ?int $affiliateTemplateId,
        ?int $responsibleTemplateId,
        bool $testMode = false,
        ?string $testEmail = null,
    ): array {
        $pdfPath = SalesTable::reciboPagoPdfPath($sale);

        if (! is_file($pdfPath)) {
            throw new RuntimeException('No existe el PDF del recibo. Genere el recibo antes de enviar el correo.');
        }

        if ($affiliateTemplateId === null && $responsibleTemplateId === null) {
            throw new RuntimeException('Elija el copy de al menos un destinatario: el afiliado o el agente / agencia.');
        }

        $normalizedTestEmail = null;

        if ($testMode) {
            $normalizedTestEmail = SaleReciboPagoEmailRecipients::normalizeEmail($testEmail);

            if ($normalizedTestEmail === null) {
                throw new RuntimeException('Ingrese un correo de prueba válido.');
            }
        }

        $recipients = SaleReciboPagoEmailRecipients::resolve($sale);
        $values = self::valuesForSale($sale);
        /** En una prueba los testigos no reciben nada: solo se muestran en el aviso de prueba. */
        $configuredWitnesses = DocumentEmailWitnesses::for(SystemNotificationKey::SaleReceiptWitnessCopy);
        $witnesses = $testMode
            ? ['active' => false, 'cc' => [], 'bcc' => [], 'phones' => []]
            : $configuredWitnesses;
        $ccEmails = $witnesses['cc'];
        $bccEmails = $witnesses['bcc'];

        $plan = [];

        if ($affiliateTemplateId !== null) {
            $affiliateEmail = $recipients['affiliate']['email'];

            if (! $testMode && $affiliateEmail === null) {
                throw new RuntimeException('La afiliación no tiene un correo válido del afiliado. Corríjalo o elija «No enviar» para el afiliado.');
            }

            $plan[] = [
                'recipient' => DocumentEmailAudience::Affiliate,
                'template' => DocumentEmailCopies::usableTemplate(self::DOCUMENT, $affiliateTemplateId, DocumentEmailAudience::Affiliate),
                'to' => $testMode ? [$normalizedTestEmail] : [$affiliateEmail],
                'real_to' => array_values(array_filter([$affiliateEmail])),
            ];
        }

        if ($responsibleTemplateId !== null) {
            if (! $testMode && ! $recipients['has_recipients']) {
                throw new RuntimeException('Ni el agente ni la agencia tienen un correo válido. Corríjalo o elija «No enviar» para el agente / agencia.');
            }

            $plan[] = [
                'recipient' => DocumentEmailAudience::Responsible,
                'template' => DocumentEmailCopies::usableTemplate(self::DOCUMENT, $responsibleTemplateId, DocumentEmailAudience::Responsible),
                'to' => $testMode ? [$normalizedTestEmail] : $recipients['emails'],
                'real_to' => $recipients['emails'],
            ];
        }

        $guardKey = DocumentEmailCopies::guardAgainstDuplicate(self::DOCUMENT, $sale->getKey(), [
            $affiliateTemplateId,
            $responsibleTemplateId,
            $testMode,
            $normalizedTestEmail,
        ]);

        $summary = [];

        try {
            /** Con la cola `database` los dos correos entran juntos o ninguno. */
            DB::transaction(function () use ($plan, $values, $testMode, $sale, $pdfPath, $ccEmails, $bccEmails, $witnesses, $configuredWitnesses, &$summary): void {
                foreach ($plan as $item) {
                    $rendered = DocumentEmailTemplateRenderer::render($item['template'], $values);
                    $subject = $testMode ? '[PRUEBA] '.$rendered['subject'] : $rendered['subject'];
                    $bodyHtml = $testMode
                        ? DocumentEmailCopies::testNoticeHtml($item['recipient'], $item['real_to'], $configuredWitnesses).$rendered['body_html']
                        : $rendered['body_html'];

                    $mailable = (new MailSaleReciboPago(
                        invoiceNumber: (string) $sale->invoice_number,
                        pdfPath: $pdfPath,
                        ccRecipients: $ccEmails,
                        subjectLine: $subject,
                        bodyHtml: $bodyHtml,
                        bccRecipients: $bccEmails,
                    ))->onQueue('default');

                    Mail::to($item['to'])->queue($mailable);

                    $summary[] = [
                        'recipient' => $item['recipient']->value,
                        'to' => $item['to'],
                        'cc' => $ccEmails,
                        'bcc' => $bccEmails,
                        'template_id' => $item['template']->id,
                        'template_name' => $item['template']->name,
                        'subject' => $subject,
                    ];
                }

                DocumentEmailWitnesses::queueWhatsAppCopies(
                    $witnesses['phones'],
                    DocumentEmailWitnesses::caption('Recibo de pago Nro. '.$sale->invoice_number, (string) $values['afiliado'], $summary, Auth::user()?->name),
                    DocumentEmailWitnesses::publicDocumentUrl('reciboDePago', basename($pdfPath), $pdfPath),
                    basename($pdfPath),
                    ['panel' => 'administration', 'source' => 'administration.sales.receipt-witness', 'sale_id' => $sale->getKey()],
                );
            });
        } catch (Throwable $exception) {
            DocumentEmailCopies::releaseDuplicateGuard($guardKey);

            throw $exception;
        }

        SecurityAudit::log('AUDIT_ADMIN_SALES_RECEIPT_EMAIL_QUEUED', 'administration.sales.send-receipt-email', [
            'panel' => 'administration',
            'sale_id' => $sale->getKey(),
            'invoice_number' => $sale->invoice_number,
            'affiliation_code' => $sale->affiliation_code,
            'sale_type' => $sale->type,
            'test_mode' => $testMode,
            'deliveries' => $summary,
            'witness_phones' => $witnesses['phones'],
        ]);

        return $summary;
    }

    public static function usd(mixed $amount): string
    {
        return is_numeric($amount) ? 'US$'.number_format((float) $amount, 2, '.', ',') : '';
    }
}
