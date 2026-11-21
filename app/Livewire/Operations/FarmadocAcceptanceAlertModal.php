<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Models\User;
use App\Support\Farmadoc\FarmadocAcceptanceAlerts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Modal con sonido para los analistas TDG de Operaciones y los superadmin: Farmadoc
 * aceptó un pedido. Vive en todas las páginas del panel (render hook persistente);
 * cada ciclo es una consulta acotada y no vuelve a pintar si nada cambió.
 */
class FarmadocAcceptanceAlertModal extends Component
{
    public const POLL_SECONDS = 15;

    public const SOUND_EVENT = 'farmadoc-acceptance-alert-arrived';

    /**
     * @var list<array<string, mixed>>
     */
    #[Locked]
    public array $alerts = [];

    public function mount(): void
    {
        $this->loadAlerts(announce: false);
    }

    public function refreshAlerts(): void
    {
        $previous = $this->signature();

        $this->loadAlerts(announce: true);

        if ($previous === $this->signature()) {
            $this->skipRender();
        }
    }

    public function dismiss(int $orderId): void
    {
        $user = $this->user();

        if ($user === null) {
            return;
        }

        FarmadocAcceptanceAlerts::dismiss($orderId, $user);

        $this->loadAlerts(announce: false);
    }

    /**
     * «Ver pedido»: cierra el aviso para este analista y abre la ficha del pedido.
     */
    public function openOrder(int $orderId): void
    {
        $alert = collect($this->alerts)->firstWhere('id', $orderId);
        $url = is_array($alert) ? ($alert['order_url'] ?? null) : null;

        $this->dismiss($orderId);

        if (is_string($url) && $url !== '') {
            $this->redirect($url, navigate: true);
        }
    }

    public function render(): View
    {
        return view('livewire.operations.farmadoc-acceptance-alert-modal', [
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

        $this->alerts = $user === null ? [] : FarmadocAcceptanceAlerts::pendingFor($user);

        $newIds = array_values(array_diff(array_column($this->alerts, 'id'), $previousIds));

        if ($announce && $newIds !== []) {
            $this->dispatch(self::SOUND_EVENT, ids: $newIds);
        }
    }

    private function signature(): string
    {
        return md5((string) json_encode($this->alerts));
    }

    private function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
