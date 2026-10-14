<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Enums\CrmCaseOutcome;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\CrmHandoffEnvelope;
use App\Models\User;
use App\Support\CrmInbox\CrmAnalystName;
use App\Support\CrmInbox\CrmHandoffAssign;
use App\Support\CrmInbox\CrmHandoffClose;
use App\Support\CrmInbox\CrmHandoffFollowUp;
use App\Support\CrmInbox\CrmHandoffMove;
use App\Support\CrmInbox\CrmHandoffOutcome;
use App\Support\CrmInbox\CrmHandoffRelay;
use App\Support\CrmInbox\CrmHandoffRelease;
use App\Support\CrmInbox\CrmHandoffReply;
use App\Support\CrmInbox\CrmHandoffTakeover;
use App\Support\CrmInbox\CrmInboxAreas;
use App\Support\CrmInbox\CrmInboxClient360;
use App\Support\CrmInbox\CrmInboxColleagues;
use App\Support\CrmInbox\CrmInboxCopilot;
use App\Support\CrmInbox\CrmInboxDirectory;
use App\Support\CrmInbox\CrmInboxHealth;
use App\Support\CrmInbox\CrmInboxHistory;
use App\Support\CrmInbox\CrmInboxLibrary;
use App\Support\CrmInbox\CrmInboxNotices;
use App\Support\CrmInbox\CrmInboxPresence;
use App\Support\CrmInbox\CrmInboxPulse;
use App\Support\CrmInbox\CrmInboxQueue;
use App\Support\CrmInbox\CrmInboxQuote;
use App\Support\CrmInbox\CrmInboxSupervisor;
use App\Support\CrmInbox\CrmInboxWatch;
use App\Support\CrmInbox\CrmPushSubscriptions;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

/**
 * Cola de atención. Tomar silencia al bot. Devolver le regresa la palabra.
 */
class AtencionWhatsapp extends Page
{
    use AuthorizesDepartmentNavigation;

    protected static ?string $navigationLabel = 'Atención WhatsApp';

    protected static ?string $title = 'Atención WhatsApp';

    protected ?string $heading = '';

    protected Width|string|null $maxContentWidth = Width::Full;

    protected static ?string $slug = 'atencion-whatsapp';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.crm.pages.atencion-whatsapp';

    public int $visible = CrmInboxQueue::PAGE_SIZE;

    #[Url(as: 'caso')]
    public ?string $selectedHandoffId = null;

    public bool $summaryOpen = false;

    public bool $colleaguesOpen = false;

    public bool $destinationsOpen = false;

    public bool $quoteOpen = false;

    public string $quoteHolder = '';

    public string $quoteAges = '';

    public string $quotePlan = 'inicial';

    /**
     * Montos que pidió el cliente; el PDF solo muestra estos.
     *
     * @var list<string>
     */
    public array $quoteCoverages = [];

    /**
     * Lo arma el servidor al calcular. Bloqueado: el navegador no puede cambiar el archivo ni el texto que se envía.
     *
     * @var array{id: string, control: string, total: string, plan: string, people: int, caption: string, coverages: list<int>, ages?: string}|null
     */
    #[Locked]
    public ?array $quoteDraft = null;

    /**
     * @var list<array{id: int, name: string}>
     */
    public array $colleagues = [];

    /**
     * @var list<array{area: string, label: string}>
     */
    public array $destinations = [];

    /**
     * @var array{status: 'match', kind_label: string, name: string, url: ?string, via?: string}|array{status: 'none', document?: string}|null
     */
    public ?array $directory = null;

    /**
     * Última huella pintada. El sondeo compara contra ella y no repinta si nada cambió.
     */
    #[Locked]
    public ?string $fingerprint = null;

    /**
     * Último valor del pulso (Redis). Si no cambió, el sondeo ni siquiera consulta la base.
     */
    #[Locked]
    public ?string $pulse = null;

    #[Locked]
    public int $checkedAt = 0;

    #[Locked]
    public int $touchedAt = 0;

    /**
     * Ficha 360 del cliente: afiliación, pagos, renovación, agente. Se arma junto con la ficha.
     *
     * @var list<array{label: string, value: string, tone?: string}>
     */
    #[Locked]
    public array $client360 = [];

    /**
     * Conversaciones anteriores del mismo teléfono. Null mientras no se buscan.
     *
     * @var list<array<string, mixed>>|null
     */
    #[Locked]
    public ?array $history = null;

    /**
     * Hilos de conversaciones anteriores que el analista abrió.
     *
     * @var array<string, list<array{author: string, text: string, time: string}>>
     */
    #[Locked]
    public array $historyThreads = [];

    public bool $followUpOpen = false;

    public bool $relayOpen = false;

    /**
     * Compañeros a quienes el supervisor puede pasar un caso en atención.
     *
     * @var list<array{id: int, name: string, state: string}>
     */
    #[Locked]
    public array $relayTargets = [];

    /**
     * Segundos entre dos revisiones completas aunque el pulso no cambie: cubre lo que cambia con el
     * reloj (recordatorios que vencen, casos que pasan a rojo) y una caída de Redis.
     */
    public const FULL_CHECK_SECONDS = 30;

