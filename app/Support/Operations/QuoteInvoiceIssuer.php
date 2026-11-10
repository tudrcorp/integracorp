<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationAccountsReceivable;
use App\Models\OperationCoordinationService;
use App\Models\OperationMedicationInvoice;
use App\Models\OperationMedicationInvoiceEvent;
use App\Models\OperationMedicationInvoiceItem;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Models\User;
use App\Support\BcvOfficialRate;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Factura al paciente que nace al APROBAR una cotización de lo NO CUBIERTO (10/11/2026).
 *
 * Decisiones de Gustavo:
 * - El analista decide al crear la cotización si genera factura y quién paga (paciente o grupo
 *   corporativo). La factura se emite al aprobarla, no antes: una cotización rechazada nunca
 *   deja una factura que anular.
 * - Precio de cada línea = costo cotizado × (1 + % de utilidad del ítem). El ítem sin % se cobra
 *   al costo. Los ítems de CORTESÍA no se cobran; una cotización de cortesía no factura.
 * - Mismo correlativo FAC-, cuenta por cobrar, bitácora y auditoría que las demás facturas.
 *   El proveedor entrega, no el inventario: anularla no devuelve existencia.
 *
 * Corre dentro de la transacción de la aprobación: si falla, la aprobación y la orden se revierten.
 */
final class QuoteInvoiceIssuer
{
    /** @var array<string, string> tipo de servicio de la cotización → tipo de factura */
    private const INVOICE_TYPES = [
        'MEDICAMENTOS' => OperationMedicationInvoice::TYPE_MEDICATIONS,
        'LABORATORIOS' => OperationMedicationInvoice::TYPE_LABORATORIES,
        'IMAGENOLOGIA' => OperationMedicationInvoice::TYPE_IMAGING,
        'ESPECIALISTA' => OperationMedicationInvoice::TYPE_SPECIALIST,
    ];

    public static function supportsServiceType(?string $typeService): bool
    {
        return array_key_exists(mb_strtoupper(trim((string) $typeService)), self::INVOICE_TYPES);
    }

