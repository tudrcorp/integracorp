<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Log as AuditLog;
use App\Models\Sale;
use App\Support\CorporateDocumentPlanAmounts;
use App\Support\Filament\Administration\InvoiceDocumentNumber;
use App\Support\Sales\InvoiceVesLineAmounts;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Factura de una venta (Administración → Ventas): arma los datos, guarda el PDF
 * en `storage/facturas/FACT-{número}.pdf` y deja en `sales.invoice_snapshot`
 * con qué se emitió, para poder regenerarla idéntica.
 *
 * Todos los montos de la factura van en bolívares: el total es lo cobrado en Bs
 * (o la tasa BCV × lo cobrado en US$) y las líneas corporativas se convierten
 * con la tasa implícita de ese total (ver InvoiceVesLineAmounts).
 */
final class SaleInvoicePdfService
{
    public const TYPE_CORPORATE = 'AFILIACION CORPORATIVA';

    public const SNAPSHOT_VERSION = 1;

    /**
     * @param  array<string, mixed>  $input  invoice_number, date (d/m/Y), invoice_in_name_of, tasa_bcv?, custom_*
     * @return string Ruta absoluta del PDF.
     */
    public function generate(Sale $sale, array $input): string
    {
        $issuedAt = now();
        $built = $this->build($sale, $input, $issuedAt);
        $path = self::pdfPath((string) $input['invoice_number']);

        $this->render($sale, $built['data'], $path);

        try {
            DB::transaction(function () use ($sale, $input, $built): void {
                $sale->invoice_generated = (string) $input['invoice_number'];
                $sale->invoice_snapshot = $built['snapshot'];
                $sale->save();
            });
        } catch (Throwable $exception) {
            File::delete($path);

            throw $exception;
        }

        return $path;
    }

    /**
     * Rehace el PDF de una factura ya emitida conservando número y fecha. El
     * archivo anterior se guarda al lado con sufijo `.reemplazada-{fecha}`.
     *
     * @param  array<string, mixed>  $input  date (d/m/Y), invoice_in_name_of, tasa_bcv?, custom_*
     * @return array{path: string, replaced_path: string|null}
     */
    public function regenerate(Sale $sale, array $input): array
    {
        $invoiceNumber = trim((string) $sale->invoice_generated);

        if ($invoiceNumber === '') {
            throw new RuntimeException('La venta no tiene una factura emitida para regenerar.');
        }

        $previous = $this->previousInvoiceInput($sale);
        $date = $previous['date'] ?? $this->normalizeDate($input['date'] ?? null);

        if ($date === null) {
            throw new RuntimeException('Indique la fecha de emisión original de la factura.');
        }

        $issuedAt = $previous['issued_at'] !== null
            ? Carbon::parse($previous['issued_at'])
            : Carbon::createFromFormat('d/m/Y', $date)->startOfDay();

        $built = $this->build($sale, [
            ...$input,
            'tasa_bcv' => $input['tasa_bcv'] ?? $previous['tasa_bcv'],
            'invoice_base_usd' => $input['invoice_base_usd'] ?? $previous['base_usd'],
            'source_payment' => $input['source_payment'] ?? $previous['source_payment'],
            'invoice_number' => $invoiceNumber,
            'date' => $date,
        ], $issuedAt);

        $path = self::pdfPath($invoiceNumber);
        $replacedPath = null;

        if (is_file($path)) {
            $replacedPath = $path.'.reemplazada-'.now()->format('YmdHis');
            File::move($path, $replacedPath);
        }

        try {
            $this->render($sale, $built['data'], $path);

            DB::transaction(function () use ($sale, $built): void {
                $sale->invoice_snapshot = [
                    ...$built['snapshot'],
                    'regenerated_at' => now()->toIso8601String(),
                ];
                $sale->save();
            });
        } catch (Throwable $exception) {
            File::delete($path);

            if ($replacedPath !== null) {
                File::move($replacedPath, $path);
            }

            throw $exception;
        }

        return ['path' => $path, 'replaced_path' => $replacedPath];
    }

    /**
     * Lo que se sabe de la emisión original: primero el snapshot; para las
     * facturas anteriores a él, la auditoría del intento de emisión (que guarda
     * fecha y «a nombre de», pero no la tasa ni los datos personalizados).
     *
     * @return array{date: string|null, invoice_in_name_of: string|null, tasa_bcv: float|null, base_usd: float|null, source_payment: array<string, mixed>|null, issued_at: string|null, billing_party: array<string, mixed>|null, source: string}
     */
    public function previousInvoiceInput(Sale $sale): array
    {
        $snapshot = is_array($sale->invoice_snapshot) ? $sale->invoice_snapshot : [];

        if ($snapshot !== []) {
            return [
                'date' => $this->normalizeDate($snapshot['date'] ?? null),
                'invoice_in_name_of' => $snapshot['invoice_in_name_of'] ?? null,
                'tasa_bcv' => is_numeric($snapshot['tasa_bcv'] ?? null) ? (float) $snapshot['tasa_bcv'] : null,
                'base_usd' => is_numeric($snapshot['base_usd'] ?? null) ? (float) $snapshot['base_usd'] : null,
                'source_payment' => is_array($snapshot['source_payment'] ?? null) ? $snapshot['source_payment'] : null,
                'issued_at' => $snapshot['issued_at'] ?? null,
                'billing_party' => is_array($snapshot['billing_party'] ?? null) ? $snapshot['billing_party'] : null,
                'source' => 'snapshot',
            ];
        }

        $details = $this->auditDetailsForInvoice($sale);

        return [
            'date' => $this->normalizeDate($details['date'] ?? null),
            'invoice_in_name_of' => $details['invoice_in_name_of'] ?? null,
            'tasa_bcv' => null,
            'base_usd' => null,
            'source_payment' => null,
            'issued_at' => null,
            'billing_party' => null,
            'source' => $details !== [] ? 'audit' : 'none',
        ];
    }

