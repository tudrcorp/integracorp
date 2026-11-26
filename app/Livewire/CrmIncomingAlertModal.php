<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\CrmHandoffEnvelope;
use App\Models\User;
use App\Support\CrmInbox\CrmHandoffReply;
use App\Support\CrmInbox\CrmHandoffTakeover;
use App\Support\CrmInbox\CrmInboxAreas;
use App\Support\CrmInbox\CrmInboxPresence;
use App\Support\CrmInbox\CrmInboxPulse;
use App\Support\CrmInbox\CrmIncomingAlerts;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Modal «llegó una conversación» del CRM de WhatsApp. Vive en todas las páginas de los paneles
 * internos (render hook persistente) y no se puede pasar por alto: fondo oscurecido, sonido que se
 * repite y el detalle del caso para tomarlo en un clic.
 *
 * Cada sondeo lee solo el pulso del CRM en Redis; baja a la base cuando el pulso cambia, cuando vence
 * un «recordar en 1 min» o cada {@see FULL_CHECK_SECONDS} segundos.
 */
class CrmIncomingAlertModal extends Component
{
    public const POLL_SECONDS = 5;

    public const FULL_CHECK_SECONDS = 20;

    public const SOUND_EVENT = 'crm-incoming-arrived';

    /**
     * @var list<array<string, mixed>>
     */
    #[Locked]
    public array $items = [];

    #[Locked]
    public int $total = 0;

    #[Locked]
    public bool $loaded = false;

    #[Locked]
    public ?string $pulse = null;

    #[Locked]
    public int $checkedAt = 0;

    #[Locked]
    public ?int $wakeAt = null;

    #[Locked]
    public int $touchedAt = 0;

    #[Locked]
    public string $hostPanel = '';

    /**
     * Casos que ya sonaron en esta pestaña: volver a mostrarlos tras «recordar» no repite el aviso de llegada.
     *
     * @var list<string>
     */
    #[Locked]
    public array $announced = [];

    public function mount(): void
    {
        $this->hostPanel = (string) Filament::getCurrentPanel()?->getId();
    }

    public function refresh(): void
    {
        $user = $this->user();

        if ($user === null) {
            $this->skipRender();

            return;
        }

        $this->touchPresence($user);
        $pulse = CrmInboxPulse::current();
        $now = time();
        $wakeDue = $this->wakeAt !== null && $now >= $this->wakeAt;

        if ($this->loaded && $pulse !== null && $pulse === $this->pulse && ! $wakeDue && $now - $this->checkedAt < self::FULL_CHECK_SECONDS) {
            $this->skipRender();

            return;
        }

        $this->pulse = $pulse;
        $this->checkedAt = $now;
        $before = $this->signature();
        $this->load($user, announce: true);

        if ($before === $this->signature()) {
            $this->skipRender();
        }
    }

    /**
     * «Tomar y responder»: silencia al bot, presenta al analista y abre el chat.
     */
    public function take(string $handoffId): void
    {
        $user = $this->user();
        $envelope = $user !== null ? $this->envelope($handoffId) : null;

        if ($user === null || $envelope === null || ! CrmIncomingAlerts::canSee($user, $envelope)) {
            $this->gone('Esta conversación ya no está disponible.');

            return;
        }

        $userId = (int) $user->getKey();

        if ($envelope->released_at !== null || $envelope->closed_at !== null) {
            $this->gone('Esta conversación ya se cerró o volvió al bot.');

            return;
        }

        if ($envelope->taken_at !== null && (int) $envelope->taken_by !== $userId) {
            $this->takenByOther($envelope);

            return;
        }

        if ($envelope->taken_at === null) {
            $result = app(CrmHandoffTakeover::class)->claim($envelope, $userId);

            if ($result['ok'] !== true) {
                $this->takeFailed($result['reason']);

                return;
            }

            $envelope->refresh();

            if ((int) $envelope->taken_by !== $userId) {
                $this->takenByOther($envelope);

                return;
            }

            $introduced = app(CrmHandoffReply::class)->introduce($envelope, $userId, (string) $user->name);

            Notification::make()
                ->title('Caso tomado')
                ->body($introduced['ok'] === true
                    ? 'El cliente ya sabe que lo atiendes. El bot ya no responde a este número.'
                    : 'No se pudo decir tu nombre por WhatsApp. Reintenta la presentación en el hilo. El bot ya no responde.')
                ->status($introduced['ok'] === true ? 'success' : 'warning')
                ->send();
        }

        $this->openInbox($envelope);
    }

    /**
     * «Ver en la bandeja»: abre el caso sin tomarlo; vuelve a avisar en un minuto si nadie lo toma.
     */
    public function open(string $handoffId): void
    {
        $user = $this->user();
        $envelope = $user !== null ? $this->envelope($handoffId) : null;

        if ($user === null || $envelope === null || ! CrmIncomingAlerts::canSee($user, $envelope)) {
            $this->gone('Esta conversación ya no está disponible.');

            return;
        }

        CrmIncomingAlerts::snooze((int) $user->getKey(), [$handoffId]);
        $this->openInbox($envelope);
    }

    /**
     * «Recordar en 1 min»: cierra la modal; vuelve si en un minuto siguen sin tomar.
     */
    public function remind(): void
    {
        $user = $this->user();

        if ($user === null) {
            return;
        }

        CrmIncomingAlerts::snooze((int) $user->getKey(), array_column($this->items, 'id'));
        $this->load($user, announce: false);
    }

