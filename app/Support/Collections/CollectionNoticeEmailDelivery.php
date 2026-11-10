<?php

declare(strict_types=1);

namespace App\Support\Collections;

use App\Enums\DocumentEmailAudience;
use App\Enums\DocumentEmailType;
use App\Enums\SystemNotificationKey;
use App\Mail\CollectionNoticeCopyMail;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Collection;
use App\Models\CollectionNoticeRegeneration;
use App\Models\Plan;
use App\Support\DocumentEmails\DocumentEmailCopies;
use App\Support\DocumentEmails\DocumentEmailTemplateRenderer;
use App\Support\DocumentEmails\DocumentEmailWitnesses;
use App\Support\Filament\Administration\SaleReciboPagoEmailRecipients;
use App\Support\SecurityAudit;
use App\Support\Storefront\StorefrontPaymentMethodsDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Envío del aviso de cobro con el copy que el analista elige para cada destinatario.
 *
 * Dos correos independientes, cada uno con su copy, el PDF del aviso y, si el analista lo marca,
 * el PDF de métodos de pago:
 * - al afiliado: el correo impreso en el aviso (lo que el analista dejó al regenerarlo) o, si no
 *   hay, el de la cuota;
 * - al responsable: agente y agencia juntos.
 * Los testigos (Centro de notificaciones → «Testigos de avisos de cobro») van en CC o CCO según
 * cada correo, y sus teléfonos reciben por WhatsApp el PDF con el detalle del envío.
 *
 * Los datos del copy (período, emisión, nombre) salen de la última regeneración: el correo dice
 * exactamente lo mismo que el PDF adjunto.
 */
final class CollectionNoticeEmailDelivery
{
    private const DOCUMENT = DocumentEmailType::CollectionNotice;

    /**
     * @return array{
     *     affiliate: array{label: string, emails: list<string>, default_id: ?int, templates: list<array{id: int, name: string, subject: string, body_html: string}>},
     *     responsible: array{label: string, emails: list<string>, default_id: ?int, templates: list<array{id: int, name: string, subject: string, body_html: string}>},
     *     cc_emails: list<string>,
     *     bcc_emails: list<string>,
     *     witness_phones: list<string>,
     *     payment_methods_available: bool
     * }
     */
    public static function options(Collection $collection, ?CollectionNoticeRegeneration $regeneration = null): array
    {
        $regeneration ??= self::latestRegeneration($collection);
        $recipients = self::recipients($collection, $regeneration);
        $witnesses = DocumentEmailWitnesses::for(SystemNotificationKey::CollectionNoticeWitnessCopy);

        return [
            ...DocumentEmailCopies::options(self::DOCUMENT, self::values($collection, $regeneration, $recipients), [
                'affiliate' => array_values(array_filter([$recipients['affiliate_email']])),
                'responsible' => $recipients['responsible_emails'],
            ]),
            'cc_emails' => $witnesses['cc'],
            'bcc_emails' => $witnesses['bcc'],
            'witness_phones' => $witnesses['phones'],
            'payment_methods_available' => StorefrontPaymentMethodsDocument::exists(),
        ];
    }