    public static function pdfPath(string $invoiceNumber): string
    {
        return public_path('storage/facturas/FACT-'.$invoiceNumber.'.pdf');
    }

    /**
     * El tipo existe con y sin tilde («AFILIACIÓN CORPORATIVA»): se compara sin acentos.
     */
    public static function isCorporate(Sale $sale): bool
    {
        return Str::upper(Str::ascii(trim((string) $sale->type))) === self::TYPE_CORPORATE;
    }

    /**
     * Total de la factura en bolívares.
     *
     * - Desde un pago (`$baseUsd`, la cuota del pago): cuota × tasa del analista.
     * - Desde Ventas, como lo calculaba la acción original: tasa × lo cobrado en
     *   US$, o lo cobrado en Bs. si la venta se pagó en bolívares.
     */
    public static function totalVes(Sale $sale, mixed $tasaBcv, mixed $baseUsd = null): float
    {
        if (is_numeric($baseUsd) && is_numeric($tasaBcv)) {
            return round((float) $baseUsd * (float) $tasaBcv, 2);
        }

        if ((float) ($sale->pay_amount_usd ?? 0) > 0 && is_numeric($tasaBcv)) {
            return round((float) $tasaBcv * (float) $sale->pay_amount_usd, 2);
        }

        return round((float) ($sale->pay_amount_ves ?? 0), 2);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{data: array<string, mixed>, snapshot: array<string, mixed>}
     */
    public function build(Sale $sale, array $input, Carbon $issuedAt): array
    {
        $sale->loadMissing(['plan', 'coverage']);

        $affiliation = self::isCorporate($sale)
            ? AffiliationCorporate::query()
                ->where('code', $sale->affiliation_code)
                ->with(['affiliationCorporatePlans'])
                ->first()
            : Affiliation::query()->where('code', $sale->affiliation_code)->first();

        $inNameOf = (string) ($input['invoice_in_name_of'] ?? 'titular');
        $tasaBcv = is_numeric($input['tasa_bcv'] ?? null) && (float) $input['tasa_bcv'] > 0 ? (float) $input['tasa_bcv'] : null;

        $baseUsd = is_numeric($input['invoice_base_usd'] ?? null) ? round((float) $input['invoice_base_usd'], 2) : null;

        if ($baseUsd !== null && $baseUsd <= 0) {
            throw new RuntimeException('El pago no tiene una cuota en dólares para facturar.');
        }

        if ($baseUsd !== null && $tasaBcv === null) {
            throw new RuntimeException('Indique la tasa BCV para calcular el monto en bolívares.');
        }

        if ((float) ($sale->pay_amount_usd ?? 0) > 0 && $tasaBcv === null) {
            throw new RuntimeException('Indique la tasa BCV: la venta tiene un monto cobrado en dólares.');
        }

        $totalVes = self::totalVes($sale, $tasaBcv, $baseUsd);
        $billingParty = self::resolveBillingParty($inNameOf, $sale, $affiliation, $input);

        $data = [
            'invoice_number' => (string) $input['invoice_number'],
            'emission_date' => (string) $input['date'],
            'payment_method' => $sale->payment_method,
            'reference' => $sale->reference_payment,
            ...$billingParty,
            'total_amount' => $totalVes,
            'plan' => self::isCorporate($sale)
                ? ($affiliation?->affiliationCorporatePlans?->toArray() ?? [])
                : $sale->plan?->description,
            'coverage' => $sale->coverage?->price,
            'frequency' => $sale->payment_frequency,
        ];

        if (self::isCorporate($sale)) {
            $data = [...$data, ...self::corporateAmounts($data['plan'], $data['frequency'], $totalVes, $issuedAt)];
        }

        return [
            'data' => $data,
            'snapshot' => [
                'version' => self::SNAPSHOT_VERSION,
                'date' => (string) $input['date'],
                'issued_at' => $issuedAt->toIso8601String(),
                'invoice_in_name_of' => $inNameOf,
                'billing_party' => $billingParty,
                'tasa_bcv' => $tasaBcv,
                'base_usd' => $baseUsd,
                'source_payment' => is_array($input['source_payment'] ?? null) ? $input['source_payment'] : null,
                'total_ves' => $totalVes,
                'period_from' => $data['period_from'] ?? null,
                'period_to' => $data['period_to'] ?? null,
            ],
        ];
    }

    /**
     * Montos en Bs de cada línea corporativa y vigencia del período facturado.
     *
     * @param  list<array<string, mixed>>  $plans
     * @return array{lines_ves: list<float|null>, lines_usd: list<float>, exchange_rate: float|null, period_from: string, period_to: string}
     */
    public static function corporateAmounts(array $plans, ?string $frequency, float $totalVes, Carbon $issuedAt): array
    {
        $linesUsd = array_map(
            static fn (array $row): float => CorporateDocumentPlanAmounts::periodAmount($row, $frequency),
            array_values($plans),
        );

        $distribution = InvoiceVesLineAmounts::distribute($linesUsd, $totalVes);
        $lastPlan = $plans === [] ? [] : $plans[array_key_last($plans)];

        return [
            'lines_ves' => $distribution['lines'],
            'lines_usd' => $linesUsd,
            'exchange_rate' => $distribution['rate'],
            'period_from' => $issuedAt->format('d/m/Y'),
            'period_to' => CorporateDocumentPlanAmounts::periodEndFrom($issuedAt, $lastPlan, $frequency),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function resolveBillingParty(
        string $inNameOf,
        Sale $sale,
        Affiliation|AffiliationCorporate|null $affiliation,
        array $data = [],
    ): array {
        $party = match (true) {
            $inNameOf === 'custom' => [
                'full_name_ti' => $data['custom_full_name'] ?? null,
                'ci_rif_ti' => $data['custom_ci_rif'] ?? null,
                'address_ti' => $data['custom_address'] ?? null,
                'phone_ti' => $data['custom_phone'] ?? null,
                'email_ti' => $data['custom_email'] ?? null,
            ],
            $affiliation instanceof AffiliationCorporate && $inNameOf === 'tomador' => [
                'full_name_ti' => $affiliation->full_name_contact,
                'ci_rif_ti' => $affiliation->nro_identificacion_contact,
                'address_ti' => $affiliation->address,
                'phone_ti' => $affiliation->phone_contact,
                'email_ti' => $affiliation->email_contact,
            ],
            $affiliation instanceof AffiliationCorporate => [
                'full_name_ti' => $affiliation->name_corporate,
                'ci_rif_ti' => $affiliation->rif,
                'address_ti' => $affiliation->address,
                'phone_ti' => $affiliation->phone,
                'email_ti' => $affiliation->email,
            ],
            $inNameOf === 'tomador' => [
                'full_name_ti' => $affiliation?->full_name_payer,
                'ci_rif_ti' => $affiliation?->nro_identificacion_payer,
                'address_ti' => $affiliation?->adress_ti,
                'phone_ti' => $affiliation?->phone_payer,
                'email_ti' => $affiliation?->email_payer,
            ],
            default => [
                'full_name_ti' => $sale->affiliate_full_name ?? $affiliation?->full_name_ti,
                'ci_rif_ti' => $sale->affiliate_ci_rif ?? $affiliation?->nro_identificacion_ti,
                'address_ti' => $affiliation?->adress_ti,
                'phone_ti' => $affiliation?->phone_ti,
                'email_ti' => $affiliation?->email_ti,
            ],
        };

        $party['ci_rif_ti'] = InvoiceDocumentNumber::digitsOnly($party['ci_rif_ti'] ?? null);

        return $party;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function render(Sale $sale, array $data, string $path): void
    {
        ini_set('memory_limit', '2048M');

        File::ensureDirectoryExists(dirname($path));

        Pdf::loadView(
            self::isCorporate($sale) ? 'documents.factura-corporativa' : 'documents.factura',
            ['data_factura' => $data],
        )->save($path);
    }

    /**
     * Detalles del último intento de emisión auditado para el número vigente.
     *
     * @return array<string, mixed>
     */
    private function auditDetailsForInvoice(Sale $sale): array
    {
        $candidates = AuditLog::query()
            ->where('action', 'AUDIT_ADMIN_SALES_INVOICE_GENERATION_ATTEMPTED')
            ->where('response', 'like', '%"sale_id":'.(int) $sale->id.',%')
            ->orderByDesc('id')
            ->limit(20)
            ->pluck('response');

        foreach ($candidates as $response) {
            $details = json_decode((string) $response, true)['details'] ?? null;

            if (! is_array($details) || (int) ($details['sale_id'] ?? 0) !== (int) $sale->id) {
                continue;
            }

            if ((string) ($details['invoice_number'] ?? '') === (string) $sale->invoice_generated && filled($details['date'] ?? null)) {
                return $details;
            }
        }

        return [];
    }

    private function normalizeDate(mixed $date): ?string
    {
        if (! is_string($date) || trim($date) === '') {
            return null;
        }

        $date = trim($date);

        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $date);
            } catch (Throwable) {
                continue;
            }

            if ($parsed !== false && $parsed->format($format) === $date) {
                return $parsed->format('d/m/Y');
            }
        }

        return null;
    }
}
