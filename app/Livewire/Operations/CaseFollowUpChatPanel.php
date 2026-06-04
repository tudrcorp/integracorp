<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Models\TelemedicineCase;
use App\Support\Operations\CaseFollowUpChatManager;
use App\Support\SecurityAudit;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class CaseFollowUpChatPanel extends Component
{
    public bool $isOpen = false;

    public bool $isMinimized = false;

    public ?int $selectedCaseId = null;

    public string $messageBody = '';

    public string $caseSearch = '';

    /** @var array<int, int> */
    public array $unreadByCase = [];

    public int $totalUnread = 0;

    public ?int $lastKnownLatestMessageId = null;

    public function mount(): void
    {
        $this->refreshUnreadCounters();
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

        if ($caseId !== null) {
            $this->selectCase($caseId);

            return;
        }

        if ($this->selectedCaseId === null) {
            $firstCase = CaseFollowUpChatManager::followUpCasesForChat()->first();

            if ($firstCase instanceof TelemedicineCase) {
                $this->selectCase($firstCase->id);

                return;
            }
        }

        $this->refreshUnreadCounters();
        $this->syncScrollAnchorForSelectedCase();
        $this->dispatchScrollToLatestMessage();
    }

    public function closePanel(): void
    {
        $this->isOpen = false;
        $this->isMinimized = false;
        $this->lastKnownLatestMessageId = null;
        $this->resetValidation();
    }

    public function toggleMinimize(): void
    {
        $this->isMinimized = ! $this->isMinimized;
    }

    public function selectCase(int $caseId): void
    {
        $case = TelemedicineCase::query()->find($caseId);

        if (! $case instanceof TelemedicineCase) {
            return;
        }

        $user = Auth::user();

        if ($user === null || ! CaseFollowUpChatManager::canAccessCase($user, $case)) {
            Notification::make()
                ->title('Acceso denegado')
                ->body('No puede acceder al chat de este caso.')
                ->danger()
                ->send();

            return;
        }

        $this->selectedCaseId = $caseId;
        $this->messageBody = '';
        $this->resetValidation();

        CaseFollowUpChatManager::markCaseAsRead($user, $caseId);
        $this->refreshUnreadCounters();
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

        $user = Auth::user();

        if ($user === null) {
            return;
        }

        $case = TelemedicineCase::query()->find($this->selectedCaseId);

        if (! $case instanceof TelemedicineCase) {
            return;
        }

        if (! CaseFollowUpChatManager::canAccessCase($user, $case)) {
            Notification::make()
                ->title('No se envió el mensaje')
                ->body('El caso ya no está disponible para chat.')
                ->warning()
                ->send();

            return;
        }

        $message = CaseFollowUpChatManager::sendMessage($case, $user, $this->messageBody);

        SecurityAudit::log('AUDIT_OPERATIONS_CASE_FOLLOW_UP_CHAT_MESSAGE', 'operations.case-follow-up-chat.send', [
            'telemedicine_case_id' => $case->id,
            'telemedicine_case_code' => $case->code,
            'message_id' => $message->id,
        ]);

        $this->messageBody = '';
        $this->resetValidation();
        $this->lastKnownLatestMessageId = $message->id;
        $this->refreshUnreadCounters();
        $this->dispatchScrollToLatestMessage();
    }

    public function updatedSelectedCaseId(): void
    {
        if ($this->selectedCaseId === null) {
            return;
        }

        $this->syncScrollAnchorForSelectedCase();
        $this->dispatchScrollToLatestMessage();
    }

    public function pollHeartbeat(): void
    {
        $this->refreshUnreadCounters();

        if (! $this->isOpen || $this->isMinimized) {
            return;
        }

        if ($this->selectedCaseId !== null && Auth::user() !== null) {
            CaseFollowUpChatManager::markCaseAsRead(Auth::user(), $this->selectedCaseId);
            $this->unreadByCase[$this->selectedCaseId] = 0;
            $this->totalUnread = array_sum($this->unreadByCase);
        }

        if ($this->syncTimelineScrollOnPoll()) {
            $this->dispatchScrollToLatestMessage(force: false);
        }
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
        if ($this->selectedCaseId === null) {
            $this->lastKnownLatestMessageId = null;

            return;
        }

        $this->lastKnownLatestMessageId = CaseFollowUpChatManager::latestMessageIdForCase($this->selectedCaseId);
    }

    protected function dispatchScrollToLatestMessage(bool $force = true): void
    {
        $this->dispatch('operations-case-chat-scroll-bottom', force: $force);
    }

    public function refreshTimeline(): void
    {
        $this->pollHeartbeat();
    }

    public function refreshUnreadCounters(): void
    {
        $user = Auth::user();

        if ($user === null) {
            $this->unreadByCase = [];
            $this->totalUnread = 0;

            return;
        }

        $cases = CaseFollowUpChatManager::followUpCasesForChat($user);
        $this->unreadByCase = CaseFollowUpChatManager::unreadCountsByCase($user, $cases);
        $this->totalUnread = array_sum($this->unreadByCase);
    }

    /**
     * @return Collection<int, TelemedicineCase>
     */
    protected function filteredCases(): Collection
    {
        $cases = CaseFollowUpChatManager::followUpCasesForChat();
        $term = mb_strtolower(trim($this->caseSearch));

        if ($term === '') {
            return $cases;
        }

        return $cases->filter(function (TelemedicineCase $case) use ($term): bool {
            $haystack = mb_strtolower(implode(' ', array_filter([
                (string) $case->code,
                (string) $case->patient_name,
                (string) $case->managed_by,
                (string) $case->telemedicineDoctor?->full_name,
            ])));

            return str_contains($haystack, $term);
        })->values();
    }

    public function render(): View
    {
        $cases = $this->filteredCases();
        $previews = CaseFollowUpChatManager::latestMessagePreviewByCase($cases);

        $selectedCase = $this->selectedCaseId !== null
            ? TelemedicineCase::query()
                ->with(['telemedicineDoctor:id,full_name', 'telemedicinePatient:id,full_name'])
                ->find($this->selectedCaseId)
            : null;

        $messages = $selectedCase instanceof TelemedicineCase
            ? CaseFollowUpChatManager::messagesForCase($selectedCase->id)
            : collect();

        return view('livewire.operations.case-follow-up-chat-panel', [
            'cases' => $cases,
            'previews' => $previews,
            'selectedCase' => $selectedCase,
            'messages' => $messages,
        ]);
    }
}
