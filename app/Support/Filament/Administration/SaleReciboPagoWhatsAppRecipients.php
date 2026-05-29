<?php

declare(strict_types=1);

namespace App\Support\Filament\Administration;

use App\Filament\Administration\Resources\Sales\Tables\SalesTable;
use App\Http\Controllers\NotificationController;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Sale;
use RuntimeException;

final class SaleReciboPagoWhatsAppRecipients
{
    public const SOL_RODRIGUEZ_PHONE = '04143027250';

    public const SOL_RODRIGUEZ_LABEL = 'Sol Rodriguez';

    /**
     * @return array{
     *     agent: array{linked: bool, name: ?string, code: ?string, phone: ?string, phone_display: ?string},
     *     agency: array{linked: bool, name: ?string, code: ?string, phone: ?string, phone_display: ?string},
     *     internal: array{name: string, phone: string, phone_display: string},
     *     targets: list<array{role: string, name: ?string, phone: string, phone_display: string}>,
     *     has_recipients: bool,
     *     has_pdf: bool
     * }
     */
    public static function resolve(Sale $sale): array
    {
        $agent = self::resolveAgent($sale);
        $agency = self::resolveAgency($sale);

        $agentPhone = self::normalizePhone($agent?->phone);
        $agencyPhone = self::normalizePhone($agency?->phone);
        $solPhone = self::normalizePhone(self::SOL_RODRIGUEZ_PHONE);

        $targets = [];
        $seenPhones = [];

        if ($agentPhone !== null && ! isset($seenPhones[$agentPhone])) {
            $seenPhones[$agentPhone] = true;
            $targets[] = [
                'role' => 'agent',
                'name' => $agent?->name,
                'phone' => $agentPhone,
                'phone_display' => self::formatPhoneDisplay($agentPhone),
            ];
        }

        if ($agencyPhone !== null && ! isset($seenPhones[$agencyPhone])) {
            $seenPhones[$agencyPhone] = true;
            $targets[] = [
                'role' => 'agency',
                'name' => $agency?->name_corporative,
                'phone' => $agencyPhone,
                'phone_display' => self::formatPhoneDisplay($agencyPhone),
            ];
        }

        if ($solPhone !== null && ! isset($seenPhones[$solPhone])) {
            $seenPhones[$solPhone] = true;
            $targets[] = [
                'role' => 'internal',
                'name' => self::SOL_RODRIGUEZ_LABEL,
                'phone' => $solPhone,
                'phone_display' => self::formatPhoneDisplay($solPhone),
            ];
        }

        return [
            'agent' => [
                'linked' => filled($sale->agent_id),
                'name' => $agent?->name,
                'code' => $agent?->code_agent,
                'phone' => $agentPhone,
                'phone_display' => $agentPhone !== null ? self::formatPhoneDisplay($agentPhone) : null,
            ],
            'agency' => [
                'linked' => filled($sale->code_agency),
                'name' => $agency?->name_corporative,
                'code' => $agency?->code ?? $sale->code_agency,
                'phone' => $agencyPhone,
                'phone_display' => $agencyPhone !== null ? self::formatPhoneDisplay($agencyPhone) : null,
            ],
            'internal' => [
                'name' => self::SOL_RODRIGUEZ_LABEL,
                'phone' => $solPhone ?? self::SOL_RODRIGUEZ_PHONE,
                'phone_display' => self::formatPhoneDisplay($solPhone ?? self::SOL_RODRIGUEZ_PHONE),
            ],
            'targets' => $targets,
            'has_recipients' => $targets !== [],
            'has_pdf' => is_file(SalesTable::reciboPagoPdfPath($sale)),
        ];
    }

    /**
     * @return array{
     *     dispatched: int,
     *     failed: int,
     *     failures: list<array{role: string, name: ?string, phone: string}>
     * }
     */
    public static function send(Sale $sale, bool $testMode = false, ?string $testPhone = null): array
    {
        if (! is_file(SalesTable::reciboPagoPdfPath($sale))) {
            throw new RuntimeException('No existe el PDF del recibo. Regenérelo antes de enviar por WhatsApp.');
        }

        $invoiceNumber = (string) $sale->invoice_number;

        if ($testMode) {
            $phone = self::normalizePhone($testPhone);

            if ($phone === null) {
                throw new RuntimeException('Ingrese un teléfono de prueba válido.');
            }

            $sent = NotificationController::sendReciboDePago($phone, $invoiceNumber);

            if (! $sent) {
                throw new RuntimeException('No se pudo enviar el recibo por WhatsApp al teléfono de prueba.');
            }

            return [
                'dispatched' => 1,
                'failed' => 0,
                'failures' => [],
            ];
        }

        $recipients = self::resolve($sale);

        if (! $recipients['has_recipients']) {
            throw new RuntimeException('No hay números de teléfono válidos para enviar el recibo por WhatsApp.');
        }

        $report = [
            'dispatched' => 0,
            'failed' => 0,
            'failures' => [],
        ];

        foreach ($recipients['targets'] as $index => $target) {
            if ($index > 0) {
                usleep(750_000);
            }

            $sent = NotificationController::sendReciboDePago($target['phone'], $invoiceNumber);

            if ($sent) {
                $report['dispatched']++;

                continue;
            }

            $report['failed']++;
            $report['failures'][] = [
                'role' => $target['role'],
                'name' => $target['name'],
                'phone' => $target['phone'],
            ];
        }

        if ($report['dispatched'] === 0) {
            throw new RuntimeException('No se pudo enviar el recibo por WhatsApp a ningún destinatario.');
        }

        return $report;
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

    private static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '58') && strlen($digits) === 12) {
            return '0'.substr($digits, 2);
        }

        if (str_starts_with($digits, '4') && strlen($digits) === 10) {
            return '0'.$digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return $digits;
        }

        return strlen($digits) >= 10 ? $digits : null;
    }

    private static function formatPhoneDisplay(string $phone): string
    {
        if (strlen($phone) === 11 && str_starts_with($phone, '0')) {
            return substr($phone, 0, 4).'-'.substr($phone, 4);
        }

        return $phone;
    }
}
