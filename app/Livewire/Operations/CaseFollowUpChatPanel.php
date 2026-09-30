<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Models\TelemedicineCase;
use App\Models\User;
use App\Support\Operations\CaseFollowUpChatManager;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\ConsultationChatSummary;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Chat de seguimiento de casos (Operaciones y Telemedicina).
 *
 * Rendimiento: el componente vive en todas las páginas del panel, así que el ciclo de
 * actualización debe ser barato. Cada ciclo hace 2–3 consultas agregadas y NO vuelve a pintar
 * el chat si nada cambió; la lista de casos es paginada y se busca en la base.
 */
class CaseFollowUpChatPanel extends Component
{
    public const CASE_PAGE_SIZE = 30;

    public const CASE_LIST_MAX = 300;

    public const MESSAGE_PAGE_SIZE = 50;

    public const MESSAGE_LIST_MAX = 1000;

    /**
     * Panel desde el que se abre el chat; decide qué casos ve el usuario. Bloqueado para que el
     * navegador no pueda cambiarlo y ampliar su alcance.
     */
    #[Locked]
    public string $context = CaseFollowUpChatManager::CONTEXT_OPERATIONS;

    public bool $isOpen = false;

    public bool $isMinimized = false;

    public ?int $selectedCaseId = null;

    public string $messageBody = '';

    public string $caseSearch = '';

    public int $caseListLimit = self::CASE_PAGE_SIZE;

    public int $messageLimit = self::MESSAGE_PAGE_SIZE;

    /** @var array<int, int> Solo casos con mensajes sin leer. */
    public array $unreadByCase = [];

    /** @var array<int, int> Caso => id de su último mensaje sin leer, del más reciente al más antiguo. */
    public array $unreadLatestByCase = [];

    /** Filtro «Solo no leídos» de la lista de casos. */
    public bool $onlyUnread = false;

    /** Primer mensaje sin leer del caso abierto: ahí se dibuja el separador «Mensajes nuevos». */
    public ?int $unreadDividerMessageId = null;

    public int $totalUnread = 0;

    public ?int $lastKnownLatestMessageId = null;

    public ?int $lastNotifiedIncomingMessageId = null;

    #[Locked]
    public string $casesSignature = '';

    public function mount(?string $context = null): void
    {
        $this->context = CaseFollowUpChatManager::normalizeContext($context);
        $this->refreshUnreadCounters();
        $this->lastNotifiedIncomingMessageId = CaseFollowUpChatManager::latestIncomingMessageIdForUser($this->currentUser(), $this->context);
        $this->dispatchUnreadSnapshot();
    }

    #[On('operations-case-chat-open')]
    public function openFromEvent(mixed $caseId = null): void
    {
        if (is_array($caseId)) {
            $caseId = $caseId['caseId'] ?? null;
        }

        $this->openPanel(filled($caseId) ? (int) $caseId : null);
    }

    public function openPanel(?int $caseId = null): void
    {
        $this->isOpen = true;
        $this->isMinimized = false;
        $this->refreshCasesSignature();

        $this->refreshUnreadCounters();

        $caseId ??= $this->latestUnreadCaseId();

        if ($caseId !== null) {
            $this->selectCase($caseId);

            return;
        }

        if ($this->selectedCaseId === null && ($firstCaseId = $this->firstCaseId()) !== null) {
            $this->selectCase($firstCaseId);

            return;
        }

        $this->refreshUnreadCounters();
        $this->syncScrollAnchorForSelectedCase();
        $this->dispatchScrollToLatestMessage();
        $this->dispatch('operations-case-chat-opened');
    }

    public function closePanel(): void
    {
        $this->isOpen = false;
        $this->isMinimized = false;
        $this->lastKnownLatestMessageId = null;
        $this->resetValidation();

        $this->dispatch('operations-case-chat-closed');
    }

    public function restorePanel(?int $caseId = null, bool $isMinimized = false): void
    {
        $this->isOpen = true;
        $this->isMinimized = $isMinimized;
        $this->refreshCasesSignature();

        $caseId ??= $this->selectedCaseId ?? $this->firstCaseId();

        if ($caseId !== null) {
            $this->selectCase($caseId);

            if (! $isMinimized) {
                $this->dispatchScrollToLatestMessage();
            }

            return;
        }

        $this->refreshUnreadCounters();
    }

