<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

/**
 * Avance de las acciones de una visita AMD dentro de la consulta.
 *
 * Obligatorias: el Informe AMD y el examen físico. Opcional: los archivos. La
 * tarjeta AMD, las notificaciones de cada acción y la revisión leen este mismo
 * objeto, así que nunca cuentan historias distintas. No bloquea el guardado:
 * solo orienta al médico.
 */
final class TelemedicineAmdVisitProgress
{
    public const REQUIRED_TOTAL = 2;

    public const COMPLETE_MESSAGE = 'Acciones AMD completas. Ya puede continuar con la consulta.';

    public function __construct(
        public readonly bool $hasInform,
        public readonly bool $hasExam,
        public readonly int $files = 0,
        public readonly ?string $examReport = null,
    ) {}

    public function completedRequired(): int
    {
        return (int) $this->hasInform + (int) $this->hasExam;
    }

    public function isComplete(): bool
    {
        return $this->completedRequired() === self::REQUIRED_TOTAL;
    }

    /**
     * Porcentaje de lo obligatorio, para la barra de avance.
     */
    public function percent(): int
    {
        return (int) round($this->completedRequired() * 100 / self::REQUIRED_TOTAL);
    }

    /**
     * Lo obligatorio que falta, en el orden en que conviene hacerlo.
     *
     * @return list<string>
     */
    public function missing(): array
    {
        return array_values(array_filter([
            $this->hasInform ? null : 'el Informe AMD',
            $this->hasExam ? null : 'el examen físico AMD',
        ]));
    }

    /**
     * Qué sigue: lo que falta o, si ya está todo, que puede continuar.
     */
    public function nextStepMessage(): string
    {
        $missing = $this->missing();

        if ($missing === []) {
            return self::COMPLETE_MESSAGE;
        }

        return 'Le falta: '.implode(' y ', $missing).'.';
    }

    /**
     * @return list<array{key: string, label: string, detail: string, done: bool, optional: bool}>
     */
    public function steps(): array
    {
        $report = $this->examReport ?? 'informe de esta consulta';

        return [
            [
                'key' => 'inform',
                'label' => 'Informe AMD',
                'detail' => $this->hasInform ? 'Registrado' : 'Pendiente',
                'done' => $this->hasInform,
                'optional' => false,
            ],
            [
                'key' => 'exam',
                'label' => 'Examen físico',
                'detail' => $this->hasExam ? "Cargado · se anexa al {$report}" : "Pendiente · se anexará al {$report}",
                'done' => $this->hasExam,
                'optional' => false,
            ],
            [
                'key' => 'files',
                'label' => 'Archivos AMD',
                'detail' => match (true) {
                    $this->files === 1 => '1 archivo cargado',
                    $this->files > 1 => "{$this->files} archivos cargados",
                    default => 'Opcional · ninguno cargado',
                },
                'done' => $this->files > 0,
                'optional' => true,
            ],
        ];
    }

    /**
     * Forma serializable para la revisión de la consulta.
     *
     * @return array{complete: bool, completed: int, total: int, message: string, steps: list<array{key: string, label: string, detail: string, done: bool, optional: bool}>}
     */
    public function toArray(): array
    {
        return [
            'complete' => $this->isComplete(),
            'completed' => $this->completedRequired(),
            'total' => self::REQUIRED_TOTAL,
            'message' => $this->nextStepMessage(),
            'steps' => $this->steps(),
        ];
    }
}
