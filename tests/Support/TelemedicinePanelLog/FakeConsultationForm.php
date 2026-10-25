<?php

declare(strict_types=1);

namespace Tests\Support\TelemedicinePanelLog;

use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Página mínima con formulario `data`, como las de Filament, para probar la
 * bitácora del panel de Telemedicina sin montar el panel.
 *
 * Vive fuera del archivo de test porque Pint renombra las clases declaradas en
 * un test de Pest (incluso las anónimas con `extends`).
 */
class FakeConsultationForm extends Component
{
    /**
     * @var array<string, mixed>
     */
    public array $data = ['telemedicine_service_list_id' => 25, 'diagnostic' => 'NUNCA SE GUARDA'];

    public bool $isCreating = false;

    public ?int $telemedicineCaseId = null;

    /** Clave del error que `create()` lanza; null guarda sin mostrar nada. */
    public ?string $failWith = null;

    public static function make(bool $busy = false): self
    {
        $component = new self;
        $component->setId('tpe-test-'.uniqid());
        $component->isCreating = $busy;

        return $component;
    }

    /**
     * Imita el «Registrar» de Filament: lanza el error pedido o no hace nada visible.
     */
    public function create(): void
    {
        if ($this->failWith !== null) {
            throw ValidationException::withMessages([$this->failWith => 'Ese servicio no está incluido en el uso clínico de este plan.']);
        }
    }

    public function render(): string
    {
        return '<div></div>';
    }
}
