<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

/**
 * Resultado de regenerar documentos de un caso.
 *
 * La acción es el plan B del médico cuando la cola de documentos falló, así que
 * no basta con «se encoló»: hay que decirle exactamente qué salió y qué no.
 */
final class TelemedicineCaseDocumentRegenerationResult
{
    /**
     * @param  list<string>  $generated  Claves de documento generadas.
     * @param  array<string, string>  $failed  Clave de documento => motivo del fallo.
     * @param  array<string, string>  $labels  Clave de documento => etiqueta legible.
     */
    public function __construct(
        public readonly array $generated,
        public readonly array $failed,
        public readonly array $labels,
    ) {}

    public function generatedCount(): int
    {
        return count($this->generated);
    }

    public function failedCount(): int
    {
        return count($this->failed);
    }

    public function allGenerated(): bool
    {
        return $this->failed === [] && $this->generated !== [];
    }

    public function noneGenerated(): bool
    {
        return $this->generated === [];
    }

    /**
     * Etiquetas legibles de los documentos que no se pudieron generar.
     *
     * @return list<string>
     */
    public function failedLabels(): array
    {
        return array_values(array_map(
            fn (string $key): string => $this->labels[$key] ?? $key,
            array_keys($this->failed),
        ));
    }

    /**
     * Etiquetas legibles de los documentos generados.
     *
     * @return list<string>
     */
    public function generatedLabels(): array
    {
        return array_values(array_map(
            fn (string $key): string => $this->labels[$key] ?? $key,
            $this->generated,
        ));
    }
}