    /**
     * @throws MedicationInvoiceException
     */
    public static function issueForApprovedQuote(OperationCoordinationService $record, OperationQuoteGenerator $quote, ?User $user = null): ?OperationMedicationInvoice
    {
        if (! (bool) $quote->generates_invoice || (bool) $quote->is_courtesy) {
            return null;
        }

        $quoteLabel = ServiceOrderFlowTrace::quoteLabel($quote);

        if (filled($quote->operation_medication_invoice_id)) {
            $existing = OperationMedicationInvoice::query()->find($quote->operation_medication_invoice_id);

            if ($existing instanceof OperationMedicationInvoice) {
                return $existing;
            }
        }

        $existing = OperationMedicationInvoice::query()->where('operation_quote_generator_id', $quote->getKey())->first();

        if ($existing instanceof OperationMedicationInvoice) {
            $quote->forceFill(['operation_medication_invoice_id' => $existing->getKey()])->save();

            return $existing;
        }

        $invoiceType = self::INVOICE_TYPES[mb_strtoupper(trim((string) $quote->type_service))] ?? null;

        if ($invoiceType === null) {
            throw new MedicationInvoiceException('La cotización '.$quoteLabel.' es de '.$quote->type_service.': ese tipo de servicio no se factura al paciente.');
        }

        if (blank($quote->invoice_payer_type) || blank($quote->invoice_payer_name) || blank($quote->invoice_payer_address)) {
            throw new MedicationInvoiceException('La cotización '.$quoteLabel.' genera factura pero no tiene responsable de pago completo. Edítela e indique el responsable antes de aprobarla.');
        }

        $lines = self::lines($quote, $invoiceType);

        if ($lines === []) {
            return null;
        }

        $user ??= Auth::user();
        $userName = filled($user?->name) ? (string) $user->name : 'SISTEMA';
        $bcvRate = BcvOfficialRate::resolve() ?? CoordinationServiceQuoteManager::resolveBcvRateFromQuote($quote);

        if ($bcvRate === null || $bcvRate <= 0) {
            throw new MedicationInvoiceException('No fue posible obtener la tasa BCV del día para emitir la factura de la cotización '.$quoteLabel.'. Intente de nuevo en unos minutos.');
        }

        $record->loadMissing(['telemedicinePatient', 'telemedicineCase']);
        $patient = $record->telemedicinePatient;
        $totalUsd = round(array_sum(array_column($lines, 'price')), 2);

        $invoice = OperationMedicationInvoice::query()->create([
            'operation_coordination_service_id' => $record->getKey(),
            'operation_service_order_id' => $quote->operation_service_order_id,
            'operation_quote_generator_id' => $quote->getKey(),
            'telemedicine_patient_id' => $record->telemedicine_patient_id,
            'telemedicine_case_id' => $record->telemedicine_case_id,
            'patient_name' => QuoteInvoicePayer::options($record)['patient']['name'],
            'patient_identification' => filled($patient?->nro_identificacion) ? (string) $patient->nro_identificacion : null,
            'case_code' => filled($record->telemedicineCase?->code) ? mb_strtoupper((string) $record->telemedicineCase->code) : null,
            'payer_type' => $quote->invoice_payer_type,
            'payer_name' => $quote->invoice_payer_name,
            'payer_identification' => $quote->invoice_payer_identification,
            'payer_address' => $quote->invoice_payer_address,
            'status' => OperationMedicationInvoice::STATUS_PENDING_PAYMENT,
            'invoice_type' => $invoiceType,
            'total_usd' => $totalUsd,
            'bcv_rate' => round($bcvRate, 4),
            'total_ves' => round($totalUsd * $bcvRate, 2),
            'issued_at' => now(),
            'issued_by_user_id' => $user?->getKey(),
            'issued_by_name' => $userName,
        ]);

        $invoice->forceFill(['invoice_number' => OperationMedicationInvoice::formatNumber((int) $invoice->getKey())])->save();

        foreach ($lines as $line) {
            OperationMedicationInvoiceItem::query()->create([
                'operation_medication_invoice_id' => $invoice->getKey(),
                'item_type' => $invoiceType,
                'telemedicine_patient_medication_id' => $invoiceType === OperationMedicationInvoice::TYPE_MEDICATIONS ? $line['item_id'] : null,
                'source_item_id' => $line['item_id'],
                'prescribed_name' => $line['label'],
                'product_name' => $line['label'],
                'category_name' => $line['category'],
                'quantity' => 1,
                'unit_cost_usd' => $line['cost'],
                'profit_percentage' => $line['percentage'] ?? 0,
                'unit_price_usd' => $line['price'],
                'line_total_usd' => $line['price'],
            ]);
        }

        OperationAccountsReceivable::query()->create([
            'operation_coordination_service_id' => $record->getKey(),
            'telemedicine_patient_id' => $record->telemedicine_patient_id,
            'telemedicine_case_id' => $record->telemedicine_case_id,
            'operation_medication_invoice_id' => $invoice->getKey(),
            'operation_quote_generator_id' => $quote->getKey(),
            'quote_number' => CoordinationServiceQuoteManager::formatCoordinationQuoteNumber((int) $quote->getKey()),
            'operation_service_order_id' => $quote->operation_service_order_id,
            'service_order_number' => filled($quote->operation_service_order_id)
                ? OperationServiceOrder::query()->whereKey($quote->operation_service_order_id)->value('order_number')
                : null,
            'quote_amount_usd' => $invoice->total_usd,
            'quote_amount_ves' => $invoice->total_ves,
            'bcv_rate' => $invoice->bcv_rate,
            'reassigned_by_user_id' => $user?->getKey(),
            'reassigned_by_analyst_name' => $userName,
            'status' => OperationAccountsReceivable::STATUS_PENDING_PAYMENT,
            'is_courtesy' => false,
            'created_by' => $userName,
            'updated_by' => $userName,
        ]);

        $quote->forceFill(['operation_medication_invoice_id' => $invoice->getKey()])->save();

        $payerLabel = OperationMedicationInvoice::payerLabel($quote->invoice_payer_type) ?? 'Responsable';
        $description = 'Factura '.$invoice->invoice_number.' emitida por '.$userName.' al aprobar la cotización '.$quoteLabel.'. '
            .'Responsable de pago: '.mb_strtolower($payerLabel).' ('.$quote->invoice_payer_name
            .(filled($quote->invoice_payer_identification) ? ', '.$quote->invoice_payer_identification : '').'). '
            .implode('; ', array_map(
                fn (array $line): string => $line['label'].' — US$ '.number_format($line['price'], 2, '.', ',')
                    .($line['percentage'] !== null ? ' ('.number_format($line['percentage'], 2, ',', '.').' %)' : ' (sin utilidad)'),
                $lines,
            ))
            .'. Total US$ '.number_format((float) $invoice->total_usd, 2, '.', ',')
            .' (Bs. '.number_format((float) $invoice->total_ves, 2, '.', ',').' a tasa BCV '.number_format((float) $invoice->bcv_rate, 2, '.', ',').').';

        OperationMedicationInvoiceEvent::query()->create([
            'operation_medication_invoice_id' => $invoice->getKey(),
            'event' => OperationMedicationInvoiceEvent::EVENT_ISSUED,
            'description' => $description,
            'payload' => [
                'source' => 'quote',
                'operation_quote_generator_id' => (int) $quote->getKey(),
                'payer_type' => $quote->invoice_payer_type,
                'item_ids' => array_column($lines, 'item_id'),
                'total_usd' => (float) $invoice->total_usd,
                'bcv_rate' => (float) $invoice->bcv_rate,
            ],
            'user_id' => $user?->getKey(),
            'user_name' => $userName,
        ]);

        MedicationInvoiceIssuer::appendCoordinationLog($record, $description, $userName, $user?->getKey());

        $invoiceId = (int) $invoice->getKey();
        $quoteId = (int) $quote->getKey();

        DB::afterCommit(function () use ($invoiceId, $quoteId, $record, $totalUsd, $user, $invoiceType): void {
            SecurityAudit::log(MedicationInvoiceIssuer::AUDIT_ISSUED, MedicationInvoiceIssuer::AUDIT_ROUTE, [
                'operation_medication_invoice_id' => $invoiceId,
                'operation_quote_generator_id' => $quoteId,
                'operation_coordination_service_id' => $record->getKey(),
                'telemedicine_case_id' => $record->telemedicine_case_id,
                'kind' => $invoiceType,
                'total_usd' => $totalUsd,
            ], $user);
        });

        return $invoice->refresh();
    }