    /**
     * @return list<array{recipient: string, to: list<string>, cc: list<string>, bcc: list<string>, template_id: int, template_name: string, subject: string, payment_methods: bool}>
     *
     * @throws RuntimeException con un mensaje en español listo para mostrar al analista
     */
    public static function deliver(
        Collection $collection,
        ?int $affiliateTemplateId,
        ?int $responsibleTemplateId,
        bool $attachPaymentMethods = false,
        bool $testMode = false,
        ?string $testEmail = null,
    ): array {
        $noticePdfPath = CollectionNotice::pdfPath($collection);

        if (! is_file($noticePdfPath)) {
            throw new RuntimeException('No existe el PDF del aviso. Regenérelo antes de enviar el correo.');
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

        $paymentMethodsPath = $attachPaymentMethods ? self::paymentMethodsPath() : null;

        $regeneration = self::latestRegeneration($collection);
        $recipients = self::recipients($collection, $regeneration);
        $values = self::values($collection, $regeneration, $recipients);
        /** En una prueba los testigos no reciben nada: solo se muestran en el aviso de prueba. */
        $configuredWitnesses = DocumentEmailWitnesses::for(SystemNotificationKey::CollectionNoticeWitnessCopy);
        $witnesses = $testMode
            ? ['active' => false, 'cc' => [], 'bcc' => [], 'phones' => []]
            : $configuredWitnesses;
        $ccEmails = $witnesses['cc'];
        $bccEmails = $witnesses['bcc'];

        $plan = [];

        if ($affiliateTemplateId !== null) {
            if (! $testMode && $recipients['affiliate_email'] === null) {
                throw new RuntimeException('El aviso no tiene un correo válido del afiliado. Corríjalo en «Editar datos», regenere el PDF o elija «No enviar» para el afiliado.');
            }

            $plan[] = [
                'recipient' => DocumentEmailAudience::Affiliate,
                'template' => DocumentEmailCopies::usableTemplate(self::DOCUMENT, $affiliateTemplateId, DocumentEmailAudience::Affiliate),
                'to' => $testMode ? [$normalizedTestEmail] : [$recipients['affiliate_email']],
                'real_to' => array_values(array_filter([$recipients['affiliate_email']])),
            ];
        }

        if ($responsibleTemplateId !== null) {
            if (! $testMode && $recipients['responsible_emails'] === []) {
                throw new RuntimeException('Ni el agente ni la agencia tienen un correo válido. Corríjalo o elija «No enviar» para el agente / agencia.');
            }

            $plan[] = [
                'recipient' => DocumentEmailAudience::Responsible,
                'template' => DocumentEmailCopies::usableTemplate(self::DOCUMENT, $responsibleTemplateId, DocumentEmailAudience::Responsible),
                'to' => $testMode ? [$normalizedTestEmail] : $recipients['responsible_emails'],
                'real_to' => $recipients['responsible_emails'],
            ];
        }

        $guardKey = DocumentEmailCopies::guardAgainstDuplicate(self::DOCUMENT, $collection->getKey(), [
            $affiliateTemplateId,
            $responsibleTemplateId,
            $attachPaymentMethods,
            $testMode,
            $normalizedTestEmail,
            $regeneration?->getKey(),
        ]);

        $summary = [];

        try {
            /** Con la cola `database` los dos correos entran juntos o ninguno. */
            DB::transaction(function () use ($plan, $values, $testMode, $collection, $noticePdfPath, $ccEmails, $bccEmails, $witnesses, $configuredWitnesses, $paymentMethodsPath, &$summary): void {
                foreach ($plan as $item) {
                    $rendered = DocumentEmailTemplateRenderer::render($item['template'], $values);
                    $subject = $testMode ? '[PRUEBA] '.$rendered['subject'] : $rendered['subject'];
                    $bodyHtml = $testMode
                        ? DocumentEmailCopies::testNoticeHtml($item['recipient'], $item['real_to'], $configuredWitnesses).$rendered['body_html']
                        : $rendered['body_html'];

                    $mailable = (new CollectionNoticeCopyMail(
                        invoiceNumber: (string) $collection->collection_invoice_number,
                        noticePdfPath: $noticePdfPath,
                        subjectLine: $subject,
                        bodyHtml: $bodyHtml,
                        ccRecipients: $ccEmails,
                        paymentMethodsPdfPath: $paymentMethodsPath,
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
                        'payment_methods' => $paymentMethodsPath !== null,
                    ];
                }

                DocumentEmailWitnesses::queueWhatsAppCopies(
                    $witnesses['phones'],
                    DocumentEmailWitnesses::caption('Aviso de cobro Nro. '.$collection->collection_invoice_number, (string) $values['afiliado'], $summary, Auth::user()?->name),
                    DocumentEmailWitnesses::publicDocumentUrl(CollectionNotice::PDF_DIRECTORY, basename($noticePdfPath), $noticePdfPath),
                    basename($noticePdfPath),
                    ['panel' => 'administration', 'source' => 'administration.collections.notice-witness', 'collection_id' => $collection->getKey()],
                );
            });
        } catch (Throwable $exception) {
            DocumentEmailCopies::releaseDuplicateGuard($guardKey);

            throw $exception;
        }

        SecurityAudit::log('AUDIT_ADMIN_COLLECTION_NOTICE_EMAIL_QUEUED', 'administration.collections.send-notice-email', [
            'panel' => 'administration',
            'collection_id' => $collection->getKey(),
            'invoice_number' => $collection->collection_invoice_number,
            'affiliation_code' => $collection->affiliation_code,
            'regeneration_id' => $regeneration?->getKey(),
            'test_mode' => $testMode,
            'deliveries' => $summary,
            'witness_phones' => $witnesses['phones'],
        ]);

        return $summary;
    }

    public static function latestRegeneration(Collection $collection): ?CollectionNoticeRegeneration
    {
        if ($collection->getKey() === null) {
            return null;
        }

        return CollectionNoticeRegeneration::query()
            ->where('collection_id', $collection->getKey())
            ->latest('id')
            ->first();
    }

    /**
     * @return array{affiliate_email: ?string, responsible_emails: list<string>, agent_name: string, agency_name: string}
     */
    public static function recipients(Collection $collection, ?CollectionNoticeRegeneration $regeneration): array
    {
        $agent = filled($collection->agent_id)
            ? Agent::query()->find($collection->agent_id, ['id', 'name', 'email'])
            : null;

        $agency = filled($collection->code_agency)
            ? Agency::query()->where('code', $collection->code_agency)->first(['id', 'code', 'name_corporative', 'email'])
            : null;

        $printedEmail = is_array($regeneration?->client) ? ($regeneration->client['email'] ?? null) : null;

        return [
            'affiliate_email' => SaleReciboPagoEmailRecipients::normalizeEmail(is_string($printedEmail) ? $printedEmail : null)
                ?? SaleReciboPagoEmailRecipients::normalizeEmail($collection->affiliate_email),
            'responsible_emails' => array_values(array_unique(array_filter([
                SaleReciboPagoEmailRecipients::normalizeEmail($agent?->email),
                SaleReciboPagoEmailRecipients::normalizeEmail($agency?->email),
            ]))),
            'agent_name' => trim((string) $agent?->name),
            'agency_name' => trim((string) $agency?->name_corporative),
        ];
    }

    /**
     * @param  array{agent_name: string, agency_name: string}  $recipients
     * @return array<string, string>
     */
    public static function values(Collection $collection, ?CollectionNoticeRegeneration $regeneration, array $recipients): array
    {
        $printedName = is_array($regeneration?->client) ? trim((string) ($regeneration->client['full_name'] ?? '')) : '';

        if ($regeneration?->period_from !== null && $regeneration->period_to !== null) {
            $from = $regeneration->period_from->format(CollectionNotice::DATE_FORMAT);
            $to = $regeneration->period_to->format(CollectionNotice::DATE_FORMAT);
        } else {
            [$defaultFrom, $defaultTo] = CollectionNotice::defaultPeriod($collection);
            $from = $defaultFrom?->format(CollectionNotice::DATE_FORMAT) ?? '';
            $to = $defaultTo?->format(CollectionNotice::DATE_FORMAT) ?? '';
        }

        return [
            'afiliado' => $printedName !== '' ? $printedName : trim((string) $collection->affiliate_full_name),
            'nro_aviso' => trim((string) $collection->collection_invoice_number),
            'plan' => self::planLabel($collection),
            'periodo_desde' => $from,
            'periodo_hasta' => $to,
            'monto' => is_numeric($collection->total_amount) ? 'US$'.number_format((float) $collection->total_amount, 2, '.', ',') : '',
            'fecha_emision' => $regeneration?->emission_date?->format(CollectionNotice::DATE_FORMAT) ?? now()->format(CollectionNotice::DATE_FORMAT),
            'frecuencia' => trim((string) $collection->payment_frequency),
            'agente' => $recipients['agent_name'],
            'agencia' => $recipients['agency_name'],
        ];
    }

    /**
     * Plan de la cuota; en las corporativas, los planes de la afiliación separados por coma.
     */
    private static function planLabel(Collection $collection): string
    {
        if (CollectionNotice::isCorporate($collection) && filled($collection->affiliation_code)) {
            $planIds = AfilliationCorporatePlan::query()
                ->whereIn('affiliation_corporate_id', AffiliationCorporate::query()->select('id')->where('code', $collection->affiliation_code))
                ->distinct()
                ->pluck('plan_id');

            $labels = Plan::query()->whereIn('id', $planIds)->orderBy('description')->pluck('description')
                ->map(fn (mixed $label): string => trim((string) $label))
                ->filter()
                ->unique();

            if ($labels->isNotEmpty()) {
                return $labels->implode(', ');
            }
        }

        if (! filled($collection->plan_id)) {
            return '';
        }

        return trim((string) Plan::query()->whereKey($collection->plan_id)->value('description'));
    }

    /**
     * @throws RuntimeException si el analista pidió adjuntarlo y el documento no está cargado
     */
    private static function paymentMethodsPath(): string
    {
        $relative = StorefrontPaymentMethodsDocument::ensureShareableRelativePath();
        $path = $relative !== null ? Storage::disk('public')->path($relative) : null;

        if ($path === null || ! is_file($path)) {
            throw new RuntimeException('No se encontró el documento de métodos de pago. Desmarque «Adjuntar métodos de pago» o avise a Sistemas para cargarlo.');
        }

        return $path;
    }
}
