<?php

declare(strict_types=1);

it('registra el chat flotante de casos en el panel de operaciones', function (): void {
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/OperationsPanelProvider.php');
    $quickNav = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/panels/internal-quick-nav.blade.php');
    $hook = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/hooks/case-follow-up-chat-panel.blade.php');

    expect($provider)
        ->toContain('case-follow-up-chat-panel')
        ->not->toContain('case-follow-up-chat-topbar')
        ->not->toContain('PanelsRenderHook::GLOBAL_SEARCH_AFTER');

    expect($hook)
        ->toContain('@auth')
        ->toContain('@endauth')
        ->toContain("@persist('operations-case-follow-up-chat-panel')")
        ->not->toContain('fi-operations-case-chat-state');

    expect($quickNav)
        ->toContain('operations-chat')
        ->toContain("Livewire.dispatch('operations-case-chat-open')")
        ->toContain('fi-business-panel-stepper-segment--operations-chat')
        ->toContain('heroicon-o-chat-bubble-bottom-center-text')
        ->toContain('fi-business-panel-stepper-chat-unread-badge')
        ->toContain('operations-case-chat-unread-updated');
});

it('centraliza la logica del chat de seguimiento en CaseFollowUpChatManager', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CaseFollowUpChatManager.php'))
        ->toContain('final class CaseFollowUpChatManager')
        ->toContain("public const FOLLOW_UP_STATUS = 'EN SEGUIMIENTO'")
        ->toContain('function sendMessage(')
        ->toContain('OperationsSupplierScope::applyToQuery')
        ->toContain('use App\\Support\\Filament\\Operations\\OperationsSupplierScope')
        ->toContain('function totalUnreadCount')
        ->toContain('function latestMessageIdForCase')
        ->toContain('function latestIncomingMessageIdForUser')
        ->toContain('function incomingMessagesAfterId');
});

it('auto desplaza al ultimo mensaje cuando llega uno nuevo por polling', function (): void {
    $component = file_get_contents(dirname(__DIR__, 2).'/app/Livewire/Operations/CaseFollowUpChatPanel.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/operations/case-follow-up-chat-panel.blade.php');
    $theme = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');

    expect($component)
        ->toContain('class CaseFollowUpChatPanel')
        ->toContain('function sendMessage(')
        ->toContain('function pollHeartbeat(')
        ->toContain('function updatedSelectedCaseId(')
        ->toContain('dispatchScrollToLatestMessage')
        ->toContain('syncScrollAnchorForSelectedCase')
        ->toContain("#[On('operations-case-chat-open')]")
        ->toContain('lastKnownLatestMessageId')
        ->toContain('syncTimelineScrollOnPoll(): bool')
        ->toContain('dispatchScrollToLatestMessage(force: false)')
        ->toContain('function restorePanel(')
        ->toContain('operations-case-chat-closed')
        ->toContain('operations-case-chat-opened')
        ->toContain('latestMessageIdForCase')
        ->toContain('lastNotifiedIncomingMessageId')
        ->toContain('notifyIncomingMessagesIfNeeded')
        ->toContain('dispatchUnreadSnapshot')
        ->toContain('operations-case-chat-incoming-message')
        ->toContain('operations-case-chat-unread-updated');

    expect($view)
        ->toContain('wire:poll.3s="pollHeartbeat"')
        ->toContain('operationsCaseChatPanel')
        ->toContain('fi-operations-case-chat-window')
        ->not->toContain('fi-operations-case-chat-backdrop')
        ->toContain('fi-operations-case-chat--ios')
        ->toContain('fi-operations-case-chat--glass')
        ->toContain('fi-operations-case-chat-body-shell')
        ->toContain('stickTimelineToLatest')
        ->toContain('pinTimelineToBottom')
        ->toContain('onMessagesScroll')
        ->toContain('bindMessagesObserver')
        ->toContain('toggleMinimize()')
        ->toContain('is-minimized')
        ->toContain('submitMessage')
        ->toContain('afterOutboundMessage')
        ->toContain('bindComposerResizeObserver')
        ->toContain('scrollMessagesToBottom')
        ->toContain('x-ref="composerInput"')
        ->toContain('wire:model.live="messageBody"')
        ->toContain('x-ref="messagesEnd"')
        ->toContain('operations-case-chat-scroll-bottom')
        ->toContain('isAuthenticatedOperationsArea')
        ->toContain('isOperationsAuthPage')
        ->toContain('handleOperationsModuleNavigation')
        ->toContain('livewire:navigated')
        ->not->toContain('restoreChatStateIfNeeded')
        ->not->toContain('fi-operations-case-chat-state')
        ->not->toContain('persistChatState')
        ->not->toContain('wire:click="toggleMinimize"')
        ->not->toContain('fi-operations-case-chat-fab')
        ->toContain('fi-operations-case-chat-header-unread-badge')
        ->toContain('has-unread-alert')
        ->toContain('has-incoming-pulse')
        ->toContain('playIncomingSound')
        ->toContain('showIncomingToast')
        ->toContain('operations-case-chat-incoming-message')
        ->toContain('operations-case-chat-unread-updated');

    expect($theme)
        ->toContain('overflow-wrap: anywhere')
        ->toContain('min(88%')
        ->toContain('overflow-x-hidden')
        ->toContain('min-h-0 flex-1')
        ->toContain('fi-operations-case-chat-body-shell')
        ->toContain('grid-template-rows: minmax(0, 1fr) auto')
        ->toContain('fi-operations-case-chat-messages-inner')
        ->toContain('touch-action: pan-y')
        ->toContain('shrink-0 border-t')
        ->not->toContain('fi-operations-case-chat-backdrop')
        ->toContain('Chat de seguimiento — tema claro')
        ->toContain('Chat de seguimiento — tema oscuro')
        ->toContain('html:not(.dark) .fi-operations-case-chat--ios.fi-operations-case-chat-window')
        ->toContain('blur(150px) saturate(180%)')
        ->toContain('html:not(.dark) .fi-operations-case-chat--ios .fi-operations-case-chat-messages')
        ->toContain('blur(150px) saturate(180%)')
        ->toContain('.dark .fi-operations-case-chat--ios.fi-operations-case-chat-window')
        ->toContain('blur(150px) saturate(180%) brightness(0.78)')
        ->toContain('Chat de seguimiento — glassmorphism 100%')
        ->toContain('.fi-operations-case-chat--glass .fi-operations-case-chat-case-item')
        ->toContain('fi-operations-case-chat-header-unread-badge')
        ->toContain('fi-business-panel-stepper-chat-unread-badge')
        ->toContain('fi-ops-case-chat-incoming-pulse');
});

it('persiste mensajes y lecturas en tablas dedicadas', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_06_03_183128_create_telemedicine_case_messages_table.php'))
        ->toContain('telemedicine_case_messages')
        ->toContain('telemedicine_case_id');

    expect(file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_06_03_183129_create_telemedicine_case_chat_reads_table.php'))
        ->toContain('telemedicine_case_chat_reads')
        ->toContain('last_read_at');
});

it('expone accion para abrir el chat desde la tabla de casos en operaciones', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/Tables/TelemedicineCasesTable.php'))
        ->toContain("Action::make('openCaseFollowUpChat')")
        ->toContain("dispatch('operations-case-chat-open', caseId: \$record->id)")
        ->toContain('CaseFollowUpChatManager::FOLLOW_UP_STATUS');
});