    public function mount(): void
    {
        $this->rememberSelection();
        $this->pulse = CrmInboxPulse::current();
        $this->checkedAt = time();
        $this->fingerprint = CrmInboxQueue::fingerprint($this->panelAreas(), $this->keepsUnlabeled(), $this->selectedHandoffId);
        $this->touchPresence();
    }

    public function select(string $handoffId): void
    {
        if (! preg_match('/^\d{1,18}$/', $handoffId)) {
            return;
        }

        $this->selectedHandoffId = $handoffId;
        $this->summaryOpen = false;
        $this->clearTransfers();
        $this->discardQuote();
        $this->rememberSelection();
    }

    /**
     * Abre el siguiente caso que te toca, o deja la conversación vacía si no hay.
     */
    private function focusNext(): void
    {
        $this->selectedHandoffId = null;
        $this->summaryOpen = false;
        $this->directory = null;
        $this->clearTransfers();
        $this->discardQuote();

        $rows = CrmInboxQueue::snapshot($this->panelAreas(), $this->keepsUnlabeled(), $this->visible)['rows'];

        foreach (CrmInboxQueue::lanes($rows, $this->analystId()) as $lane) {
            if ($lane['key'] === 'reply' && $lane['rows'] !== []) {
                $this->selectedHandoffId = (string) $lane['rows'][0]['handoff_id'];
                $this->rememberSelection();

                return;
            }
        }
    }

    /**
     * Sondeo de la bandeja: una consulta de agregados. Solo repinta si la cola o el hilo abierto cambiaron.
     */
    public function heartbeat(): void
    {
        $this->touchPresence();
        $pulse = CrmInboxPulse::current();
        $now = time();

        if ($pulse !== null && $pulse === $this->pulse && $now - $this->checkedAt < self::FULL_CHECK_SECONDS) {
            $this->skipRender();

            return;
        }

        $this->pulse = $pulse;
        $this->checkedAt = $now;
        $current = CrmInboxQueue::fingerprint($this->panelAreas(), $this->keepsUnlabeled(), $this->selectedHandoffId);

        if ($current === $this->fingerprint) {
            $this->skipRender();

            return;
        }

        $this->fingerprint = $current;
        $this->retryDirectoryWithNewDocument();
    }

    /**
     * La ficha se busca aparte, después de abrir el caso, para que elegir un caso responda de inmediato.
     */
    public function loadDirectory(): void
    {
        if ($this->directory !== null) {
            return;
        }

        $envelope = $this->selectedEnvelope();

        if ($envelope === null) {
            return;
        }

        $match = CrmInboxDirectory::match((string) $envelope->phone);
        $card = $this->directoryCard((string) $envelope->phone, $match);

        if ($match === null) {
            $case = $this->openCase();
            $document = $case !== null ? CrmInboxCopilot::detectedDocument($case) : null;

            if ($document !== null) {
                $match = CrmInboxDirectory::matchDocument($document);
                $card = $this->documentCard($document, $match);
            }
        }

        $this->directory = $card;
        $this->client360 = $match !== null ? CrmInboxClient360::forMatch($match) : [];
        $this->history = CrmInboxHistory::forPhone((string) $envelope->phone, (string) $envelope->handoff_id);
    }

    /**
     * Abre una conversación anterior del mismo cliente dentro del panel lateral.
     */
    public function openHistory(string $handoffId): void
    {
        $envelope = $this->selectedEnvelope();

        if ($envelope === null || isset($this->historyThreads[$handoffId])) {
            return;
        }

        $thread = CrmInboxHistory::thread((string) $envelope->phone, $handoffId);

        if ($thread !== null) {
            $this->historyThreads = array_slice([$handoffId => $thread] + $this->historyThreads, 0, 3, true);
        }
    }

    /**
     * Lo que SolIA y el cliente ya dijeron, armado para el panel del copiloto. Solo lee.
     *
     * @param  array<string, mixed>  $case
     * @return array<string, mixed>|null
     */
    public function copilot(array $case): ?array
    {
        $envelope = $this->selectedEnvelope();

        if ($envelope === null || (string) $envelope->handoff_id !== (string) ($case['handoff_id'] ?? '')) {
            return null;
        }

        return CrmInboxCopilot::brief(
            $envelope,
            $case,
            $this->analystId(),
            $this->directory === null ? null : $this->directory['status'] === 'match',
            $this->slaWarnSeconds(),
            now(),
        );
    }

    /**
     * Ejecuta la sugerencia que el servidor calcula ahora, no la que mandó el navegador.
     */
    public function useSuggestion(string $key): void
    {
        $suggestion = $this->currentSuggestion($key);

        if ($suggestion === null) {
            return;
        }

        if ($suggestion['action'] === 'quote') {
            $this->prefillQuote();
        }

        $envelope = $this->selectedEnvelope();

        if ($envelope !== null) {
            CrmInboxCopilot::record($envelope, $key, 'used', $this->analystId());
        }
    }

    public function dismissSuggestion(string $key): void
    {
        if ($this->currentSuggestion($key) === null) {
            return;
        }

        $envelope = $this->selectedEnvelope();

        if ($envelope !== null) {
            CrmInboxCopilot::record($envelope, $key, 'dismissed', $this->analystId());
        }
    }

