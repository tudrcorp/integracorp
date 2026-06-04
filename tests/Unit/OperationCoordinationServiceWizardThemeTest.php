<?php

declare(strict_types=1);

it('el theme define estilos del wizard de coordinación de servicios con bordes visibles', function (): void {
    $theme = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');
    expect($theme)->toContain('.fi-coordination-service-wizard .fi-section')
        ->and($theme)->toContain('.fi-coordination-service-wizard .fi-input-wrp')
        ->and($theme)->toContain('border border-zinc-200/90')
        ->and($theme)->toContain('.fi-coordination-manage-items-wizard')
        ->and($theme)->toContain('.fi-coordination-manage-items-page .fi-main')
        ->and($theme)->toContain('.fi-coordination-service-wizard .fi-section-header-heading')
        ->and($theme)->toContain('dark:text-zinc-50')
        ->and($theme)->toContain('.fi-coordination-manage-items-page .fi-fo-repeater > ul > li')
        ->and($theme)->toContain('.fi-manage-service-context-panel')
        ->and($theme)->toContain('.dark .fi-coordination-manage-items-page .fi-manage-service-items-table thead')
        ->and($theme)->toContain('58rem')
        ->and($theme)->toContain('.fi-modal:has(.fi-coordination-manage-items-modal) .fi-modal-window-ctn')
        ->and($theme)->toContain('.fi-manage-quote-summary')
        ->and($theme)->toContain('.fi-unregistered-provider-wizard-step');
});
