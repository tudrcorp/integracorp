<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Reportes del generador de Operaciones.
 *
 * Los de servicios salen de `operation_service_statistics` (una fila por ítem
 * de servicio). Los de inventario salen del inventario propio (Diagnomóvil):
 * existencias por ubicación y el libro de entradas y salidas.
 */
enum OperationReportType: string
{
    case ServiceDetail = 'service_detail';
    case ByStatus = 'by_status';
    case ByProvider = 'by_provider';
    case Coverage = 'coverage';
    case Billing = 'billing';
    case BusinessLine = 'business_line';
    case CasesByBusinessLine = 'cases_by_business_line';
    case CasesByPatient = 'cases_by_patient';
    case Patients = 'patients';
    case DeniedCases = 'denied_cases';
    case FullTable = 'full_table';
    case InventoryGeneral = 'inventory_general';
    case InventoryLowStock = 'inventory_low_stock';
    case InventoryMovements = 'inventory_movements';
    case InventoryBalance = 'inventory_balance';
    case InventoryConsumption = 'inventory_consumption';
    case InventoryIdle = 'inventory_idle';
    case InventoryReconciliation = 'inventory_reconciliation';

    public const GROUP_SERVICES = 'services';

    public const GROUP_INVENTORY = 'inventory';

    public function label(): string
    {
        return match ($this) {
            self::ServiceDetail => 'Detalle de servicios',
            self::ByStatus => 'Servicios por estatus',
            self::ByProvider => 'Servicios por proveedor',
            self::Coverage => 'Cobertura',
            self::Billing => 'Facturación y montos',
            self::BusinessLine => 'Línea de negocio y agencia',
            self::CasesByBusinessLine => 'Casos por línea de negocio',
            self::CasesByPatient => 'Casos por paciente',
            self::Patients => 'Pacientes',
            self::DeniedCases => 'Casos negados o anulados',
            self::FullTable => 'Tabla completa',
            self::InventoryGeneral => 'Inventario general',
            self::InventoryLowStock => 'Stock bajo y agotados',
            self::InventoryMovements => 'Movimientos (kárdex)',
            self::InventoryBalance => 'Saldos por producto',
            self::InventoryConsumption => 'Consumo por producto',
            self::InventoryIdle => 'Productos sin movimiento',
            self::InventoryReconciliation => 'Conciliación de existencias',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ServiceDetail => 'Todo lo gestionado en el periodo, un servicio por fila.',
            self::ByStatus => 'Cuántos servicios hay pendientes, en gestión y finalizados.',
            self::ByProvider => 'Carga y montos por proveedor de servicio y de gestión.',
            self::Coverage => 'Cubierto frente a no cubierto, por tipo de servicio.',
            self::Billing => 'Neto, cotizado, descuento y facturado; con y sin factura.',
            self::BusinessLine => 'Corporativos e individuales, por agencia.',
            self::CasesByBusinessLine => 'Casos, pacientes y servicios de cada línea de negocio.',
            self::CasesByPatient => 'Cuántos casos y servicios tuvo cada paciente.',
            self::Patients => 'Listado de pacientes atendidos con sus datos de contacto.',
            self::DeniedCases => 'Servicios de casos negados, anulados o reversados.',
            self::FullTable => 'Todas las columnas y todos los registros, sin filtros. Solo CSV.',
            self::InventoryGeneral => 'Existencia actual de cada producto por ubicación, con valor y estado.',
            self::InventoryLowStock => 'Qué reponer: productos en el umbral o agotados y para cuántos días alcanzan.',
            self::InventoryMovements => 'Cada entrada y salida del periodo, con motivo, caso y responsable.',
            self::InventoryBalance => 'Saldo inicial, entradas, salidas y saldo final por producto y ubicación.',
            self::InventoryConsumption => 'Unidades consumidas por producto y motivo, con promedio diario.',
            self::InventoryIdle => 'Productos con existencia que no tuvieron salidas en el periodo.',
            self::InventoryReconciliation => 'Diferencias entre almacén, ficha de inventario y movimientos registrados.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ServiceDetail => 'heroicon-o-queue-list',
            self::ByStatus => 'heroicon-o-chart-pie',
            self::ByProvider => 'heroicon-o-building-office-2',
            self::Coverage => 'heroicon-o-shield-check',
            self::Billing => 'heroicon-o-banknotes',
            self::BusinessLine => 'heroicon-o-briefcase',
            self::CasesByBusinessLine => 'heroicon-o-rectangle-group',
            self::CasesByPatient => 'heroicon-o-user-circle',
            self::Patients => 'heroicon-o-users',
            self::DeniedCases => 'heroicon-o-no-symbol',
            self::FullTable => 'heroicon-o-circle-stack',
            self::InventoryGeneral => 'heroicon-o-archive-box',
            self::InventoryLowStock => 'heroicon-o-exclamation-triangle',
            self::InventoryMovements => 'heroicon-o-arrows-right-left',
            self::InventoryBalance => 'heroicon-o-scale',
            self::InventoryConsumption => 'heroicon-o-arrow-trending-down',
            self::InventoryIdle => 'heroicon-o-pause-circle',
            self::InventoryReconciliation => 'heroicon-o-clipboard-document-check',
        };
    }