    public function toggleMinimize(): void
    {
        $this->isMinimized = ! $this->isMinimized;
    }

    public function selectCase(int $caseId): void
    {
        $user = $this->currentUser();
        $case = TelemedicineCase::query()->find($caseId);

        if (! $case instanceof TelemedicineCase) {
            return;
        }

        if ($user === null || ! CaseFollowUpChatManager::canAccessCase($user, $case, $this->context)) {
            Notification::make()
                ->title('Acceso denegado')
                ->body('No puede acceder al chat de este caso.')
                ->danger()
                ->send();

            return;
        }

        if (isset($this->unreadByCase[$caseId])) {
            $this->unreadDividerMessageId = CaseFollowUpChatManager::firstUnreadMessageId($user, $caseId);
        } elseif ($this->selectedCaseId !== $caseId) {
            $this->unreadDividerMessageId = null;
        }
        $this->selectedCaseId = $caseId;
        $this->messageBody = '';
        $this->messageLimit = self::MESSAGE_PAGE_SIZE;
        $this->resetValidation();

        CaseFollowUpChatManager::markCaseAsRead($user, $caseId);
        $this->forgetUnread($caseId);
        $this->syncScrollAnchorForSelectedCase();
        $this->dispatchUnreadSnapshot();
    }

    public function sendMessage(): void
    {
        $this->validate([
            'messageBody' => ['required', 'string', 'min:1', 'max:5000'],
            'selectedCaseId' => ['required', 'integer'],
        ], [], [
            'messageBody' => 'mensaje',
            'selectedCaseId' => 'caso',
        ]);

        $user = $this->currentUser();

        if ($user === null) {
            return;
        }

        $case = TelemedicineCase::query()->find($this->selectedCaseId);

        if (! $case instanceof TelemedicineCase) {
            return;
        }

        if (! CaseFollowUpChatManager::canAccessCase($user, $case, $this->context)) {
            Notification::make()
                ->title('No se envió el mensaje')
                ->body('El caso ya no está disponible para chat.')
                ->warning()
                ->send();

            return;
        }

        $message = CaseFollowUpChatManager::sendMessage($case, $user, $this->messageBody, $this->context);

        SecurityAudit::log(
            $this->context === CaseFollowUpChatManager::CONTEXT_TELEMEDICINE
                ? 'AUDIT_TELEMEDICINE_CASE_FOLLOW_UP_CHAT_MESSAGE'
                : 'AUDIT_OPERATIONS_CASE_FOLLOW_UP_CHAT_MESSAGE',
            $this->context.'.case-follow-up-chat.send',
            [
                'telemedicine_case_id' => $case->id,
                'telemedicine_case_code' => $case->code,
                'message_id' => $message->id,
                'context' => $this->context,
            ],
        );

        $this->messageBody = '';
        $this->resetValidation();
        $this->lastKnownLatestMessageId = $message->id;
        $this->refreshCasesSignature();
        $this->dispatchScrollToLatestMessage();
    }

    /**
     * `selectedCaseId` puede llegar cambiado desde el navegador: se valida antes de usarlo.
     */
    public function updatedSelectedCaseId(): void
    {
        if ($this->selectedCaseId === null) {
            return;
        }

        $user = $this->currentUser();
        $case = TelemedicineCase::query()->find($this->selectedCaseId);

        if ($user === null || ! $case instanceof TelemedicineCase || ! CaseFollowUpChatManager::canAccessCase($user, $case, $this->context)) {
            $this->selectedCaseId = null;

            return;
        }

        $this->messageLimit = self::MESSAGE_PAGE_SIZE;
        $this->unreadDividerMessageId = null;
        $this->syncScrollAnchorForSelectedCase();
        $this->dispatchScrollToLatestMessage();
    }

    public function updatedCaseSearch(): void
    {
        $this->caseListLimit = self::CASE_PAGE_SIZE;
    }

    public function updatedOnlyUnread(): void
    {
        $this->caseListLimit = self::CASE_PAGE_SIZE;
    }

    public function toggleOnlyUnread(): void
    {
        $this->onlyUnread = ! $this->onlyUnread;
        $this->caseListLimit = self::CASE_PAGE_SIZE;
    }

    public function loadMoreCases(): void
    {
        $this->caseListLimit = min(self::CASE_LIST_MAX, $this->caseListLimit + self::CASE_PAGE_SIZE);
    }

