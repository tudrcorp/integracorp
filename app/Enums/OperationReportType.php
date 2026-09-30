<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Reportes del generador de Operaciones. Todos salen de
 * `operation_service_statistics`: una fila por ítem de servicio.
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
        };
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
        return in_array($this, [self::ServiceDetail, self::DeniedCases, self::FullTable], true);
    }

    /**
     * Reportes que pueden traer miles de filas (uno por servicio o por
     * paciente): su PDF se acota y se genera en cola.
     */
    public function hasManyRows(): bool
    {
        return $this->isDetail() || in_array($this, [self::CasesByPatient, self::Patients], true);
    }

    /**
     * La tabla completa no se acota por periodo ni filtros: es el volcado íntegro.
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
