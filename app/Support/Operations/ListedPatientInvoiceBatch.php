<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationCoordinationService;
use App\Models\OperationMedicationInvoice;
use App\Models\OperationQuoteGenerator;
use App\Models\OperationServiceOrder;
use App\Models\TelemedicineCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Emite las facturas a pacientes de un lote cerrado de órdenes de medicamentos.
 *
 * Cada orden ya tiene una cotización APROBADA de lo no cubierto, pero se aprobó
 * sin marcar «genera factura». La factura sale por el mismo emisor que al aprobar
 * una cotización: a nombre del paciente, con el total de venta de la cotización
 * (costo más el % de utilidad) y el N.º de siniestro del caso.
 *
 * No toca el estatus de la orden. Si la orden está caducada, la factura igual se
 * emite: el compromiso ya estaba cotizado y aprobado.
 */
final class ListedPatientInvoiceBatch
{
    public const ACTION_ISSUE = 'emitir';

    public const ACTION_SKIP = 'omitir';

    public const ACTION_ALREADY = 'ya_emitida';

    /** Diferencia aceptable entre la suma de líneas y el total guardado de la cotización. */
    public const TOTAL_TOLERANCE_USD = 0.05;

    private const MINIMUM_NAME_SCORE = 2;

    /**
     * Orden, N.º de siniestro y nombre tal como los entregó Operaciones.
     *
     * @return list<array{order: string, claim: string, name: string}>
     */
    public static function rows(): array
    {
        return [
            ['order' => 'ORD-0601', 'claim' => '3150510419591', 'name' => 'BRYANNY PAOLA SALAS PEREZ'],
            ['order' => 'ORD-0664', 'claim' => '3150510419610', 'name' => 'ELOISA ALVAREZ HERNANDEZ'],
            ['order' => 'ORD-0604', 'claim' => '3150510419782', 'name' => 'JESUS DAVID BRICEÑO TOVAR'],
            ['order' => 'ORD-0603', 'claim' => '3150510419784', 'name' => 'Dian Irwin bastidas Aguilar'],
            ['order' => 'ORD-0607', 'claim' => '3150510419785', 'name' => 'Nohemí margarita montilla de pineda'],
            ['order' => 'ORD-0625', 'claim' => '3150510419797', 'name' => 'Albanny Victoria Rojas Nelo'],
            ['order' => 'ORD-0620', 'claim' => '3150510419799', 'name' => 'Yasmina del rosario Briceño baptista'],
            ['order' => 'ORD-0621', 'claim' => '3150510419804', 'name' => 'MARIA CONCEPCION HERNANDEZ AZUAJE'],
            ['order' => 'ORD-0670', 'claim' => '3150510419806', 'name' => 'XIOREMAR MARGARITA PEREZ'],
            ['order' => 'ORD-0662', 'claim' => '3150510419809', 'name' => 'Sara crisney rojas alcantara'],
            ['order' => 'ORD-0671', 'claim' => '3150510419812', 'name' => 'Angel Custodio Araujo'],
            ['order' => 'ORD-0643', 'claim' => '3150510420663', 'name' => 'LEONOR DEL VALLE AIZPURUA'],
            ['order' => 'ORD-0744', 'claim' => '3150510419796', 'name' => 'Asnaldo Jesús Fulcado Arrias.'],
            ['order' => 'ORD-0863', 'claim' => '3150510420634', 'name' => 'GILBERTO RIVAS'],
            ['order' => 'ORD-0813', 'claim' => '3150510420633', 'name' => 'Handry Thais Castillo Caruci'],
            ['order' => 'ORD-0852', 'claim' => '3150510420635', 'name' => 'JOSE GREGORIO BRICEÑO SÚÁREZ'],
            ['order' => 'ORD-0815', 'claim' => '3150510420641', 'name' => 'julio Enrique Terán'],
            ['order' => 'ORD-0810', 'claim' => '3150510420642', 'name' => 'VALERIA ANDREINA ROA'],
            ['order' => 'ORD-0895', 'claim' => '3150510420643', 'name' => 'Eddy Rojas'],
            ['order' => 'ORD-0818', 'claim' => '3150510420652', 'name' => 'Emma Mosquera'],
            ['order' => 'ORD-0827', 'claim' => '3150510420657', 'name' => 'JUAN MEDINA'],
            ['order' => 'ORD-0800', 'claim' => '3150510420660', 'name' => 'JARIELYS ESTHER GIL CASTRO'],
            ['order' => 'ORD-0610', 'claim' => '3150510420824', 'name' => 'María Victoria Nelo de Rojas'],
            ['order' => 'ORD-0655', 'claim' => '3150510420840', 'name' => 'Jenny del Carmen Vargas Andrade'],
            ['order' => 'ORD-0615', 'claim' => '3150510420894', 'name' => 'OSMAR YFRAIN PACHECO'],
            ['order' => 'ORD-0622', 'claim' => '3150510420847', 'name' => 'ALEJANDRA GABRIELA HERNANDEZ CHIRINOS'],
            ['order' => 'ORD-0650', 'claim' => '3150510420906', 'name' => 'DISNEY JACKELINE MILLAMIZAR'],
            ['order' => 'ORD-0649', 'claim' => '3150510420900', 'name' => 'MIGDALIA JOSEFINA GOMEZ MORILLO'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $name): array
    {
        $folded = strtr(mb_strtoupper(trim($name)), self::accents());
        $parts = preg_split('/[^A-Z0-9]+/u', $folded) ?: [];
        $stopwords = ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'EL', 'Y', 'DA', 'DO', 'DAS', 'DOS'];

        $tokens = [];

        foreach ($parts as $part) {
            if (! is_string($part) || strlen($part) < 3 || in_array($part, $stopwords, true)) {
                continue;
            }

            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
    }

    public static function matchScore(string $expected, string $actual): int
    {
        return count(array_intersect(self::tokens($expected), self::tokens($actual)));
    }

    /**
     * Elige la orden cuyo paciente coincide con el nombre del listado.
     * Si dos candidatos empatan, no elige: hay que revisarlo a mano.
     *
     * @param  list<array{id: int, name: string}>  $candidates
     * @return array{id: int, name: string, score: int}|null
     */
    public static function pick(string $expected, array $candidates): ?array
    {
        $scored = [];

        foreach ($candidates as $candidate) {
            $score = self::matchScore($expected, $candidate['name']);

            if ($score < self::MINIMUM_NAME_SCORE) {
                continue;
            }

            $scored[] = [
                'id' => $candidate['id'],
                'name' => $candidate['name'],
                'score' => $score,
            ];
        }

        if ($scored === []) {
            return null;
        }

        usort($scored, fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        if (count($scored) > 1 && $scored[0]['score'] === $scored[1]['score']) {
            return null;
        }

        return $scored[0];
    }

    /**
     * Las cotizaciones anteriores al % por ítem guardan un % global. Se reparte
     * en cada línea, igual que al abrirlas para editar, para que la factura
     * cobre el total de venta y no solo el costo.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function itemsWithSaleProfit(array $items, mixed $legacyPercentage): array
    {
        if (QuoteLinePricing::usesItemPricing($items)) {
            return $items;
        }

        $percentage = QuoteLinePricing::percentage($legacyPercentage);

        return array_map(function (mixed $item) use ($percentage): mixed {
            if (! is_array($item)) {
                return $item;
            }

            if (QuoteLinePricing::percentage($item['profit_percentage'] ?? null) !== null) {
                return $item;
            }

            $item['profit_percentage'] = $percentage;

            return $item;
        }, $items);
    }

    /**
     * @param  array{order: string, claim: string, name: string}  $row
     * @return array{
     *     order: string,
     *     claim: string,
     *     expected_name: string,
     *     action: string,
     *     detail: string,
     *     patient: string|null,
     *     order_id: int|null,
     *     quote_id: int|null,
     *     case_id: int|null,
     *     coordination_id: int|null,
     *     total_usd: float|null,
     *     order_status: string|null
     * }
     */
    public static function assessRow(array $row): array
    {
        $base = [
            'order' => $row['order'],
            'claim' => TelemedicineCaseClaimNumber::normalize($row['claim']),
            'expected_name' => $row['name'],
            'action' => self::ACTION_SKIP,
            'detail' => '',
            'patient' => null,
            'order_id' => null,
            'quote_id' => null,
            'case_id' => null,
            'coordination_id' => null,
            'total_usd' => null,
            'order_status' => null,
        ];

        if (($problem = TelemedicineCaseClaimNumber::problem($base['claim'])) !== null) {
            return [...$base, 'detail' => $problem];
        }

        $orders = OperationServiceOrder::query()
            ->where('order_number', $row['order'])
            ->with(['operationCoordinationService.telemedicinePatient', 'operationCoordinationService.telemedicineCase'])
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            return [...$base, 'detail' => 'No existe esa orden de servicio.'];
        }

        $candidates = $orders
            ->map(function (OperationServiceOrder $order): array {
                $coordination = $order->operationCoordinationService;

                return [
                    'id' => (int) $order->getKey(),
                    'name' => trim((string) ($coordination?->telemedicinePatient?->full_name ?? '')),
                ];
            })
            ->all();

        $match = self::pick($row['name'], $candidates);

        if ($match === null) {
            $names = collect($candidates)->pluck('name')->filter()->implode(' · ');

            return [...$base, 'detail' => 'El paciente de la orden no coincide con el listado'.($names !== '' ? ': '.$names.'.' : '.')];
        }

        /** @var OperationServiceOrder $order */
        $order = $orders->firstWhere('id', $match['id']);
        $coordination = $order->operationCoordinationService;
        $case = $coordination?->telemedicineCase;
        $base['order_id'] = (int) $order->getKey();
        $base['patient'] = $match['name'];
        $base['order_status'] = (string) $order->status;
        $base['coordination_id'] = $coordination instanceof OperationCoordinationService ? (int) $coordination->getKey() : null;
        $base['case_id'] = $case instanceof TelemedicineCase ? (int) $case->getKey() : null;

        if (! $coordination instanceof OperationCoordinationService || ! $case instanceof TelemedicineCase) {
            return [...$base, 'detail' => 'La orden no tiene servicio médico o caso de telemedicina.'];
        }

        $currentClaim = TelemedicineCaseClaimNumber::normalize($case->claim_number);

        if ($currentClaim !== '' && $currentClaim !== $base['claim']) {
            return [...$base, 'detail' => 'El caso '.$case->code.' ya tiene otro N.º de siniestro ('.$currentClaim.').'];
        }

        $quotes = OperationQuoteGenerator::query()
            ->where('operation_service_order_id', $order->getKey())
            ->where('status', OperationQuoteGenerator::STATUS_APPROVED)
            ->where('is_courtesy', false)
            ->where('type_service', 'MEDICAMENTOS')
            ->orderBy('id')
            ->get();

        if ($quotes->isEmpty()) {
            return [...$base, 'detail' => 'No hay una cotización de medicamentos aprobada para esta orden.'];
        }

        $open = $quotes->filter(function (OperationQuoteGenerator $quote): bool {
            if (blank($quote->operation_medication_invoice_id)) {
                return true;
            }

            $invoice = OperationMedicationInvoice::query()->find($quote->operation_medication_invoice_id);

            return ! $invoice instanceof OperationMedicationInvoice
                || $invoice->status === OperationMedicationInvoice::STATUS_ANNULLED;
        })->values();

        if ($open->isEmpty()) {
            $number = OperationMedicationInvoice::query()->find($quotes->first()?->operation_medication_invoice_id);

            return [
                ...$base,
                'action' => self::ACTION_ALREADY,
                'quote_id' => (int) $quotes->first()->getKey(),
                'detail' => 'Ya tiene la factura '.($number instanceof OperationMedicationInvoice ? $number->displayNumber() : 'vinculada').'.',
            ];
        }

        if ($open->count() !== 1) {
            return [...$base, 'detail' => 'Hay '.$open->count().' cotizaciones aprobadas sin factura. Hay que elegir una a mano.'];
        }

        if (filled($open->first()->operation_medication_invoice_id)) {
            return [...$base, 'quote_id' => (int) $open->first()->getKey(), 'detail' => 'La cotización apunta a una factura anulada. No se emite otra automáticamente.'];
        }

        /** @var OperationQuoteGenerator $quote */
        $quote = $open->first();
        $items = self::itemsWithSaleProfit(is_array($quote->items) ? $quote->items : [], $quote->porcentaje_ganancia);
        $totals = QuoteLinePricing::totals($items);
        $saleTotal = $totals['total'];
        $quoteTotal = round((float) $quote->total, 2);

        if ($saleTotal === null || $saleTotal <= 0 || $totals['priced'] !== $totals['count']) {
            return [...$base, 'quote_id' => (int) $quote->getKey(), 'detail' => 'La cotización no tiene precio de venta en todos los ítems.'];
        }

        if (abs($saleTotal - $quoteTotal) > self::TOTAL_TOLERANCE_USD) {
            return [
                ...$base,
                'quote_id' => (int) $quote->getKey(),
                'total_usd' => $saleTotal,
                'detail' => 'El total de las líneas (US$ '.number_format($saleTotal, 2, '.', '').') no cuadra con el total de la cotización (US$ '.number_format($quoteTotal, 2, '.', '').').',
            ];
        }

        try {
            QuoteInvoicePayer::snapshot($coordination, OperationMedicationInvoice::PAYER_PATIENT, null);
        } catch (MedicationInvoiceException $exception) {
            return [...$base, 'quote_id' => (int) $quote->getKey(), 'total_usd' => $saleTotal, 'detail' => $exception->getMessage()];
        }

        $detail = 'Cotización '.ServiceOrderFlowTrace::quoteLabel($quote).' · orden '.$order->status.' · US$ '.number_format($saleTotal, 2, '.', '');

        if (abs($saleTotal - $quoteTotal) >= 0.01) {
            $detail .= ' (la cotización guardó US$ '.number_format($quoteTotal, 2, '.', '').'; la diferencia es de redondeo)';
        }

        return [
            ...$base,
            'action' => self::ACTION_ISSUE,
            'quote_id' => (int) $quote->getKey(),
            'total_usd' => $saleTotal,
            'detail' => $detail,
        ];
    }

    /**
     * @param  array{order: string, claim: string, name: string}  $row
     *
     * @throws MedicationInvoiceException
     */
    public static function issue(array $row, User $user): OperationMedicationInvoice
    {
        $decision = self::assessRow($row);

        if ($decision['action'] !== self::ACTION_ISSUE || $decision['quote_id'] === null || $decision['case_id'] === null || $decision['coordination_id'] === null) {
            throw new MedicationInvoiceException($decision['detail'] !== '' ? $decision['detail'] : 'Esta fila no se puede facturar.');
        }

        return DB::transaction(function () use ($decision, $user): OperationMedicationInvoice {
            $quote = OperationQuoteGenerator::query()->whereKey($decision['quote_id'])->lockForUpdate()->first();
            $coordination = OperationCoordinationService::query()->whereKey($decision['coordination_id'])->lockForUpdate()->first();
            $case = TelemedicineCase::query()->whereKey($decision['case_id'])->lockForUpdate()->first();

            if (! $quote instanceof OperationQuoteGenerator || ! $coordination instanceof OperationCoordinationService || ! $case instanceof TelemedicineCase) {
                throw new MedicationInvoiceException('La cotización o el caso ya no están disponibles.');
            }

            if (filled($quote->operation_medication_invoice_id)) {
                $existing = OperationMedicationInvoice::query()->find($quote->operation_medication_invoice_id);

                if ($existing instanceof OperationMedicationInvoice && $existing->status !== OperationMedicationInvoice::STATUS_ANNULLED) {
                    return $existing;
                }

                if ($existing instanceof OperationMedicationInvoice) {
                    throw new MedicationInvoiceException('La cotización apunta a una factura anulada. No se emite otra automáticamente.');
                }
            }

            $currentClaim = TelemedicineCaseClaimNumber::normalize($case->claim_number);

            if ($currentClaim === '') {
                TelemedicineCaseClaimNumber::set($case, $decision['claim'], null, $user);
            } elseif ($currentClaim !== $decision['claim']) {
                throw new MedicationInvoiceException('El caso ya tiene otro N.º de siniestro ('.$currentClaim.').');
            }

            $stamped = self::itemsWithSaleProfit(is_array($quote->items) ? $quote->items : [], $quote->porcentaje_ganancia);
            $snapshot = QuoteInvoicePayer::snapshot($coordination, OperationMedicationInvoice::PAYER_PATIENT, null);

            $quote->items = $stamped;
            $quote->syncOriginalAttribute('items');
            $quote->forceFill([
                'generates_invoice' => true,
                ...$snapshot,
                'updated_by' => $user->name,
            ])->save();

            $quote->items = $stamped;
            $quote->syncOriginalAttribute('items');

            $invoice = QuoteInvoiceIssuer::issueForApprovedQuote($coordination, $quote, $user);

            if (! $invoice instanceof OperationMedicationInvoice) {
                throw new MedicationInvoiceException('No se emitió la factura de la orden '.$decision['order'].'.');
            }

            $order = OperationServiceOrder::query()->find($decision['order_id']);
            MedicationInvoiceIssuer::appendCoordinationLog(
                $coordination,
                'Factura '.$invoice->displayNumber().' registrada por '.$user->name
                .' a nombre del paciente, desde la cotización ya aprobada de la orden '.($order?->order_number ?: $decision['order'])
                .'. N.º de siniestro '.$invoice->claim_number.'.',
                (string) $user->name,
                $user->getKey(),
            );

            return $invoice;
        });
    }

    /**
     * @return array<string, string>
     */
    private static function accents(): array
    {
        return [
            'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A',
            'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U',
            'Ñ' => 'N',
        ];
    }
}