    /**
     * Abre el cotizador con los datos detectados. El analista los revisa y corrige antes de calcular.
     */
    public function prefillQuote(): void
    {
        $case = $this->openCase();

        if ($case === null || ! ($case['taken'] ?? false) || ($case['taken_by'] ?? null) !== $this->analystId()) {
            return;
        }

        $brief = $this->copilot($case);
        $prefill = $brief['quote'] ?? null;

        if (! is_array($prefill)) {
            $this->openQuote();

            return;
        }

        $this->forgetDraftFile();
        $this->quoteDraft = null;
        $this->applyPrefill($prefill);
        $this->openQuote();
    }

    /**
     * @param  array{holder: string, ages: string, plan: string, coverage: string}  $prefill
     */
    private function applyPrefill(array $prefill): void
    {
        $this->quoteHolder = mb_substr((string) $prefill['holder'], 0, 80);
        $this->quoteAges = (string) $prefill['ages'];
        $this->quotePlan = isset(CrmInboxQuote::PLANS[$prefill['plan']]) ? (string) $prefill['plan'] : 'inicial';
        $this->quoteCoverages = in_array((int) $prefill['coverage'], CrmInboxQuote::COVERAGES[$this->quotePlan] ?? [], true)
            ? [(string) (int) $prefill['coverage']]
            : [];
    }

    /**
     * Abre la charla con SolIA para poder saltar a la frase de la que salió un dato.
     */
    public function openSummary(): void
    {
        $this->summaryOpen = true;
    }

    public function announce(string $title, string $body, string $handoffId): void
    {
        CrmInboxNotices::toast($title, str_replace("\n", ' · ', $body), $handoffId);
    }

    public function showColleagues(): void
    {
        $this->destinationsOpen = false;
        $this->destinations = [];
        $envelope = $this->selectedEnvelope();

        if ($envelope === null || $envelope->taken_at !== null) {
            $this->colleaguesOpen = false;
            $this->colleagues = [];

            return;
        }

        $this->colleagues = CrmInboxColleagues::forArea($envelope->area, $this->analystId());
        $this->colleaguesOpen = true;
    }

    public function assignTo(int $userId): void
    {
        $envelope = $this->selectedEnvelope();

        if ($envelope === null) {
            return;
        }

        $result = app(CrmHandoffAssign::class)->assign($envelope, $userId, $this->analystId());

        if ($result['ok'] !== true) {
            Notification::make()
                ->title($result['reason'] === 'taken' ? 'Esta conversación está en atención' : 'No se pudo pasar el caso')
                ->body($result['reason'] === 'taken'
                    ? 'Ya no se puede pasar. Quien lo tomó sigue en el chat.'
                    : 'Elige a un compañero de este departamento.')
                ->danger()
                ->send();

            return;
        }

        $this->clearTransfers();
        $given = CrmAnalystName::given($result['name']);
        $given = $given !== '' ? $given : $result['name'];

        Notification::make()
            ->title($result['already'] ? 'Ya estaba para '.$given : 'Quedó para '.$given)
            ->body('Sigue en la cola. El cliente no recibió ningún mensaje.')
            ->success()
            ->send();
    }

    public function showDestinations(): void
    {
        $this->colleaguesOpen = false;
        $this->colleagues = [];
        $envelope = $this->selectedEnvelope();

        if ($envelope === null || $envelope->taken_at !== null) {
            $this->destinationsOpen = false;
            $this->destinations = [];

            return;
        }

        $this->destinations = CrmInboxAreas::destinations($envelope->area);
        $this->destinationsOpen = true;
    }

    public function moveTo(string $area): void
    {
        $envelope = $this->selectedEnvelope();

        if ($envelope === null) {
            return;
        }

        $result = app(CrmHandoffMove::class)->move($envelope, $area, $this->analystId());

        if ($result['ok'] !== true) {
            Notification::make()
                ->title($result['reason'] === 'taken' ? 'Esta conversación está en atención' : 'No se pudo pasar el caso')
                ->body($result['reason'] === 'taken'
                    ? 'Ya no se puede mover. Quien lo tomó sigue en el chat.'
                    : 'Elige otro equipo.')
                ->danger()
                ->send();

            return;
        }

        $this->focusNext();

        Notification::make()
            ->title('Pasó a '.$result['label'])
            ->body('Salió de esta bandeja. El cliente no recibió ningún mensaje.')
            ->success()
            ->send();
    }

    public function toggleSummary(): void
    {
        $this->summaryOpen = ! $this->summaryOpen;
    }

    public function loadMore(): void
    {
        $this->visible = min($this->visible + CrmInboxQueue::PAGE_SIZE, 200);
    }

