<?php

declare(strict_types=1);

use App\Models\HelpDesk;
use App\Support\HelpdeskTaskStatusOptions;
use App\Support\ProjectManagement\InProgressHelpDeskOptions;

it('formatea la etiqueta del ticket con id y descripcion', function (): void {
    $inProgress = new HelpDesk([
        'description' => 'Corregir plantilla de correos de testigos',
        'status' => HelpdeskTaskStatusOptions::STATUS_IN_PROGRESS,
    ]);
    $inProgress->id = 31;

    expect(InProgressHelpDeskOptions::label($inProgress))
        ->toBe('#31 — Corregir plantilla de correos de testigos');

    $empty = new HelpDesk([
        'description' => null,
        'status' => HelpdeskTaskStatusOptions::STATUS_IN_PROGRESS,
    ]);
    $empty->id = 7;

    expect(InProgressHelpDeskOptions::label($empty))->toBe('#7');
});

it('expone el select de ticket y la relacion help_desk en actividades', function (): void {
    $backlog = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Projects/Pages/Backlog.php');
    $options = file_get_contents(dirname(__DIR__, 2).'/app/Support/ProjectManagement/InProgressHelpDeskOptions.php');
    $activity = file_get_contents(dirname(__DIR__, 2).'/app/Models/ProjectManagement/Activity.php');
    $migration = file_get_contents(
        dirname(__DIR__, 2).'/database/migrations/2026_07_28_114249_add_help_desk_id_to_activities_table.php',
    );

    expect($backlog)
        ->toContain("Select::make('help_desk_id')")
        ->toContain("->label('Ticket')")
        ->toContain('ToBacklogSession::options()')
        ->toContain('ToBacklogSession::remove($helpDeskId)');

    expect($options)
        ->toContain('HelpdeskTaskStatusOptions::STATUS_IN_PROGRESS')
        ->toContain('public static function search')
        ->toContain('public static function labelForId');

    expect($activity)
        ->toContain("'help_desk_id'")
        ->toContain('public function helpDesk(): BelongsTo');

    expect($migration)
        ->toContain("foreignId('help_desk_id')")
        ->toContain("constrained('help_desks')");
});