    public function loadOlderMessages(): void
    {
        $this->messageLimit = min(self::MESSAGE_LIST_MAX, $this->messageLimit + self::MESSAGE_PAGE_SIZE);
    }

    /**
     * Ciclo de actualización. Es barato (2–3 consultas agregadas) y solo vuelve a pintar el chat
     * cuando hay algo nuevo que mostrar; si no, responde sin HTML.
     */
    public function pollHeartbeat(): void
    {
        $user = $this->currentUser();

        if ($user === null) {
            $this->skipRender();

            return;
        }

        $previousUnread = $this->unreadLatestByCase;

        $this->refreshUnreadCounters();
        $this->notifyIncomingMessagesIfNeeded();
        $this->dispatchUnreadSnapshot();

        if (! $this->isOpen) {
            $this->skipRender();

            return;
        }

        $needsRender = $previousUnread !== $this->unreadLatestByCase;

        if (! $this->isMinimized && $this->selectedCaseId !== null && $this->syncTimelineScrollOnPoll()) {
            $needsRender = true;
            $this->dispatchScrollToLatestMessage(force: false);
        }

        if (! $this->isMinimized && $this->selectedCaseId !== null && isset($this->unreadByCase[$this->selectedCaseId])) {
            CaseFollowUpChatManager::markCaseAsRead($user, $this->selectedCaseId);
            $this->forgetUnread($this->selectedCaseId);
            $needsRender = true;
        }

        $signature = CaseFollowUpChatManager::followUpCasesSignature($user, $this->context);

        if ($signature !== $this->casesSignature) {
            $this->casesSignature = $signature;
            $needsRender = true;
        }

        if (! $needsRender) {
            $this->skipRender();
        }
    }

    public function refreshTimeline(): void
    {
        $this->pollHeartbeat();
    }

    public function refreshUnreadCounters(): void
    {
        $user = $this->currentUser();

        if ($user === null) {
            $this->unreadByCase = [];
            $this->unreadLatestByCase = [];
            $this->totalUnread = 0;

            return;
        }

        $summary = CaseFollowUpChatManager::unreadSummaryForUser($user, $this->context);

        $this->unreadByCase = array_map(static fn (array $row): int => $row['count'], $summary);
        $this->unreadLatestByCase = array_map(static fn (array $row): int => $row['latest_id'], $summary);
        $this->totalUnread = array_sum($this->unreadByCase);
    }

    private function forgetUnread(int $caseId): void
    {
        unset($this->unreadByCase[$caseId], $this->unreadLatestByCase[$caseId]);
        $this->totalUnread = array_sum($this->unreadByCase);
    }

    /**
     * Caso con el mensaje sin leer más reciente (el resumen ya viene ordenado).
     */
    private function latestUnreadCaseId(): ?int
    {
        $caseId = array_key_first($this->unreadLatestByCase);

        return $caseId !== null ? (int) $caseId : null;
    }

    protected function syncTimelineScrollOnPoll(): bool
    {
        if ($this->selectedCaseId === null) {
            return false;
        }

        $latestMessageId = CaseFollowUpChatManager::latestMessageIdForCase($this->selectedCaseId);

        if ($latestMessageId === $this->lastKnownLatestMessageId) {
            return false;
        }

        $this->lastKnownLatestMessageId = $latestMessageId;

        return true;
    }

    protected function syncScrollAnchorForSelectedCase(): void
    {
        $this->lastKnownLatestMessageId = $this->selectedCaseId === null
            ? null
            : CaseFollowUpChatManager::latestMessageIdForCase($this->selectedCaseId);
    }

    protected function refreshCasesSignature(): void
    {
        $user = $this->currentUser();

        $this->casesSignature = $user === null
            ? ''
            : CaseFollowUpChatManager::followUpCasesSignature($user, $this->context);
    }

    protected function dispatchScrollToLatestMessage(bool $force = true): void
    {
        $this->dispatch('operations-case-chat-scroll-bottom', force: $force);
    }

