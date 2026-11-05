<?php

declare(strict_types=1);

it('replica el ordenamiento de tickets por status en todos los módulos', function () {
    $orderSql = \App\Support\HelpdeskTableConfigurator::statusOrderByCaseSql();

    expect($orderSql)
        ->toContain("WHEN 'PENDIENTE POR INICIAR' THEN 1")
        ->toContain("WHEN 'EN ANALISIS' THEN 3")
        ->toContain("WHEN 'CANCELADO' THEN 9");

    $panels = ['Administration', 'Marketing', 'Operations', 'Business'];

    foreach ($panels as $panel) {
        $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Tables/HelpdesksTable.php";
        $contents = file_get_contents($path);

        expect($contents)->toContain('HelpdeskTableConfigurator::configure');
    }
});

it('muestra el menú de acciones del ticket al lado izquierdo de la fila en todos los módulos', function () {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskTableConfigurator.php');

    expect($contents)
        ->toContain('use Filament\Tables\Enums\RecordActionsPosition;')
        ->toContain('position: RecordActionsPosition::BeforeColumns')
        ->toContain("ActionGroup::make(\$groupActions)->icon('heroicon-c-ellipsis-vertical')");
});

it('en telemedicina el menú de tres puntos del ticket también va a la izquierda de la fila', function () {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/Helpdesks/Tables/HelpdesksTable.php');

    expect($contents)
        ->toContain('position: RecordActionsPosition::BeforeColumns')
        ->toContain("->icon('heroicon-c-ellipsis-vertical')")
        ->toContain('ViewAction::make()');
});

it('«Ver adjunto» va fuera del menú de tres puntos, como icono de clip con tooltip, y «Ver notas» en azul', function () {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskTableConfigurator.php');

    expect($contents)
        ->toContain("->label('Ver adjunto')")
        ->toContain("->icon('heroicon-m-paper-clip')")
        ->toContain("->modalHeading('Adjuntos del ticket')")
        ->not->toContain("->label('Documentos')")
        ->toContain("\$actions[] = Action::make('previewDocuments')")
        ->toContain("->iconButton()\n            ->tooltip('Ver adjunto')")
        ->not->toContain('$previewDocumentsAction')
        ->and(preg_match("/->label\('Ver notas'\)\s*->icon\('heroicon-m-clipboard-document-list'\)\s*->color\('azulClaro'\)/", $contents))->toBe(1);
});
