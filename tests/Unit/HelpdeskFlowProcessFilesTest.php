<?php

declare(strict_types=1);

it('ListHelpdesks define modal de video tutorial con carga de formatos en todos los módulos', function (): void {
    $panels = ['Business', 'Administration', 'Operations', 'Marketing'];

    foreach ($panels as $panel) {
        $contents = file_get_contents(
            dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Pages/ListHelpdesks.php"
        );

        expect($contents)
            ->toContain("Action::make('helpdeskVideoTutorial')")
            ->toContain("FileUpload::make('videoTutorialFiles')")
            ->toContain("'helpdesks/video-tutorial'")
            ->toContain('->maxSize(10240)')
            ->toContain("view('filament.helpdesks.video-tutorial-modal'");
    }
});

it('registra ruta de descarga de archivos de video tutorial', function (): void {
    $routesContents = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

    expect($routesContents)
        ->toContain('helpdesks/video-tutorial-files/{helpdeskVideoTutorialFile}/download')
        ->toContain("->name('helpdesks.video-tutorial-files.download')");
});

it('ListHelpdesks define acción Filament de borrado de video tutorial con confirmación en todos los módulos', function (): void {
    $panels = ['Business', 'Administration', 'Operations', 'Marketing'];

    foreach ($panels as $panel) {
        $contents = file_get_contents(
            dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Pages/ListHelpdesks.php"
        );

        expect($contents)
            ->toContain('function mountDeleteHelpdeskVideoTutorialFile(int $fileId): void')
            ->toContain("->replaceMountedAction('deleteHelpdeskVideoTutorialFile', ['fileId' => \$fileId])")
            ->toContain('function deleteHelpdeskVideoTutorialFileAction(): Action')
            ->toContain("Action::make('deleteHelpdeskVideoTutorialFile')")
            ->toContain('->modalHeading(\'Borrar archivo del video tutorial\')');
    }
});

it('la vista del modal de video tutorial incluye descargar y eliminar', function (): void {
    $viewContents = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/filament/helpdesks/video-tutorial-modal.blade.php'
    );

    expect($viewContents)
        ->not->toContain('confirm(')
        ->toContain("route('helpdesks.video-tutorial-files.download', \$file)")
        ->toContain('wire:click="mountDeleteHelpdeskVideoTutorialFile({{ $file->getKey() }})"')
        ->toContain('Vista previa no disponible para este formato.');
});

it('ListHelpdesks define modal de flujo con carga de formatos requeridos en todos los módulos', function (): void {
    $panels = ['Business', 'Administration', 'Operations', 'Marketing'];

    foreach ($panels as $panel) {
        $contents = file_get_contents(
            dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Pages/ListHelpdesks.php"
        );

        expect($contents)
            ->toContain("Action::make('helpdeskFlowProcess')")
            ->toContain("FileUpload::make('flowProcessFiles')")
            ->toContain("'application/pdf'")
            ->toContain("'application/vnd.ms-powerpoint'")
            ->toContain("'application/vnd.openxmlformats-officedocument.presentationml.presentation'")
            ->toContain('->maxSize(10240)')
            ->toContain("view('filament.helpdesks.flow-process-modal'");
    }
});

it('registra ruta de descarga del flujo de proceso', function (): void {
    $routesContents = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

    expect($routesContents)
        ->toContain('helpdesks/flow-process-files/{helpdeskFlowProcessFile}/download')
        ->toContain("->name('helpdesks.flow-process-files.download')")
        ->not->toContain("->name('helpdesks.flow-process-files.destroy')");
});

it('ListHelpdesks define acción Filament de borrado del flujo con confirmación en todos los módulos', function (): void {
    $panels = ['Business', 'Administration', 'Operations', 'Marketing'];

    foreach ($panels as $panel) {
        $contents = file_get_contents(
            dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Pages/ListHelpdesks.php"
        );

        expect($contents)
            ->toContain('function mountDeleteHelpdeskFlowProcessFile(int $fileId): void')
            ->toContain("->replaceMountedAction('deleteHelpdeskFlowProcessFile', ['fileId' => \$fileId])")
            ->toContain('function deleteHelpdeskFlowProcessFileAction(): Action')
            ->toContain("Action::make('deleteHelpdeskFlowProcessFile')")
            ->toContain('->requiresConfirmation()')
            ->toContain("->modalDescription('¿Está segura/o de hacer esto?')")
            ->toContain("->modalSubmitActionLabel('Borrar')")
            ->toContain("->modalCancelActionLabel('Cancelar')");
    }
});

it('la vista del modal incluye acciones de descargar y eliminar', function (): void {
    $viewContents = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/filament/helpdesks/flow-process-modal.blade.php'
    );

    expect($viewContents)
        ->not->toContain('>Ver<')
        ->not->toContain('confirm(')
        ->not->toContain('<form method="POST" action="{{ route(\'helpdesks.flow-process-files.destroy\', $file) }}"')
        ->toContain("route('helpdesks.flow-process-files.download', \$file)")
        ->toContain('wire:click="mountDeleteHelpdeskFlowProcessFile({{ $file->getKey() }})"')
        ->not->toContain("route('helpdesks.flow-process-files.destroy', \$file)")
        ->toContain('Vista previa no disponible para este formato.');
});