    protected function notifyIncomingMessagesIfNeeded(): void
    {
        $user = $this->currentUser();

        if ($user === null) {
            return;
        }

        $newMessages = CaseFollowUpChatManager::incomingMessagesAfterId($user, $this->lastNotifiedIncomingMessageId, $this->context);

        if ($newMessages->isEmpty()) {
            return;
        }

        /** @var \App\Models\TelemedicineCaseMessage $latest */
        $latest = $newMessages->last();
        $this->lastNotifiedIncomingMessageId = (int) $latest->id;

        $caseCode = TelemedicineCase::query()->whereKey($latest->telemedicine_case_id)->value('code');
        $isSummary = $latest->isConsultationSummary();
        $authorName = $isSummary
            ? ConsultationChatSummary::AUTHOR_LABEL
            : ($latest->user?->name ?? $latest->user?->email ?? 'Analista');
        $preview = $isSummary
            ? (string) ($latest->meta['title'] ?? 'Resumen de consulta')
            : trim((string) $latest->body);

        $this->dispatch(
            'operations-case-chat-incoming-message',
            messageId: (int) $latest->id,
            caseId: (int) $latest->telemedicine_case_id,
            caseCode: filled($caseCode) ? (string) $caseCode : 'Caso #'.$latest->telemedicine_case_id,
            authorName: (string) $authorName,
            bodyPreview: Str::limit($preview, 80),
            newCount: $newMessages->count(),
            totalUnread: $this->totalUnread,
            isSummary: $isSummary,
        );
    }

    protected function dispatchUnreadSnapshot(): void
    {
        $this->dispatch(
            'operations-case-chat-unread-updated',
            totalUnread: $this->totalUnread,
        );
    }

    private function firstCaseId(): ?int
    {
        $user = $this->currentUser();

        return $user === null ? null : CaseFollowUpChatManager::firstFollowUpCaseId($user, $this->context);
    }

    private function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public function render(): View
    {
        $user = $this->currentUser();

        if (! $this->isOpen || $user === null) {
            return view('livewire.operations.case-follow-up-chat-panel', [
                'unreadCases' => collect(),
                'previews' => [],
                'cases' => collect(),
                'hasMoreCases' => false,
                'activeCasesCount' => 0,
                'selectedCase' => null,
                'messages' => collect(),
                'hasOlderMessages' => false,
            ]);
        }

        $unreadCaseIds = array_map('intval', array_keys($this->unreadLatestByCase));

        /** Los casos con mensajes sin leer van siempre arriba, fuera de la paginación. */
        $unreadCases = CaseFollowUpChatManager::casesByIdsForChatList($user, $this->context, $unreadCaseIds, $this->caseSearch);
        $previews = CaseFollowUpChatManager::latestMessagePreviewByCase($unreadCases);

        if ($this->onlyUnread) {
            $cases = collect();
            $hasMoreCases = false;
        } else {
            $casePage = CaseFollowUpChatManager::casesForChatList($user, $this->context, $this->caseSearch, $this->caseListLimit, $unreadCaseIds);
            $hasMoreCases = $casePage->count() > $this->caseListLimit;
            $cases = $casePage->take($this->caseListLimit)->values();
        }

        $selectedCase = $this->selectedCaseId !== null
            ? TelemedicineCase::query()
                ->with(['telemedicineDoctor:id,full_name', 'telemedicinePatient:id,full_name'])
                ->find($this->selectedCaseId)
            : null;

        /** `selectedCaseId` es público: nunca se muestran mensajes de un caso fuera del alcance del usuario. */
        if ($selectedCase instanceof TelemedicineCase && ! CaseFollowUpChatManager::canAccessCase($user, $selectedCase, $this->context)) {
            $selectedCase = null;
            $this->selectedCaseId = null;
        }

        $messages = $selectedCase instanceof TelemedicineCase
            ? CaseFollowUpChatManager::messagesForCase($selectedCase->id, $this->messageLimit)
            : collect();

        $hasOlderMessages = $selectedCase instanceof TelemedicineCase
            && $messages->count() >= $this->messageLimit
            && CaseFollowUpChatManager::countMessagesForCase($selectedCase->id) > $messages->count();

        return view('livewire.operations.case-follow-up-chat-panel', [
            'unreadCases' => $unreadCases,
            'previews' => $previews,
            'cases' => $cases,
            'hasMoreCases' => $hasMoreCases,
            'activeCasesCount' => $this->caseSearch === '' && ! $hasMoreCases && ! $this->onlyUnread
                ? $cases->count() + $unreadCases->count()
                : CaseFollowUpChatManager::countFollowUpCases($user, $this->context),
            'selectedCase' => $selectedCase,
            'messages' => $messages instanceof Collection ? $messages : collect($messages),
            'hasOlderMessages' => $hasOlderMessages,
        ]);
    }
}
