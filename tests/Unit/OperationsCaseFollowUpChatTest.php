<?php

declare(strict_types=1);

it('registra el chat flotante de casos en el panel de operaciones', function (): void {
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/OperationsPanelProvider.php');

    expect($provider)
        ->toContain('case-follow-up-chat-panel')
        ->toContain('case-follow-up-chat-topbar')
        ->toContain('PanelsRenderHook::GLOBAL_SEARCH_AFTER');
});

it('centraliza la logica del chat de seguimiento en CaseFollowUpChatManager', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CaseFollowUpChatManager.php'))
        ->toContain('final class CaseFollowUpChatManager')
        ->toContain("public const FOLLOW_UP_STATUS = 'EN SEGUIMIENTO'")
        ->toContain('function sendMessage(')
        ->toContain('OperationsSupplierScope::applyToQuery')
        ->toContain('use App\\Support\\Filament\\Operations\\OperationsSupplierScope')
        ->toContain('function totalUnreadCount')
        ->toContain('function latestMessageIdForCase');
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
        ->toContain('syncTimelineScrollOnPoll')
        ->toContain('latestMessageIdForCase');

    expect($view)
        ->toContain('wire:poll.3s="pollHeartbeat"')
        ->toContain('operationsCaseChatPanel')
        ->toContain('fi-operations-case-chat-window')
        ->toContain('fi-operations-case-chat--ios')
        ->toContain('fi-operations-case-chat-body-shell')
        ->toContain('stickTimelineToLatest')
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
        ->not->toContain('wire:click="toggleMinimize"')
        ->not->toContain('fi-operations-case-chat-fab');

    expect($theme)
        ->toContain('overflow-wrap: anywhere')
        ->toContain('min(88%')
        ->toContain('overflow-x-hidden')
        ->toContain('min-h-0 flex-1')
        ->toContain('shrink-0 border-t');
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
