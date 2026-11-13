<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Models\User;
use App\Support\Operations\NonCoveredMedicationAlerts;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Modal con sonido para los analistas TDG de Operaciones: medicamentos NO
 * CUBIERTOS recetados a un afiliado con Plan Especial de 30K, 40K o 50K.
 *
 * Vive en todas las páginas del panel (render hook persistente). Cada ciclo es
 * una consulta acotada y no vuelve a pintar si los avisos no cambiaron.
 */
class NonCoveredMedicationAlertModal extends Component
{
    public const POLL_SECONDS = 10;

    public const SOUND_EVENT = 'non-covered-medication-alert-arrived';

    /**
     * @var list<array<string, mixed>>
     */
    #[Locked]
    public array $alerts = [];

    /** @var list<string> */
    public array $selectedMedicationIds = [];

    #[Locked]
    public ?int $selectionAlertId = null;

    /**
     * Al entrar, la vista anuncia los avisos pendientes; los ciclos siguientes
     * anuncian solo los nuevos.
     */
    public function mount(): void
    {
        $this->loadAlerts(announce: false);
    }

    public function refreshAlerts(): void
    {
        $previous = $this->alertSignature();

        $this->loadAlerts(announce: true);

        if ($previous === $this->alertSignature()) {
            $this->skipRender();
        }
    }

    public function dismiss(int $alertId): void
    {
        $user = $this->user();

        if ($user === null) {
            return;
        }

        NonCoveredMedicationAlerts::dismiss($alertId, $user);

        $this->loadAlerts(announce: false);
    }

    public function manage(int $alertId): void
    {
        $user = $this->user();

        if ($user === null || ! NonCoveredMedicationAlerts::userCanReceive($user)) {
            return;
        }

        if ($this->selectionAlertId !== $alertId) {
            $this->loadAlerts(announce: false);

            return;
        }

        $result = NonCoveredMedicationAlerts::claim($alertId, $user, $this->selectedMedicationIds);

        if (! $result['ok'] || $result['url'] === null) {
            Notification::make()
                ->title('No se pudo abrir la gestión')
                ->body($result['message'])
                ->warning()
                ->send();

            $this->loadAlerts(announce: false);

            return;
        }

        $this->alerts = array_values(array_filter($this->alerts, fn (array $alert): bool => $alert['id'] !== $alertId));
        $this->syncSelection();

        $this->redirect($result['url'], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.operations.non-covered-medication-alert-modal', [
            'current' => $this->alerts[0] ?? null,
            'total' => count($this->alerts),
            'pollSeconds' => self::POLL_SECONDS,
            'soundEvent' => self::SOUND_EVENT,
        ]);
    }

    private function loadAlerts(bool $announce): void
    {
        $user = $this->user();
        $previousIds = array_column($this->alerts, 'id');

        $this->alerts = $user === null ? [] : NonCoveredMedicationAlerts::pendingFor($user);
        $this->syncSelection();

        $newIds = array_values(array_diff(array_column($this->alerts, 'id'), $previousIds));

        if ($announce && $newIds !== []) {
            $this->dispatch(self::SOUND_EVENT, ids: $newIds);
        }
    }

    /**
     * Al cambiar el aviso visible, todos sus medicamentos llegan marcados.
     */
    private function syncSelection(): void
    {
        $current = $this->alerts[0] ?? null;

        if ($current === null) {
            $this->selectionAlertId = null;
            $this->selectedMedicationIds = [];

            return;
        }

        $available = array_map(fn (array $medication): string => (string) $medication['id'], $current['medications']);

        if ($this->selectionAlertId !== $current['id']) {
            $this->selectionAlertId = $current['id'];
            $this->selectedMedicationIds = $available;

            return;
        }

        $this->selectedMedicationIds = array_values(array_intersect($this->selectedMedicationIds, $available));
    }

    private function alertSignature(): string
    {
        return md5((string) json_encode($this->alerts));
    }

    private function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
