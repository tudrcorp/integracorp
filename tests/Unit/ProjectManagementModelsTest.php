<?php

declare(strict_types=1);

it('define modelos y relaciones polimórficas del sistema de proyectos', function (): void {
    $projectPath = dirname(__DIR__, 2).'/app/Models/ProjectManagement/Project.php';
    $subprojectPath = dirname(__DIR__, 2).'/app/Models/ProjectManagement/Subproject.php';
    $assignmentPath = dirname(__DIR__, 2).'/app/Models/ProjectManagement/ProjectAssignment.php';
    $activityPath = dirname(__DIR__, 2).'/app/Models/ProjectManagement/Activity.php';
    $notesPath = dirname(__DIR__, 2).'/app/Models/ProjectManagement/NotesLog.php';
    $documentPath = dirname(__DIR__, 2).'/app/Models/ProjectManagement/Document.php';
    $departmentPath = dirname(__DIR__, 2).'/app/Models/ProjectManagement/Department.php';
    $groupPath = dirname(__DIR__, 2).'/app/Models/ProjectManagement/Group.php';
    $traitNotesPath = dirname(__DIR__, 2).'/app/Models/ProjectManagement/Concerns/InteractsWithProjectManagementNotes.php';
    $traitDocumentsPath = dirname(__DIR__, 2).'/app/Models/ProjectManagement/Concerns/InteractsWithProjectManagementDocuments.php';

    expect(file_get_contents($projectPath))
        ->toContain('class Project extends Model')
        ->toContain('public function subprojects(): HasMany')
        ->toContain('public function assignments(): HasMany')
        ->toContain('public function activities(): HasMany');

    expect(file_get_contents($subprojectPath))
        ->toContain('class Subproject extends Model')
        ->toContain('public function project(): BelongsTo')
        ->toContain('public function activities(): HasMany');

    expect(file_get_contents($assignmentPath))
        ->toContain('class ProjectAssignment extends Model')
        ->toContain('public function assignable(): MorphTo')
        ->toContain('return $this->morphTo();');

    expect(file_get_contents($activityPath))
        ->toContain('class Activity extends Model')
        ->toContain('public function executor(): MorphTo')
        ->toContain('public function project(): BelongsTo')
        ->toContain('public function subproject(): BelongsTo');

    expect(file_get_contents($notesPath))
        ->toContain('class NotesLog extends Model')
        ->toContain('public function notable(): MorphTo')
        ->toContain('public function author(): BelongsTo');

    expect(file_get_contents($documentPath))
        ->toContain('class Document extends Model')
        ->toContain('public function documentable(): MorphTo')
        ->toContain('public function uploader(): BelongsTo');

    expect(file_get_contents($departmentPath))
        ->toContain('HasProjectManagementAssignments')
        ->toContain('HasProjectManagementExecutions');

    expect(file_get_contents($groupPath))
        ->toContain('HasProjectManagementAssignments')
        ->toContain('HasProjectManagementExecutions');

    expect(file_get_contents($traitNotesPath))
        ->toContain('function notesLogs(): MorphMany')
        ->toContain("morphMany(NotesLog::class, 'notable')");

    expect(file_get_contents($traitDocumentsPath))
        ->toContain('function documents(): MorphMany')
        ->toContain("morphMany(Document::class, 'documentable')");
});
