<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Documento al que pertenece un copy de correo. Cada uno tiene su pantalla, sus permisos y sus
 * variables: un copy del aviso de cobro nunca aparece al enviar un recibo, y al revés.
 */
enum DocumentEmailType: string
{
    case ReceiptPayment = 'RECIBO_PAGO';
    case CollectionNotice = 'AVISO_COBRO';

    public function label(): string
    {
        return match ($this) {
            self::ReceiptPayment => 'Recibo de pago',
            self::CollectionNotice => 'Aviso de cobro',
        };
    }

    public function defaultSubject(): string
    {
        return match ($this) {
            self::ReceiptPayment => 'Recibo de pago Nro. {nro_recibo}',
            self::CollectionNotice => 'Aviso de cobro Nro. {nro_aviso}',
        };
    }

    /**
     * @return array<string, string> variable => descripción
     */
    public function variables(): array
    {
        return match ($this) {
            self::ReceiptPayment => [
                'afiliado' => 'Nombre del afiliado o de la empresa',
                'nro_recibo' => 'Número del recibo de pago',
                'monto' => 'Monto total del recibo (US$)',
                'fecha_emision' => 'Fecha de emisión del recibo',
                'plan' => 'Plan de la venta',
                'metodo_pago' => 'Método de pago',
                'agente' => 'Nombre del agente',
                'agencia' => 'Nombre de la agencia',
            ],
            self::CollectionNotice => [
                'afiliado' => 'Nombre del afiliado o de la empresa (el del aviso)',
                'nro_aviso' => 'Número del aviso de cobro',
                'plan' => 'Plan (o planes, si es corporativa)',
                'periodo_desde' => 'Inicio del período de cobro del aviso',
                'periodo_hasta' => 'Fin del período de cobro del aviso',
                'monto' => 'Monto a pagar (US$)',
                'fecha_emision' => 'Fecha de emisión del aviso',
                'frecuencia' => 'Frecuencia de pago',
                'agente' => 'Nombre del agente',
                'agencia' => 'Nombre de la agencia',
            ],
        };
    }

    /**
     * Valores de ejemplo para la vista previa de un copy fuera de un documento real.
     *
     * @return array<string, string>
     */
    public function sampleValues(): array
    {
        return match ($this) {
            self::ReceiptPayment => [
                'afiliado' => 'MARGARITA TORRES',
                'nro_recibo' => '11-001458',
                'monto' => 'US$163.50',
                'fecha_emision' => '04/11/2026',
                'plan' => 'PLAN ESPECIAL',
                'metodo_pago' => 'ZELLE',
                'agente' => 'Agente de ejemplo',
                'agencia' => 'Agencia de ejemplo',
            ],
            self::CollectionNotice => [
                'afiliado' => 'ALBERTO MARTINEZ SANTAMARTA',
                'nro_aviso' => '11-000932',
                'plan' => 'PLAN INICIAL',
                'periodo_desde' => '03/11/2026',
                'periodo_hasta' => '02/05/2027',
                'monto' => 'US$80.00',
                'fecha_emision' => '09/11/2026',
                'frecuencia' => 'SEMESTRAL',
                'agente' => 'Agente de ejemplo',
                'agencia' => 'Agencia de ejemplo',
            ],
        };
    }
}
