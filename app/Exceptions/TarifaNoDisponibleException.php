<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * El servicio devolvió 422: falta tarifa para alguna edad o plan.
 *
 * No es un fallo del sistema, es un dato que el usuario debe ver, así que se
 * muestra el motivo tal cual y el flujo continúa.
 */
final class TarifaNoDisponibleException extends RuntimeException
{
    /**
     * @param  list<array{plan?: string, motivo?: string}>  $faltantes
     */
    public function __construct(
        string $message,
        public readonly array $faltantes = [],
    ) {
        parent::__construct($message);
    }

    /**
     * Motivos en una sola línea, listos para una notificación.
     */
    public function motivos(): string
    {
        $motivos = [];

        foreach ($this->faltantes as $faltante) {
            $motivo = trim((string) ($faltante['motivo'] ?? ''));

            if ($motivo !== '') {
                $plan = trim((string) ($faltante['plan'] ?? ''));
                $motivos[] = $plan !== '' ? mb_strtoupper($plan).': '.$motivo : $motivo;
            }
        }

        return $motivos === [] ? $this->getMessage() : implode(' · ', $motivos);
    }
}
