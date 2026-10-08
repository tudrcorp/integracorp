<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Models\CrmHandoffEnvelope;
use App\Models\User;
use App\Support\CrmInbox\CrmAnalystName;
use App\Support\CrmInbox\CrmHandoffAssign;
use App\Support\CrmInbox\CrmHandoffClose;
use App\Support\CrmInbox\CrmHandoffMove;
use App\Support\CrmInbox\CrmHandoffRelease;
use App\Support\CrmInbox\CrmHandoffReply;
use App\Support\CrmInbox\CrmHandoffTakeover;
use App\Support\CrmInbox\CrmInboxAreas;
use App\Support\CrmInbox\CrmInboxColleagues;
use App\Support\CrmInbox\CrmInboxCopilot;
use App\Support\CrmInbox\CrmInboxDirectory;
use App\Support\CrmInbox\CrmInboxNotices;
use App\Support\CrmInbox\CrmInboxQueue;
use App\Support\CrmInbox\CrmInboxQuote;
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

    public string $quoteCoverage = '';

    /**
     * Lo arma el servidor al calcular. Bloqueado: el navegador no puede cambiar el archivo ni el texto que se envía.
     *
     * @var array{id: string, control: string, total: string, plan: string, people: int, caption: string, ages?: string, coverage?: string}|null
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

    public function mount(): void
    {
        $this->rememberSelection();
        $this->fingerprint = CrmInboxQueue::fingerprint($this->panelAreas(), $this->keepsUnlabeled(), $this->selectedHandoffId);
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
     * Sondeo de la bandeja: una consulta de agregados. Solo repinta si la cola o el hilo abierto cambiaron.
     */
    public function heartbeat(): void
    {
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

        $card = $this->directoryCard((string) $envelope->phone);

        if ($card['status'] === 'none') {
            $case = $this->openCase();
            $document = $case !== null ? CrmInboxCopilot::detectedDocument($case) : null;

            if ($document !== null) {
                $card = $this->documentCard($document);
            }
        }

        $this->directory = $card;
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
        $this->quoteCoverage = in_array((int) $prefill['coverage'], CrmInboxQuote::COVERAGES[$this->quotePlan] ?? [], true)
            ? (string) $prefill['coverage']
            : '';
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

        $this->selectedHandoffId = null;
        $this->summaryOpen = false;
        $this->directory = null;
        $this->clearTransfers();
        $this->discardQuote();

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
            Notification::make()
                ->title('No se pudo tomar el caso')
                ->body('El bot sigue respondiendo. Intenta de nuevo en un momento.')
                ->danger()
                ->send();

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

    public function close(): void
    {
        $envelope = $this->selectedEnvelope();

        if ($envelope === null) {
            return;
        }

        $result = app(CrmHandoffClose::class)->close($envelope, $this->analystId());

        if ($result['ok'] !== true) {
            Notification::make()
                ->title('Esta conversación está en atención')
                ->body('Devuélvela al bot para cerrarla. Así el cliente sabe que SolIA retoma.')
                ->danger()
                ->send();

            return;
        }

        $this->selectedHandoffId = null;
        $this->summaryOpen = false;
        $this->directory = null;
        $this->clearTransfers();
        $this->discardQuote();

        Notification::make()
            ->title('Conversación cerrada')
            ->body('Ya no aparece en la bandeja. El cliente no recibió ningún mensaje.')
            ->success()
            ->send();
    }

    public function release(): void
    {
        $envelope = $this->selectedEnvelope();

        if ($envelope === null) {
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

        $result = app(CrmHandoffRelease::class)->release($envelope);

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

        $this->selectedHandoffId = null;
        $this->summaryOpen = false;
        $this->directory = null;
        $this->clearTransfers();
        $this->discardQuote();

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

        if ($this->quoteAges === '' && $this->quoteCoverage === '' && $this->quoteDraft === null) {
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

        if ($this->quoteCoverage !== '' && ! in_array((int) $this->quoteCoverage, $allowed, true)) {
            $this->quoteCoverage = '';
        }

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
            $this->quoteCoverage,
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
            'coverage' => $this->quoteCoverage,
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
            'coverage' => $draft['coverage'] ?? null,
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
        $this->quoteCoverage = '';
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
     * @return array{viewerId: ?int, slaWarnSeconds: int, slaLateSeconds: int, serverNow: int}
     */
    protected function getViewData(): array
    {
        $warn = intdiv($this->slaWarnSeconds(), 60);
        $late = max($warn + 1, (int) config('crm-inbox.sla.late_minutes', 15));

        return [
            'viewerId' => $this->analystId(),
            'slaWarnSeconds' => $warn * 60,
            'slaLateSeconds' => $late * 60,
            'serverNow' => now()->getTimestamp(),
        ];
    }

    /**
     * Respuestas rápidas del caso abierto, con el nombre del cliente y la propuesta ya puestos.
     *
     * @param  array<string, mixed>  $case
     * @return list<array{label: string, text: string}>
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
        $replies = [];

        foreach ((array) config('crm-inbox.quick_replies', []) as $reply) {
            $label = trim((string) ($reply['label'] ?? ''));
            $text = trim((string) ($reply['text'] ?? ''));

            if ($label === '' || $text === '' || (str_contains($text, '{propuesta}') && $quote === '')) {
                continue;
            }

            $replies[] = [
                'label' => $label,
                'text' => str_replace(['{cliente}', '{propuesta}'], [$given, $quote], $text),
            ];
        }

        return array_slice($replies, 0, 5);
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
            default => 'No se pudo enviar por WhatsApp',
        };

        $notice = Notification::make()->title($title)->danger();

        $body = match ($reason) {
            'not_taken' => 'El bot sigue respondiendo hasta que tomes el caso.',
            'missing' => 'Vuelve a calcularla. El archivo ya no está.',
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
        $this->colleagues = [];
        $this->destinations = [];
    }

    private function rememberSelection(): void
    {
        if ($this->selectedHandoffId === null || ! preg_match('/^\d{1,18}$/', $this->selectedHandoffId)) {
            $this->selectedHandoffId = null;
            $this->directory = null;

            return;
        }

        if ($this->selectedEnvelope() === null) {
            $this->selectedHandoffId = null;
        }

        $this->directory = null;
    }

    /**
     * @return array{status: 'match', kind_label: string, name: string, url: ?string, via: string}|array{status: 'none', document: string}
     */
    private function documentCard(string $document): array
    {
        $match = CrmInboxDirectory::matchDocument($document);

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
    private function directoryCard(string $phone): array
    {
        $match = CrmInboxDirectory::match($phone);

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