    /**
     * Líneas cobrables de la cotización (sin las de cortesía), validadas.
     *
     * @return list<array{item_id: int, label: string, category: string, cost: float, percentage: float|null, price: float}>
     *
     * @throws MedicationInvoiceException
     */
    private static function lines(OperationQuoteGenerator $quote, string $invoiceType): array
    {
        $lines = [];
        $quoteLabel = ServiceOrderFlowTrace::quoteLabel($quote);

        foreach ((array) $quote->items as $item) {
            if (! is_array($item) || (bool) ($item['is_courtesy'] ?? false)) {
                continue;
            }

            $key = (string) ($item['key'] ?? '');
            $itemId = ClinicalServiceCatalog::idFromKey($key);
            $label = trim((string) ($item['label'] ?? ''));
            $cost = is_numeric($item['unit_price_usd'] ?? null) ? round((float) $item['unit_price_usd'], 2) : null;

            if ($itemId <= 0 || $label === '') {
                throw new MedicationInvoiceException('Un ítem de la cotización '.$quoteLabel.' no es válido. Edítela antes de aprobarla.');
            }

            if ($cost === null || $cost <= 0) {
                throw new MedicationInvoiceException('«'.$label.'» no tiene precio en la cotización '.$quoteLabel.'. Edítela antes de aprobarla.');
            }

            $percentage = QuoteLinePricing::percentage($item['profit_percentage'] ?? null);

            if ($percentage !== null && $percentage > QuoteLinePricing::MAX_PERCENTAGE) {
                throw new MedicationInvoiceException('El % de utilidad de «'.$label.'» no es válido. Edite la cotización '.$quoteLabel.'.');
            }

            $activeInvoice = OperationMedicationInvoiceItem::query()
                ->where('item_type', $invoiceType)
                ->where('source_item_id', $itemId)
                ->whereHas('invoice', fn ($query) => $query->where('status', '!=', OperationMedicationInvoice::STATUS_ANNULLED))
                ->with('invoice:id,invoice_number')
                ->first();

            if ($activeInvoice !== null) {
                throw new MedicationInvoiceException('«'.$label.'» ya tiene la factura vigente '.($activeInvoice->invoice?->invoice_number ?? '').'. Anúlela o quite el ítem antes de aprobar.');
            }

            $lines[] = [
                'item_id' => $itemId,
                'label' => $label,
                'category' => (string) ($item['category'] ?? ''),
                'cost' => $cost,
                'percentage' => $percentage,
                'price' => (float) QuoteLinePricing::salePrice($cost, $percentage),
            ];
        }

        return $lines;
    }
}
