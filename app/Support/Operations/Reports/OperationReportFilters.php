<?php

declare(strict_types=1);

namespace App\Support\Operations\Reports;

use App\Models\OperationServiceStatistic;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Periodo y filtros opcionales de un reporte de Operaciones.
 *
 * El periodo se aplica sobre `started_on` (fecha de inicio del servicio), que
 * está indexada. Todo lo que llega del formulario se normaliza aquí: el job en
 * cola vuelve a construir el objeto con `fromArray()`, así que nunca confía en
 * un estado ya armado.
 */
final readonly class OperationReportFilters
{
    public const PERIOD_TODAY = 'today';

    public const PERIOD_THIS_WEEK = 'this_week';

    public const PERIOD_THIS_MONTH = 'this_month';

    public const PERIOD_LAST_MONTH = 'last_month';

    public const PERIOD_CUSTOM = 'custom';

    public const STOCK_AVAILABLE = 'available';

    public const STOCK_LOW = 'low';

    public const STOCK_OUT = 'out';

    /**
     * @param  list<string>  $statuses
     * @param  list<string>  $coverages
     * @param  list<string>  $businessLines
     * @param  list<string>  $services
     * @param  list<string>  $serviceProviders
     * @param  list<int>  $ubications  Ubicaciones de inventario (ids).
     * @param  list<int>  $categories  Categorías de producto (ids).
     * @param  list<int>  $products  Productos (ids).
     * @param  list<string>  $stockStatuses  {@see self::stockStatusOptions()}
     * @param  list<string>  $invoiceStatuses  Estados de factura de medicamentos.
     * @param  list<string>  $invoiceTypes  Tipos de factura a paciente (medicamentos, laboratorios…).
     * @param  list<int>  $invoiceIds  Facturas elegidas a mano (acción masiva): si hay, manda sobre el periodo.
     * @param  list<string>  $orderStatuses  Estatus de orden de servicio elegidos {@see ServiceOrderStatusReportBuilder::statusOptions()}.
     * @param  list<string>  $documentStatuses  Documento del aliado de servicio (PENDIENTE / FACTURADO).
     * @param  list<int>  $orderSuppliers  Proveedores (ids) de las órdenes.
     * @param  list<string>  $payableStatuses  Estatus de cuenta por pagar {@see AccountsPayableStatusReportBuilder::statusOptions()}.
     * @param  list<string>  $payableSuppliers  Proveedor de la cuenta por pagar (nombre congelado en la factura).
     * @param  list<string>  $cashStatuses  Estatus del pago de contado (REGISTRADO / REVERSADO).
     * @param  list<string>  $cashKinds  Pago individual o masivo {@see CashPaymentReportBuilder::kindOptions()}.
     * @param  list<string>  $cashCurrencies  Moneda del pago (USD / VES).
     * @param  list<string>  $cashSuppliers  Proveedor del pago (nombre congelado).
     */
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public array $statuses = [],
        public array $coverages = [],
        public array $businessLines = [],
        public array $services = [],
        public array $serviceProviders = [],
        public array $ubications = [],
        public array $categories = [],
        public array $products = [],
        public array $stockStatuses = [],
        public array $invoiceStatuses = [],
        public array $invoiceIds = [],
        public array $invoiceTypes = [],
        public array $orderStatuses = [],
        public array $documentStatuses = [],
        public array $orderSuppliers = [],
        public array $payableStatuses = [],
        public array $payableSuppliers = [],
        public array $cashStatuses = [],
        public array $cashKinds = [],
        public array $cashCurrencies = [],
        public array $cashSuppliers = [],
    ) {
        if ($from->greaterThan($to)) {
            throw new InvalidArgumentException('La fecha inicial no puede ser posterior a la final.');
        }
    }

    /**
     * @return array<string, string>
     */
    public static function periodOptions(): array
    {
        return [
            self::PERIOD_TODAY => 'Hoy',
            self::PERIOD_THIS_WEEK => 'Esta semana',
            self::PERIOD_THIS_MONTH => 'Este mes',
            self::PERIOD_LAST_MONTH => 'Mes anterior',
            self::PERIOD_CUSTOM => 'Personalizado',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function stockStatusOptions(): array
    {
        return [
            self::STOCK_AVAILABLE => 'Disponible',
            self::STOCK_LOW => 'Stock bajo',
            self::STOCK_OUT => 'Agotado',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?CarbonImmutable $now = null): self
    {
        $now ??= CarbonImmutable::now();
        [$from, $to] = self::resolvePeriod(
            (string) ($data['period'] ?? self::PERIOD_THIS_MONTH),
            $data['from'] ?? null,
            $data['to'] ?? null,
            $now,
        );

        return new self(
            from: $from,
            to: $to,
            statuses: self::strings($data['statuses'] ?? []),
            coverages: self::strings($data['coverages'] ?? []),
            businessLines: self::strings($data['business_lines'] ?? []),
            services: self::strings($data['services'] ?? []),
            serviceProviders: self::strings($data['service_providers'] ?? []),
            ubications: self::ids($data['ubications'] ?? []),
            categories: self::ids($data['categories'] ?? []),
            products: self::ids($data['products'] ?? []),
            stockStatuses: array_values(array_intersect(
                self::strings($data['stock_statuses'] ?? []),
                array_keys(self::stockStatusOptions()),
            )),
            invoiceStatuses: array_values(array_intersect(
                self::strings($data['invoice_statuses'] ?? []),
                array_keys(MedicationInvoiceReportBuilder::statusOptions()),
            )),
            invoiceIds: array_slice(self::ids($data['invoice_ids'] ?? []), 0, MedicationInvoiceReportBuilder::MAX_SELECTED),
            invoiceTypes: array_values(array_intersect(
                self::strings($data['invoice_types'] ?? []),
                array_keys(\App\Models\OperationMedicationInvoice::typeOptions()),
            )),
            orderStatuses: array_values(array_intersect(
                self::strings($data['order_statuses'] ?? []),
                array_keys(ServiceOrderStatusReportBuilder::statusOptions()),
            )),
            documentStatuses: array_values(array_intersect(
                self::strings($data['document_statuses'] ?? []),
                array_keys(ServiceOrderStatusReportBuilder::documentOptions()),
            )),
            orderSuppliers: self::ids($data['order_suppliers'] ?? []),
            payableStatuses: array_values(array_intersect(
                self::strings($data['payable_statuses'] ?? []),
                array_keys(AccountsPayableStatusReportBuilder::statusOptions()),
            )),
            payableSuppliers: array_slice(self::strings($data['payable_suppliers'] ?? []), 0, 200),
            cashStatuses: array_values(array_intersect(self::strings($data['cash_statuses'] ?? []), array_keys(CashPaymentReportBuilder::statusOptions()))),
            cashKinds: array_values(array_intersect(self::strings($data['cash_kinds'] ?? []), array_keys(CashPaymentReportBuilder::kindOptions()))),
            cashCurrencies: array_values(array_intersect(self::strings($data['cash_currencies'] ?? []), array_keys(CashPaymentReportBuilder::currencyOptions()))),
            cashSuppliers: array_slice(self::strings($data['cash_suppliers'] ?? []), 0, 200),
        );
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function resolvePeriod(string $period, mixed $from, mixed $to, CarbonImmutable $now): array
    {
        return match ($period) {
            self::PERIOD_TODAY => [$now->startOfDay(), $now->endOfDay()],
            self::PERIOD_THIS_WEEK => [$now->startOfWeek(), $now->endOfDay()],
            self::PERIOD_LAST_MONTH => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            self::PERIOD_CUSTOM => [self::date($from, 'inicial')->startOfDay(), self::date($to, 'final')->endOfDay()],
            default => [$now->startOfMonth(), $now->endOfDay()],
        };
    }

    /**
     * @param  Builder<OperationServiceStatistic>  $query
     * @return Builder<OperationServiceStatistic>
     */
    public function apply(Builder $query): Builder
    {
        $query->whereBetween('started_on', [$this->from->toDateString(), $this->to->toDateString()]);

        if ($this->statuses !== []) {
            $query->whereIn('service_status', $this->statuses);
        }

        if ($this->coverages !== []) {
            $query->whereIn('coverage', $this->coverages);
        }

        if ($this->businessLines !== []) {
            $query->whereIn('business_line', $this->businessLines);
        }

        if ($this->services !== []) {
            $query->whereIn('service', $this->services);
        }

        if ($this->serviceProviders !== []) {
            $query->whereIn('service_provider', $this->serviceProviders);
        }

        return $query;
    }

    /**
     * Texto legible del periodo y los filtros, para el encabezado del PDF.
     *
     * @return list<string>
     */
    public function describe(): array
    {
        $lines = ['Periodo: '.$this->from->format('d/m/Y').' al '.$this->to->format('d/m/Y')];

        foreach ([
            'Estatus' => $this->statuses,
            'Cobertura' => $this->coverages,
            'Línea de negocio' => $this->businessLines,
            'Servicio' => $this->services,
            'Proveedor' => $this->serviceProviders,
        ] as $label => $values) {
            if ($values !== []) {
                $lines[] = $label.': '.implode(', ', $values);
            }
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'period' => self::PERIOD_CUSTOM,
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'statuses' => $this->statuses,
            'coverages' => $this->coverages,
            'business_lines' => $this->businessLines,
            'services' => $this->services,
            'service_providers' => $this->serviceProviders,
            'ubications' => $this->ubications,
            'categories' => $this->categories,
            'products' => $this->products,
            'stock_statuses' => $this->stockStatuses,
            'invoice_statuses' => $this->invoiceStatuses,
            'invoice_ids' => $this->invoiceIds,
            'invoice_types' => $this->invoiceTypes,
            'order_statuses' => $this->orderStatuses,
            'document_statuses' => $this->documentStatuses,
            'order_suppliers' => $this->orderSuppliers,
            'payable_statuses' => $this->payableStatuses,
            'payable_suppliers' => $this->payableSuppliers,
            'cash_statuses' => $this->cashStatuses,
            'cash_kinds' => $this->cashKinds,
            'cash_currencies' => $this->cashCurrencies,
            'cash_suppliers' => $this->cashSuppliers,
        ];
    }

    private static function date(mixed $value, string $label): CarbonImmutable
    {
        if (blank($value)) {
            throw new InvalidArgumentException("Indique la fecha {$label} del periodo.");
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (\Throwable) {
            throw new InvalidArgumentException("La fecha {$label} del periodo no es válida.");
        }
    }

    /**
     * @return list<int>
     */
    private static function ids(mixed $values): array
    {
        $ids = [];

        foreach ((array) $values as $value) {
            if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
                $id = (int) $value;

                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $values): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '', (array) $values),
            static fn (string $value): bool => $value !== '',
        )));
    }
}
