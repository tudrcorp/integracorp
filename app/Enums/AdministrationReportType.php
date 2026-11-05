<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Reportes del Centro de Reportes de Administración.
 */
enum AdministrationReportType: string
{
    case CollectionsGeneral = 'collections_general';
    case CollectionsByStructure = 'collections_by_structure';
    case DroppedAffiliationCollections = 'dropped_affiliation_collections';
    case CommissionsDetail = 'commissions_detail';
    case CapitationBilling = 'capitation_billing';

    public function label(): string
    {
        return match ($this) {
            self::CollectionsGeneral => 'Cobranza por mes · General',
            self::CollectionsByStructure => 'Cobranza por mes · Por agencia o agente',
            self::DroppedAffiliationCollections => 'Cuotas de afiliaciones dadas de baja',
            self::CommissionsDetail => 'Detallado de comisiones',
            self::CapitationBilling => 'Pre-facturación de capitado',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CollectionsGeneral => 'Toda la cartera por cobrar: una fila por afiliación con su próxima cuota pendiente, ordenada por vencimiento.',
            self::CollectionsByStructure => 'La cobranza de una agencia con toda su estructura (agencias y agentes de su red) o de un agente, agrupada con subtotales.',
            self::DroppedAffiliationCollections => 'Cuotas por pagar de afiliaciones excluidas, inactivas o anuladas, con la fecha de baja y si vencen antes o después de ella, para decidir si se anulan o se mantienen como deuda.',
            self::CommissionsDetail => 'Una fila por comisión calculada con toda la estructura: agencia master, agencia general, agente, subagente y referidor, y lo que cobra cada uno en USD y VES.',
            self::CapitationBilling => 'Lo que se le paga a un proveedor Outsourcing en el mes: afiliados con su tarifa de capitado, resumen por tomador, prorrateo de nuevos afiliados, retiros, descuentos y tasa BCV. Se cierra el mes para dejarlo fijo.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CollectionsGeneral => 'heroicon-o-clipboard-document-list',
            self::CollectionsByStructure => 'heroicon-o-building-office-2',
            self::DroppedAffiliationCollections => 'heroicon-o-user-minus',
            self::CommissionsDetail => 'heroicon-o-banknotes',
            self::CapitationBilling => 'heroicon-o-receipt-percent',
        };
    }

    public function fileSlug(): string
    {
        return match ($this) {
            self::CollectionsGeneral => 'cobranza-general',
            self::CollectionsByStructure => 'cobranza-estructura',
            self::DroppedAffiliationCollections => 'cuotas-afiliaciones-de-baja',
            self::CommissionsDetail => 'detallado-comisiones',
            self::CapitationBilling => 'prefacturacion-capitado',
        };
    }

    /**
     * Qué cuenta cada fila del reporte, para los mensajes de la pantalla.
     *
     * @return array{0: string, 1: string}
     */
    public function rowNoun(): array
    {
        return match ($this) {
            self::DroppedAffiliationCollections => ['cuota', 'cuotas'],
            self::CommissionsDetail => ['comisión', 'comisiones'],
            self::CapitationBilling => ['afiliado', 'afiliados'],
            default => ['afiliación', 'afiliaciones'],
        };
    }

    /**
     * Los reportes de cobranza filtran por vencimiento de la cuota y por estatus de cobro; el
     * de comisiones, por fecha de cálculo y sin estatus de cobro.
     */
    public function isCommissions(): bool
    {
        return $this === self::CommissionsDetail;
    }

    /**
     * La pre-facturación de capitado no usa periodo ni estatus de cobro: va por proveedor y mes,
     * y exige su propio permiso.
     */
    public function isCapitation(): bool
    {
        return $this === self::CapitationBilling;
    }

    public function isGrouped(): bool
    {
        return $this === self::CollectionsByStructure;
    }

    /**
     * @return list<OperationReportFormat>
     */
    public function formats(): array
    {
        return [OperationReportFormat::Excel, OperationReportFormat::Csv, OperationReportFormat::Pdf];
    }
}