    /**
     * Sección del selector: servicios o inventario.
     */
    public function group(): string
    {
        return str_starts_with($this->value, 'inventory_') ? self::GROUP_INVENTORY : self::GROUP_SERVICES;
    }

    public function isInventory(): bool
    {
        return $this->group() === self::GROUP_INVENTORY;
    }

    /**
     * Reportes que se acotan por un periodo. Las fotos del inventario
     * (existencia, stock bajo, conciliación) muestran el estado de hoy.
     */
    public function usesPeriod(): bool
    {
        if (! $this->isInventory()) {
            return $this->usesFilters();
        }

        return in_array($this, [self::InventoryMovements, self::InventoryBalance, self::InventoryConsumption, self::InventoryIdle], true);
    }

    /**
     * Cómo se llama cada fila del reporte, para el resumen de la pantalla.
     *
     * @return array{0: string, 1: string} [singular, plural]
     */
    public function rowNoun(): array
    {
        return match ($this) {
            self::CasesByPatient, self::Patients => ['paciente', 'pacientes'],
            self::InventoryMovements => ['movimiento', 'movimientos'],
            self::InventoryBalance, self::InventoryReconciliation => ['producto por ubicación', 'productos por ubicación'],
            self::InventoryGeneral, self::InventoryLowStock, self::InventoryConsumption, self::InventoryIdle => ['producto', 'productos'],
            default => ['servicio', 'servicios'],
        };
    }

    /**
     * @return list<self>
     */
    public static function forGroup(string $group): array
    {
        return array_values(array_filter(self::cases(), fn (self $type): bool => $type->group() === $group));
    }

    /**
     * @return list<OperationReportFormat>
     */
    public function formats(): array
    {
        return $this === self::FullTable
            ? [OperationReportFormat::Csv]
            : [OperationReportFormat::Excel, OperationReportFormat::Csv, OperationReportFormat::Pdf];
    }

    public function supportsFormat(OperationReportFormat $format): bool
    {
        return in_array($format, $this->formats(), true);
    }

    /**
     * Una fila por servicio (puede ser grande) frente a un resumen agrupado.
     */
    public function isDetail(): bool
    {
        return in_array($this, [self::ServiceDetail, self::DeniedCases, self::FullTable, self::InventoryMovements], true);
    }

    /**
     * Reportes que pueden traer miles de filas (uno por servicio o por
     * paciente): su PDF se acota y se genera en cola.
     */
    public function hasManyRows(): bool
    {
        return $this->isDetail() || in_array($this, [self::CasesByPatient, self::Patients, self::InventoryGeneral, self::InventoryBalance], true);
    }

    /**
     * La tabla completa no se acota por periodo ni filtros: es el volcado íntegro.
     * Los de inventario siempre admiten sus filtros (ubicación, categoría, producto).
     */
    public function usesFilters(): bool
    {
        return $this !== self::FullTable;
    }

    public function fileSlug(): string
    {
        return str_replace('_', '-', $this->value);
    }
}