    /**
     * «Marcar como ausente»: deja de recibir casos nuevos hasta que vuelva a Disponible en la bandeja.
     */
    public function away(): void
    {
        $user = $this->user();

        if ($user === null || ! CrmInboxPresence::set((int) $user->getKey(), CrmInboxPresence::AWAY)) {
            return;
        }

        $this->items = [];
        $this->total = 0;

        Notification::make()
            ->title('Ahora estás: Ausente')
            ->body('No te llegan casos nuevos. Vuelve a «Disponible» desde Atención WhatsApp cuando puedas atender.')
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('livewire.crm-incoming-alert-modal', [
            'current' => $this->items[0] ?? null,
            'others' => array_slice($this->items, 1),
            'more' => max(0, $this->total - count($this->items)),
            'pollSeconds' => self::POLL_SECONDS,
            'soundEvent' => self::SOUND_EVENT,
            'warnSeconds' => max(1, (int) config('crm-inbox.sla.warn_minutes', 5)) * 60,
            'lateSeconds' => max(1, (int) config('crm-inbox.sla.late_minutes', 15)) * 60,
        ]);
    }

    private function load(User $user, bool $announce): void
    {
        $previousItems = collect($this->items)->keyBy('id')->all();
        $previous = array_map('strval', array_keys($previousItems));
        $userId = (int) $user->getKey();

        if (! in_array(CrmInboxPresence::chosen($userId), [CrmInboxPresence::AVAILABLE, CrmInboxPresence::BUSY], true)) {
            $this->items = [];
            $this->total = 0;
            $this->wakeAt = null;
            $this->loaded = true;

            return;
        }

        $pending = CrmIncomingAlerts::pendingFor($user);
        $this->items = $pending['items'];
        $this->total = $pending['total'];
        $this->wakeAt = $pending['wake_at'];
        $this->loaded = true;

        $current = array_column($this->items, 'id');

        if ($announce) {
            $this->announceTakenByOthers(array_values(array_diff($previous, $current)), $userId, $previousItems);
        }

        $fresh = array_values(array_diff($current, $this->announced));

        if ($fresh !== []) {
            $this->announced = array_slice([...$this->announced, ...$fresh], -200);
            $this->dispatch(self::SOUND_EVENT, ids: $fresh);
        }
    }

    /**
     * Quien tenía la modal abierta se entera de que otro tomó el caso, en vez de verlo desaparecer.
     *
     * @param  list<string>  $removed
     * @param  array<string, array<string, mixed>>  $previousItems
     */
    private function announceTakenByOthers(array $removed, int $userId, array $previousItems): void
    {
        if ($removed === []) {
            return;
        }

        CrmHandoffEnvelope::query()
            ->whereIn('handoff_id', $removed)
            ->whereNotNull('taken_at')
            ->whereNotNull('taken_by')
            ->where('taken_by', '!=', $userId)
            ->limit(5)
            ->get(['handoff_id', 'taken_by', 'payload'])
            ->each(fn (CrmHandoffEnvelope $envelope) => $this->notifyTaken($envelope, $previousItems[(string) $envelope->handoff_id] ?? null));
    }

    private function takenByOther(CrmHandoffEnvelope $envelope): void
    {
        $this->notifyTaken($envelope);
        $user = $this->user();

        if ($user !== null) {
            $this->load($user, announce: false);
        }
    }

    /**
     * @param  array<string, mixed>|null  $item
     */
    private function notifyTaken(CrmHandoffEnvelope $envelope, ?array $item = null): void
    {
        $taker = trim((string) User::query()->whereKey($envelope->taken_by)->value('name'));
        $payload = is_array($envelope->payload) ? $envelope->payload : [];
        $client = trim((string) ($item['name'] ?? $payload['name'] ?? ''));

        Notification::make()
            ->title(($taker !== '' ? $taker : 'Un compañero').' tomó la conversación')
            ->body($client !== '' && $client !== 'Sin nombre' ? 'Ya atiende a '.$client.'.' : 'Ya la está atendiendo.')
            ->info()
            ->send();
    }

    private function takeFailed(string $reason): void
    {
        if ($reason === 'cap') {
            Notification::make()
                ->title('Tienes '.CrmHandoffTakeover::capacity().' casos en atención')
                ->body('Es el tope por analista. Devuelve o cierra alguno antes de tomar otro.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('No se pudo tomar el caso')
            ->body('El bot sigue respondiendo. Intenta de nuevo en un momento.')
            ->danger()
            ->send();
    }

    private function gone(string $message): void
    {
        Notification::make()->title($message)->warning()->send();
        $user = $this->user();

        if ($user !== null) {
            $this->load($user, announce: false);
        }
    }

    private function openInbox(CrmHandoffEnvelope $envelope): void
    {
        $panel = CrmInboxAreas::panelFor($envelope->area);

        $this->redirect(CrmIncomingAlerts::inboxUrl($panel, (string) $envelope->handoff_id), navigate: $panel === $this->hostPanel);
    }

    private function envelope(string $handoffId): ?CrmHandoffEnvelope
    {
        if (preg_match('/^\d{1,18}$/', $handoffId) !== 1) {
            return null;
        }

        return CrmHandoffEnvelope::query()->where('handoff_id', $handoffId)->first();
    }

    private function touchPresence(User $user): void
    {
        if (time() - $this->touchedAt < CrmInboxPresence::TOUCH_EVERY) {
            return;
        }

        $this->touchedAt = time();
        CrmInboxPresence::touch((int) $user->getKey());
    }

    private function signature(): string
    {
        return md5((string) json_encode([$this->items, $this->total]));
    }

    private function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