    public function take(): void
    {
        $envelope = $this->selectedEnvelope();

        if ($envelope === null) {
            return;
        }

        $result = app(CrmHandoffTakeover::class)->claim($envelope, $this->analystId());

        if ($result['ok'] !== true) {
            $this->takeFailed($result['reason']);

            return;
        }

        if ($result['already'] === true) {
            Notification::make()
                ->title('El caso ya estaba tomado')
                ->body('El bot ya no responde a este número.')
                ->success()
                ->send();

            return;
        }

        $envelope->refresh();
        $introduced = app(CrmHandoffReply::class)->introduce($envelope, $this->analystId(), $this->analystName());
        $given = CrmAnalystName::given($this->analystName());

        if ($introduced['ok'] === true) {
            Notification::make()
                ->title('Caso tomado')
                ->body($given !== ''
                    ? 'El cliente ya sabe que lo atiende '.$given.'. El bot ya no responde a este número.'
                    : 'El bot ya no responde a este número.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Caso tomado')
            ->body('No se pudo decir tu nombre por WhatsApp. Reintenta la presentación en el hilo. El bot ya no responde.')
            ->warning()
            ->send();
    }

    /**
     * Toma el caso que más espera de los que te tocan: primero lo marcado para ti, luego lo libre.
     * Si otro analista lo toma en el mismo instante, prueba con el siguiente.
     */
    public function takeNext(): void
    {
        $userId = $this->analystId();

        if ($userId === null) {
            return;
        }

        if (CrmHandoffTakeover::atCapacity($userId)) {
            $this->takeFailed('cap');

            return;
        }

        $takeover = app(CrmHandoffTakeover::class);

        foreach (CrmInboxQueue::nextCandidates($this->panelAreas(), $this->keepsUnlabeled(), $userId) as $candidate) {
            $result = $takeover->claim($candidate, $userId);

            if ($result['ok'] !== true) {
                $this->takeFailed($result['reason']);

                return;
            }

            $candidate->refresh();

            if ((int) $candidate->taken_by !== $userId) {
                continue;
            }

            $this->select((string) $candidate->handoff_id);
            $introduced = app(CrmHandoffReply::class)->introduce($candidate, $userId, $this->analystName());

            $notice = Notification::make()->title('Caso tomado');

            if ($introduced['ok'] === true) {
                $notice->body('Es el que más esperaba. El cliente ya sabe que lo atiendes.')->success();
            } else {
                $notice->body('No se pudo decir tu nombre por WhatsApp. Reintenta la presentación en el hilo.')->warning();
            }

            $notice->send();

            return;
        }

        Notification::make()
            ->title('No hay casos esperando')
            ->body('Cuando llegue uno, suena el aviso.')
            ->success()
            ->send();
    }

    public function setPresence(string $status): void
    {
        $userId = $this->analystId();

        if ($userId === null || ! CrmInboxPresence::set($userId, $status)) {
            return;
        }

        Notification::make()
            ->title('Ahora estás: '.CrmInboxPresence::label($status))
            ->body(CrmInboxPresence::STATUSES[$status]['hint'])
            ->success()
            ->send();
    }

    /**
     * Te quedas con un caso en atención de un compañero que no responde o no está. El cliente sigue con una persona.
     */
    public function relay(): void
    {
        $this->relayTo((int) $this->analystId(), true);
    }

    public function showRelayTargets(): void
    {
        $envelope = $this->selectedEnvelope();

        if ($envelope === null || $envelope->taken_at === null || ! $this->isSupervisor()) {
            $this->relayOpen = false;
            $this->relayTargets = [];

            return;
        }

        $colleagues = CrmInboxColleagues::forArea($envelope->area, $envelope->taken_by !== null ? (int) $envelope->taken_by : null);
        $states = CrmInboxPresence::effective(array_column($colleagues, 'id'));

        $this->relayTargets = array_map(fn (array $colleague): array => [
            ...$colleague,
            'state' => CrmInboxPresence::label($states[$colleague['id']] ?? CrmInboxPresence::AVAILABLE),
        ], $colleagues);
        $this->relayOpen = true;
    }

    public function relayTo(int $userId, bool $self = false): void
    {
        $envelope = $this->selectedEnvelope();
        $actorId = $this->analystId();

        if ($envelope === null || $actorId === null || $userId <= 0) {
            return;
        }

        $supervisor = ! $self && $this->isSupervisor();

        if (! $self && ! $supervisor) {
            return;
        }

        $result = app(CrmHandoffRelay::class)->relay($envelope, $userId, $actorId, $supervisor);

        if ($result['ok'] !== true) {
            Notification::make()
                ->title('No se pudo pasar el caso')
                ->body(match ($result['reason']) {
                    'forbidden' => 'Solo se releva un caso sin respuesta o de alguien que no está. El supervisor puede pasarlo a quien sea.',
                    'cap' => 'Llegaste al tope de casos en atención. Cierra o devuelve alguno primero.',
                    'changed' => 'Alguien lo movió en este momento. Revisa el caso de nuevo.',
                    'same' => 'Esa persona ya lo atiende.',
                    default => 'Elige a alguien de este departamento.',
                })
                ->danger()
                ->send();

            return;
        }

        $this->relayOpen = false;
        $this->relayTargets = [];

        Notification::make()
            ->title($self ? 'Ahora lo atiendes tú' : 'Ahora lo atiende '.CrmAnalystName::given($result['name']))
            ->body('El bot sigue en pausa. Al cliente le llegó un aviso del cambio.')
            ->success()
            ->send();
    }

    public function setFollowUp(string $when, string $note = ''): void
    {
        $envelope = $this->selectedEnvelope();

        if ($envelope === null) {
            return;
        }

        $result = app(CrmHandoffFollowUp::class)->set($envelope, $this->analystId(), $when, $note);

        if ($result['ok'] !== true) {
            Notification::make()
                ->title('No se pudo programar el recordatorio')
                ->body($result['reason'] === 'invalid'
                    ? 'Elige una fecha futura, dentro de los próximos '.CrmHandoffFollowUp::MAX_DAYS.' días.'
                    : 'Solo quien atiende el caso puede programarlo.')
                ->danger()
                ->send();

            return;
        }

        $this->followUpOpen = false;

        Notification::make()
            ->title('Te lo recuerdo '.CrmHandoffFollowUp::label($result['at']))
            ->body('El caso sube a «Te toca responder» y te llega un aviso.')
            ->success()
            ->send();
    }

    public function clearFollowUp(): void
    {
        $envelope = $this->selectedEnvelope();

        if ($envelope === null || (int) $envelope->taken_by !== $this->analystId()) {
            return;
        }

        app(CrmHandoffFollowUp::class)->clear($envelope);
        $this->followUpOpen = false;
    }

    /**
     * Envía un documento aprobado de la biblioteca. El id del envío lo genera el navegador para que un doble clic no lo duplique.
     */
    public function sendDocument(string $replyId, int $documentId): void
    {
        $envelope = $this->selectedEnvelope();

        if ($envelope === null) {
            return;
        }

        $reply = app(CrmHandoffReply::class);
        $staged = $reply->stageDocument($envelope, $this->analystId(), $replyId, $documentId);

        if ($staged['ok'] !== true) {
            $this->replyNotice($staged['reason'] === 'missing' ? 'document_missing' : $staged['reason']);

            return;
        }

        $delivered = $reply->deliver($envelope, $replyId);

        if ($delivered['ok'] !== true && $delivered['reason'] !== 'invalid') {
            $this->replyNotice($delivered['reason']);
        }
    }

    public function countReply(int $id): void
    {
        CrmInboxLibrary::countReply($id);
        $this->skipRender();
    }

    /**
     * @return array{outcome: CrmCaseOutcome, note: ?string}|null
     */
    private function outcomeOrNotify(string $outcome, string $note): ?array
    {
        $parsed = CrmHandoffOutcome::parse($outcome, $note);

        if ($parsed['ok'] === true) {
            return ['outcome' => $parsed['outcome'], 'note' => $parsed['note']];
        }

        Notification::make()
            ->title($parsed['reason'] === 'outcome_note' ? 'Cuenta en una línea qué pasó' : 'Elige cómo terminó el caso')
            ->body('Con esto el equipo sabe qué se vende, qué se pierde y por qué.')
            ->danger()
            ->send();

        return null;
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

    /**
     * Marca que la bandeja sigue abierta, a lo sumo una vez por minuto.
     */
    private function touchPresence(): void
    {
        $userId = $this->analystId();

        if ($userId === null || time() - $this->touchedAt < CrmInboxPresence::TOUCH_EVERY) {
            return;
        }

        $this->touchedAt = time();
        CrmInboxPresence::touch($userId);
    }

    /**
     * Tablero de supervisión del mismo panel.
     */
    protected function supervisionUrl(): ?string
    {
        $page = match ($this->panelId()) {
            'operations' => \App\Filament\Operations\Pages\SupervisionWhatsapp::class,
            'administration' => \App\Filament\Administration\Pages\SupervisionWhatsapp::class,
            default => SupervisionWhatsapp::class,
        };

        try {
            return $page::getUrl(panel: $this->panelId());
        } catch (\Throwable) {
            return null;
        }
    }

    public function isSupervisor(): bool
    {
        if ($this->supervisorMemo !== null) {
            return $this->supervisorMemo;
        }

        $user = Auth::user();

        return $this->supervisorMemo = $user instanceof User && CrmInboxSupervisor::can($user, $this->panelId());
    }

    /**
     * Se calcula una vez por petición: no viaja al navegador.
     */
    protected ?bool $supervisorMemo = null;

    public function close(string $outcome = '', string $note = ''): void
    {
        $envelope = $this->selectedEnvelope();

        if ($envelope === null) {
            return;
        }

        $parsed = $this->outcomeOrNotify($outcome, $note);

        if ($parsed === null) {
            return;
        }

        $result = app(CrmHandoffClose::class)->close($envelope, $this->analystId(), $parsed['outcome'], $parsed['note']);

        if ($result['ok'] !== true) {
            Notification::make()
                ->title('Esta conversación está en atención')
                ->body('Devuélvela al bot para cerrarla. Así el cliente sabe que SolIA retoma.')
                ->danger()
                ->send();

            return;
        }

        $this->focusNext();

        Notification::make()
            ->title('Conversación cerrada')
            ->body('Ya no aparece en la bandeja. El cliente no recibió ningún mensaje.')
            ->success()
            ->send();
    }

    public function release(string $outcome = '', string $note = ''): void
    {
        $envelope = $this->selectedEnvelope();

        if ($envelope === null) {
            return;
        }

        if ($envelope->taken_at === null && $envelope->released_at === null) {
            Notification::make()
                ->title('Toma el caso antes de devolverlo')
                ->body('El bot sigue respondiendo hasta que lo tomes.')
                ->danger()
                ->send();

            return;
        }

        $parsed = $this->outcomeOrNotify($outcome, $note);

        if ($parsed === null) {
            return;
        }

        if ($envelope->released_at === null && $envelope->taken_at !== null) {
            $farewell = app(CrmHandoffReply::class)->farewell($envelope, $this->takerName($envelope));

            if ($farewell['ok'] !== true) {
                Notification::make()
                    ->title('No se pudo avisar al cliente')
                    ->body('Sigues en el chat. El bot no volvió a hablar. Pulsa devolver otra vez.')
                    ->danger()
                    ->send();

                return;
            }
        }

        $result = app(CrmHandoffRelease::class)->release($envelope, $parsed['outcome'], $parsed['note'], $this->analystId());

        if ($result['ok'] !== true) {
            Notification::make()
                ->title($result['reason'] === 'not_taken' ? 'Toma el caso antes de devolverlo' : 'No se pudo devolver el caso')
                ->body($result['reason'] === 'not_taken'
                    ? 'El bot sigue respondiendo hasta que lo tomes.'
                    : 'Sigues en el chat. El bot no volvió a hablar.')
                ->danger()
                ->send();

            return;
        }

        $this->focusNext();

        Notification::make()
            ->title($result['already'] ? 'El caso ya estaba devuelto' : 'El bot volvió a responder')
            ->body('Este número ya no está en tu atención.')
            ->success()
            ->send();
    }

    public function openQuote(): void
    {
        $case = $this->openCase();

        if ($case === null || ! ($case['taken'] ?? false)) {
            return;
        }

        if ($this->quoteAges === '' && $this->quoteCoverages === [] && $this->quoteDraft === null) {
            $prefill = $this->copilot($case)['quote'] ?? null;

            if (is_array($prefill)) {
                $this->applyPrefill($prefill);
            }
        }

        if ($this->quoteHolder === '' && ($case['name'] ?? '') !== '' && ($case['name'] ?? '') !== 'Sin nombre') {
            $this->quoteHolder = (string) $case['name'];
        }

        $this->quoteOpen = true;
    }

    public function updatedQuotePlan(): void
    {
        $allowed = CrmInboxQuote::COVERAGES[$this->quotePlan] ?? [];

        $this->quoteCoverages = array_values(array_filter(
            $this->quoteCoverages,
            static fn (mixed $amount): bool => in_array((int) $amount, $allowed, true),
        ));

        $this->forgetDraftFile();
        $this->quoteDraft = null;
    }

    public function previewQuote(): void
    {
        $envelope = $this->selectedEnvelope();

        if ($envelope === null) {
            return;
        }

        $this->forgetDraftFile();

        $result = app(CrmInboxQuote::class)->preview(
            $envelope,
            $this->quoteHolder,
            $this->quoteAges,
            $this->quotePlan,
            array_values(array_map(strval(...), $this->quoteCoverages)),
            $this->analystName(),
        );

        if ($result['ok'] !== true) {
            $this->quoteDraft = null;

            Notification::make()
                ->title('No se pudo cotizar')
                ->body($result['detail'])
                ->danger()
                ->send();

            return;
        }

        $this->quoteDraft = [
            ...$result['draft'],
            'ages' => mb_substr(trim($this->quoteAges), 0, 60),
        ];
    }

    public function sendQuote(): void
    {
        $envelope = $this->selectedEnvelope();
        $draft = $this->quoteDraft;

        if ($envelope === null || $draft === null) {
            return;
        }

        $reply = app(CrmHandoffReply::class);
        $staged = $reply->stageQuote($envelope, $this->analystId(), $draft['id'], $draft['caption'], [
            'control' => $draft['control'],
            'total' => $draft['total'],
            'plan' => $draft['plan'],
            'people' => $draft['people'],
            'ages' => $draft['ages'] ?? '',
            'coverages' => $draft['coverages'],
        ]);

        if ($staged['ok'] !== true) {
            $this->replyNotice($staged['reason']);

            return;
        }

        $delivered = $reply->deliver($envelope, $draft['id']);

        if ($delivered['ok'] !== true) {
            $this->replyNotice($delivered['reason']);

            return;
        }

        $this->quoteDraft = null;

        Notification::make()
            ->title('Propuesta enviada')
            ->body($draft['caption'].'. El cliente la recibe en este WhatsApp.')
            ->success()
            ->send();
    }

    public function discardQuote(): void
    {
        $this->forgetDraftFile();
        $this->quoteOpen = false;
        $this->quoteHolder = '';
        $this->quoteAges = '';
        $this->quotePlan = 'inicial';
        $this->quoteCoverages = [];
        $this->quoteDraft = null;
    }

    public function stageReply(string $replyId, string $text): void
    {
        $envelope = $this->selectedEnvelope();

        if ($envelope === null) {
            return;
        }

        $result = app(CrmHandoffReply::class)->stage($envelope, $this->analystId(), $replyId, $text, $this->analystName());

        if ($result['ok'] !== true) {
            $this->replyNotice($result['reason']);
        }
    }

    public function deliverReply(string $replyId): void
    {
        $envelope = $this->selectedEnvelope();

        if ($envelope === null) {
            return;
        }

        $result = app(CrmHandoffReply::class)->deliver($envelope, $replyId);

        if ($result['ok'] !== true && $result['reason'] !== 'invalid') {
            $this->replyNotice($result['reason']);
        }
    }

    public function saveNote(string $text): void
    {
        $envelope = $this->selectedEnvelope();

        if ($envelope === null) {
            return;
        }

        $result = app(CrmHandoffReply::class)->note($envelope, $this->analystId(), $text);

        if ($result['ok'] !== true) {
            $this->replyNotice($result['reason']);
        }
    }

    /**
     * @return array{count: int, oldest_waiting: ?string, rows: list<array<string, mixed>>}
     */
    public function board(): array
    {
        return CrmInboxQueue::snapshot($this->panelAreas(), $this->keepsUnlabeled(), $this->visible);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function openCase(): ?array
    {
        if ($this->selectedHandoffId === null) {
            return null;
        }

        $case = CrmInboxQueue::find($this->selectedHandoffId, $this->panelAreas(), $this->keepsUnlabeled());

        if ($case === null) {
            $this->selectedHandoffId = null;
            $this->directory = null;
        }

        return $case;
    }

    protected function panelId(): string
    {
        return 'business';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $warn = intdiv($this->slaWarnSeconds(), 60);
        $late = max($warn + 1, (int) config('crm-inbox.sla.late_minutes', 15));
        $userId = $this->analystId();
        $capacity = CrmHandoffTakeover::capacity();

        return [
            'viewerId' => $userId,
            'slaWarnSeconds' => $warn * 60,
            'slaLateSeconds' => $late * 60,
            'serverNow' => now()->getTimestamp(),
            'health' => CrmInboxHealth::status(),
            'presence' => CrmInboxPresence::chosen($userId),
            'presenceOptions' => CrmInboxPresence::STATUSES,
            'supervisor' => $this->isSupervisor(),
            'capacity' => $capacity,
            'activeCount' => $userId !== null && $capacity > 0 ? CrmHandoffTakeover::activeCount($userId) : 0,
            'outcomes' => CrmCaseOutcome::options(),
            'followUpPresets' => CrmHandoffFollowUp::PRESETS,
            'abandonMinutes' => CrmInboxWatch::abandonMinutes(),
            'supervisionUrl' => $this->isSupervisor() ? $this->supervisionUrl() : null,
        ];
    }

    /**
     * Documentos aprobados que se pueden enviar en este caso.
     *
     * @param  array<string, mixed>  $case
     * @return list<array{id: int, title: string, description: string, size: int}>
     */
    public function documentsFor(array $case): array
    {
        return CrmInboxLibrary::documents(isset($case['area']) && is_string($case['area']) ? $case['area'] : null);
    }

    /**
     * Respuestas rápidas del caso abierto, con el nombre del cliente, el tuyo y la propuesta ya puestos.
     * Salen de la Biblioteca de atención (del área del caso o generales); sin ninguna cargada, de configuración.
     *
     * @param  array<string, mixed>  $case
     * @return list<array{id: ?int, label: string, text: string}>
     */
    public static function quickReplies(array $case): array
    {
        $given = CrmAnalystName::given((string) ($case['name'] ?? ''));
        $given = $given !== '' && ($case['name'] ?? '') !== 'Sin nombre' ? $given : 'Hola';
        $context = is_array($case['context'] ?? null) ? $case['context'] : [];
        $quote = trim(implode(' · ', array_filter([
            $context['quote_control'] ?? null,
            isset($context['quote_total']) ? trim(config('crm-inbox.quote_currency', 'USD').' '.$context['quote_total']) : null,
        ])));
        $user = Auth::user();
        $analyst = $user instanceof User ? CrmAnalystName::given((string) $user->name) : '';

        return CrmInboxLibrary::quickReplies(
            isset($case['area']) && is_string($case['area']) ? $case['area'] : null,
            ['cliente' => $given, 'analista' => $analyst, 'propuesta' => $quote],
        );
    }

    /**
     * @return list<string>
     */
    protected function panelAreas(): array
    {
        return CrmInboxAreas::forPanel($this->panelId());
    }

    protected function keepsUnlabeled(): bool
    {
        return $this->panelId() === 'business';
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    public function savePushSubscription(array $subscription, bool $quiet = false): void
    {
        $userId = $this->analystId();
        $endpoint = $subscription['endpoint'] ?? null;
        $keys = $subscription['keys'] ?? null;
        $publicKey = is_array($keys) ? ($keys['p256dh'] ?? null) : null;
        $authToken = is_array($keys) ? ($keys['auth'] ?? null) : null;
        $encoding = $subscription['contentEncoding'] ?? 'aes128gcm';

        $valid = $userId !== null
            && is_string($endpoint)
            && str_starts_with($endpoint, 'https://')
            && strlen($endpoint) <= 500
            && filter_var($endpoint, FILTER_VALIDATE_URL) !== false
            && is_string($publicKey)
            && $publicKey !== ''
            && strlen($publicKey) <= 255
            && is_string($authToken)
            && $authToken !== ''
            && strlen($authToken) <= 255
            && is_string($encoding)
            && strlen($encoding) <= 32;

        if (! $valid) {
            if (! $quiet) {
                Notification::make()
                    ->title('No se pudo activar los avisos')
                    ->danger()
                    ->send();
            }

            return;
        }

        CrmPushSubscriptions::store((int) $userId, (string) $endpoint, (string) $publicKey, (string) $authToken, (string) $encoding);

        if ($quiet) {
            return;
        }

        Notification::make()
            ->title('Avisos activados')
            ->body('Este navegador quedó registrado.')
            ->success()
            ->send();
    }

    private function slaWarnSeconds(): int
    {
        return max(1, (int) config('crm-inbox.sla.warn_minutes', 5)) * 60;
    }

    /**
     * @return array{key: string, title: string, reason: string, action: string, text: ?string}|null
     */
    private function currentSuggestion(string $key): ?array
    {
        if (! in_array($key, CrmInboxCopilot::SUGGESTIONS, true)) {
            return null;
        }

        $case = $this->openCase();

        if ($case === null) {
            return null;
        }

        $suggestion = $this->copilot($case)['suggestion'] ?? null;

        return is_array($suggestion) && $suggestion['key'] === $key ? $suggestion : null;
    }

    /**
     * Si el cliente escribió una cédula nueva y la ficha seguía vacía, se vuelve a buscar.
     */
    private function retryDirectoryWithNewDocument(): void
    {
        if ($this->directory === null || $this->directory['status'] !== 'none' || $this->selectedHandoffId === null) {
            return;
        }

        $case = $this->openCase();
        $document = $case !== null ? CrmInboxCopilot::detectedDocument($case) : null;

        if ($document !== null && $document !== ($this->directory['document'] ?? null)) {
            $this->directory = null;
        }
    }

    private function analystId(): ?int
    {
        $id = Auth::id();

        return is_numeric($id) ? (int) $id : null;
    }

    private function analystName(): string
    {
        $user = Auth::user();

        return $user instanceof User ? (string) $user->name : '';
    }

    private function takerName(CrmHandoffEnvelope $envelope): string
    {
        $currentId = $this->analystId();

        if ($envelope->taken_by === null || ($currentId !== null && (int) $envelope->taken_by === $currentId)) {
            return $this->analystName();
        }

        $name = User::query()->whereKey((int) $envelope->taken_by)->value('name');

        return is_string($name) ? $name : '';
    }

    private function replyNotice(string $reason): void
    {
        $title = match ($reason) {
            'not_taken' => 'Toma el caso antes de escribir',
            'empty' => 'Escribe un mensaje antes de enviar',
            'too_long' => 'El mensaje es demasiado largo',
            'missing' => 'No se pudo enviar la propuesta',
            'document_missing' => 'Ese documento ya no está disponible',
            default => 'No se pudo enviar por WhatsApp',
        };

        $notice = Notification::make()->title($title)->danger();

        $body = match ($reason) {
            'not_taken' => 'El bot sigue respondiendo hasta que tomes el caso.',
            'missing' => 'Vuelve a calcularla. El archivo ya no está.',
            'document_missing' => 'Pídele al supervisor que lo revise en la Biblioteca de atención.',
            'unconfigured', 'unreachable', 'rejected', 'busy' => 'Quedó en el hilo. Puedes reintentar.',
            default => null,
        };

        if ($body !== null) {
            $notice->body($body);
        }

        $notice->send();
    }

    private function selectedEnvelope(): ?CrmHandoffEnvelope
    {
        if ($this->selectedHandoffId === null) {
            return null;
        }

        return CrmInboxQueue::queryFor($this->panelAreas(), $this->keepsUnlabeled())
            ->where('handoff_id', $this->selectedHandoffId)
            ->first();
    }

    private function forgetDraftFile(): void
    {
        $id = $this->quoteDraft['id'] ?? null;

        if (is_string($id)) {
            CrmInboxQuote::forgetUnsent($id);
        }
    }

    private function clearTransfers(): void
    {
        $this->colleaguesOpen = false;
        $this->destinationsOpen = false;
        $this->relayOpen = false;
        $this->followUpOpen = false;
        $this->colleagues = [];
        $this->destinations = [];
        $this->relayTargets = [];
    }

    private function rememberSelection(): void
    {
        if ($this->selectedHandoffId === null || ! preg_match('/^\d{1,18}$/', $this->selectedHandoffId)) {
            $this->selectedHandoffId = null;
            $this->directory = null;
            $this->client360 = [];
            $this->history = null;
            $this->historyThreads = [];

            return;
        }

        if ($this->selectedEnvelope() === null) {
            $this->selectedHandoffId = null;
        }

        $this->directory = null;
        $this->client360 = [];
        $this->history = null;
        $this->historyThreads = [];
    }

    /**
     * @return array{status: 'match', kind_label: string, name: string, url: ?string, via: string}|array{status: 'none', document: string}
     */
    private function documentCard(string $document, ?array $match): array
    {
        if ($match === null) {
            return ['status' => 'none', 'document' => $document];
        }

        return [
            'status' => 'match',
            'kind_label' => $match['kind'] === 'afiliado' ? 'Afiliación' : 'Agente',
            'name' => $match['name'],
            'url' => CrmInboxDirectory::url($match, $this->panelId()),
            'via' => 'document',
        ];
    }

    /**
     * @return array{status: 'match', kind_label: string, name: string, url: ?string}|array{status: 'none'}
     */
    private function directoryCard(string $phone, ?array $match): array
    {
        if ($match === null) {
            return ['status' => 'none'];
        }

        return [
            'status' => 'match',
            'kind_label' => $match['kind'] === 'afiliado' ? 'Afiliación' : 'Agente',
            'name' => $match['name'],
            'url' => CrmInboxDirectory::url($match, $this->panelId()),
        ];
    }
}
