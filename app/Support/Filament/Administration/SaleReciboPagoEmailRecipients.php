<?php

declare(strict_types=1);

namespace App\Support\Filament\Administration;

use App\Filament\Administration\Resources\Sales\Tables\SalesTable;
use App\Mail\MailSaleReciboPago;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Sale;
use Illuminate\Support\Facades\Mail;

final class SaleReciboPagoEmailRecipients
{
    /**
     * @var list<string>
     */
    public const CC_RECIPIENTS = [
        'afiliaciones@tudrencasa.com',
        'administracion@tudrencasa.com',
        'solrodriguez@tudrencasa.com',
        'hsanchez@tudrencasa.com',
    ];

    /**
     * @return array{
     *     agent: array{linked: bool, name: ?string, code: ?string, email: ?string},
     *     agency: array{linked: bool, name: ?string, code: ?string, email: ?string},
     *     emails: list<string>,
     *     cc_emails: list<string>,
     *     has_recipients: bool,
     *     has_pdf: bool
     * }
     */
    public static function resolve(Sale $sale): array
    {
        $agent = self::resolveAgent($sale);
        $agency = self::resolveAgency($sale);

        $agentEmail = self::normalizeEmail($agent?->email);
        $agencyEmail = self::normalizeEmail($agency?->email);

        $emails = array_values(array_unique(array_filter([
            $agentEmail,
            $agencyEmail,
        ])));

        return [
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
            'cc_emails' => self::ccRecipients(),
            'has_recipients' => $emails !== [],
            'has_pdf' => is_file(SalesTable::reciboPagoPdfPath($sale)),
        ];
    }

    /**
     * @return list<string>
     */
    public static function ccRecipients(): array
    {
        return self::CC_RECIPIENTS;
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
            ccRecipients: self::ccRecipients(),
        ));
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

    private static function normalizeEmail(?string $email): ?string
    {
        $normalized = strtolower(trim((string) $email));

        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $normalized;
    }
}
