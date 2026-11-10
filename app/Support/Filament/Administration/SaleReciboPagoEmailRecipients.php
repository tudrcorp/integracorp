<?php

declare(strict_types=1);

namespace App\Support\Filament\Administration;

use App\Enums\SystemNotificationKey;
use App\Filament\Administration\Resources\Sales\Tables\SalesTable;
use App\Mail\MailSaleReciboPago;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Sale;
use App\Support\DocumentEmails\DocumentEmailWitnesses;
use Illuminate\Support\Facades\Mail;

final class SaleReciboPagoEmailRecipients
{
    /**
     * Testigos históricos: hoy son el valor inicial de «Testigos de recibos de pago» en el Centro
     * de notificaciones, que es donde se administran.
     *
     * @var list<string>
     */
    public const CC_RECIPIENTS = [
        'afiliaciones@tudrencasa.com',
        'administracion@tudrencasa.com',
        'solrodriguez@tudrencasa.com',
        'hsanchez@tudrencasa.com',
    ];

    /**
     * `emails` son los del responsable (agente y agencia), como siempre; el afiliado va aparte
     * en `affiliate` porque recibe su propio correo con su propio copy.
     *
     * @return array{
     *     affiliate: array{name: ?string, email: ?string},
     *     agent: array{linked: bool, name: ?string, code: ?string, email: ?string},
     *     agency: array{linked: bool, name: ?string, code: ?string, email: ?string},
     *     emails: list<string>,
     *     cc_emails: list<string>,
     *     bcc_emails: list<string>,
     *     has_recipients: bool,
     *     has_pdf: bool
     * }
     */
    public static function resolve(Sale $sale): array
    {
        $agent = self::resolveAgent($sale);
        $agency = self::resolveAgency($sale);

        $witnesses = DocumentEmailWitnesses::for(SystemNotificationKey::SaleReceiptWitnessCopy);
        $agentEmail = self::normalizeEmail($agent?->email);
        $agencyEmail = self::normalizeEmail($agency?->email);

        $emails = array_values(array_unique(array_filter([
            $agentEmail,
            $agencyEmail,
        ])));

        return [
            'affiliate' => [
                'name' => filled($sale->affiliate_full_name) ? (string) $sale->affiliate_full_name : null,
                'email' => self::resolveAffiliateEmail($sale),
            ],
            'agent' => [
                'linked' => filled($sale->agent_id),
                'name' => $agent?->name,
                'code' => $agent?->code_agent,
                'email' => $agentEmail,
            ],
            'agency' => [
                'linked' => filled($sale->code_agency),
                'name' => $agency?->name_corporative,
                'code' => $agency?->code ?? $sale->code_agency,
                'email' => $agencyEmail,
            ],
            'emails' => $emails,
            'cc_emails' => $witnesses['cc'],
            'bcc_emails' => $witnesses['bcc'],
            'has_recipients' => $emails !== [],
            'has_pdf' => is_file(SalesTable::reciboPagoPdfPath($sale)),
        ];
    }

    /**
     * @return list<string>
     */
    public static function ccRecipients(): array
    {
        return DocumentEmailWitnesses::for(SystemNotificationKey::SaleReceiptWitnessCopy)['cc'];
    }

    /**
     * @return list<string>
     */
    public static function bccRecipients(): array
    {
        return DocumentEmailWitnesses::for(SystemNotificationKey::SaleReceiptWitnessCopy)['bcc'];
    }

    public static function send(Sale $sale, bool $testMode = false, ?string $testEmail = null): void
    {
        if (! is_file(SalesTable::reciboPagoPdfPath($sale))) {
            throw new \RuntimeException('No existe el PDF del recibo. Regenérelo antes de enviar el correo.');
        }

        $pdfPath = SalesTable::reciboPagoPdfPath($sale);

        if ($testMode) {
            $email = self::normalizeEmail($testEmail);

            if ($email === null) {
                throw new \RuntimeException('Ingrese un correo de prueba válido.');
            }

            Mail::to($email)->send(new MailSaleReciboPago(
                invoiceNumber: (string) $sale->invoice_number,
                pdfPath: $pdfPath,
                ccRecipients: [],
            ));

            return;
        }

        $recipients = self::resolve($sale);

        if (! $recipients['has_recipients']) {
            throw new \RuntimeException('No hay correos válidos del agente o la agencia para enviar el recibo.');
        }

        Mail::to($recipients['emails'])->send(new MailSaleReciboPago(
            invoiceNumber: (string) $sale->invoice_number,
            pdfPath: $pdfPath,
            ccRecipients: $recipients['cc_emails'],
            bccRecipients: $recipients['bcc_emails'],
        ));
    }

    /**
     * Correo del afiliado: el de la afiliación (titular o empresa) y, si no hay, el copiado en la venta.
     */
    public static function resolveAffiliateEmail(Sale $sale): ?string
    {
        $email = null;

        if (filled($sale->affiliation_code)) {
            $email = match ($sale->type) {
                'AFILIACION INDIVIDUAL' => Affiliation::query()->where('code', $sale->affiliation_code)->value('email_ti'),
                'AFILIACION CORPORATIVA' => AffiliationCorporate::query()->where('code', $sale->affiliation_code)->value('email'),
                default => null,
            };
        }

        return self::normalizeEmail(is_string($email) ? $email : null)
            ?? self::normalizeEmail($sale->affiliate_email);
    }

    private static function resolveAgent(Sale $sale): ?Agent
    {
        if ($sale->relationLoaded('agent')) {
            return $sale->agent;
        }

        if (! filled($sale->agent_id)) {
            return null;
        }

        return Agent::query()->find($sale->agent_id);
    }

    private static function resolveAgency(Sale $sale): ?Agency
    {
        if ($sale->relationLoaded('agency')) {
            return $sale->agency;
        }

        if (! filled($sale->code_agency)) {
            return null;
        }

        return Agency::query()->where('code', $sale->code_agency)->first();
    }

    public static function normalizeEmail(?string $email): ?string
    {
        $normalized = strtolower(trim((string) $email));

        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $normalized;
    }
}
