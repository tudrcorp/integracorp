<?php

declare(strict_types=1);

it('define estructura de datos para actividades de agenda corporativa', function (): void {
    $activitiesMigrationPath = dirname(__DIR__, 2).'/database/migrations/2026_04_20_174753_create_corporate_agenda_activities_table.php';
    $scheduleMigrationPath = dirname(__DIR__, 2).'/database/migrations/2026_04_21_000001_add_schedule_time_to_corporate_agenda_activities_table.php';
    $participantsMigrationPath = dirname(__DIR__, 2).'/database/migrations/2026_04_20_174754_create_corporate_agenda_activity_participants_table.php';
    $participantResponseMigrationPath = dirname(__DIR__, 2).'/database/migrations/2026_04_21_001000_add_response_note_to_corporate_agenda_activity_participants_table.php';
    $notesMigrationPath = dirname(__DIR__, 2).'/database/migrations/2026_04_20_174754_create_corporate_agenda_activity_notes_table.php';

    $activitiesMigration = file_get_contents($activitiesMigrationPath);
    $scheduleMigration = file_get_contents($scheduleMigrationPath);
    $participantsMigration = file_get_contents($participantsMigrationPath);
    $participantResponseMigration = file_get_contents($participantResponseMigrationPath);
    $notesMigration = file_get_contents($notesMigrationPath);

    expect($activitiesMigration)
        ->toContain("Schema::create('corporate_agenda_activities'")
        ->toContain("->foreignId('creator_user_id')->constrained('users')->cascadeOnDelete()")
        ->toContain("->date('activity_date')")
        ->toContain("->string('activity_type')")
        ->toContain("->boolean('has_google_meet')->default(false)")
        ->toContain("->string('google_meet_url')->nullable()")
        ->toContain("->text('description')");

    expect($scheduleMigration)
        ->toContain("Schema::table('corporate_agenda_activities'")
        ->toContain("->time('start_time')->default('08:00:00')")
        ->toContain("->time('end_time')->default('09:00:00')");

    expect($participantsMigration)
        ->toContain("Schema::create('corporate_agenda_activity_participants'")
        ->toContain("indexName: 'caap_activity_fk'")
        ->toContain("indexName: 'caap_colaborador_fk'")
        ->toContain("->string('invitation_status')->default('PENDING')");

    expect($participantResponseMigration)
        ->toContain("Schema::table('corporate_agenda_activity_participants'")
        ->toContain("->text('response_note')->nullable()");

    expect($notesMigration)
        ->toContain("Schema::create('corporate_agenda_activity_notes'")
        ->toContain("->foreignId('activity_id')->constrained('corporate_agenda_activities')->cascadeOnDelete()")
        ->toContain("->foreignId('user_id')->constrained('users')->cascadeOnDelete()")
        ->toContain("->text('note')");
});

it('expone acciones de agenda para crear, editar, responder invitaciones y notas', function (): void {
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Business/Pages/AgendaCorporativa.php';
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/business/pages/agenda-corporativa.blade.php';
    $shellPath = dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/corporate-calendar-shell.blade.php';
    $pageContents = file_get_contents($pagePath);
    $viewContents = file_get_contents($viewPath);
    $shellContents = file_get_contents($shellPath);

    expect($pageContents)
        ->toContain('openDayModal')
        ->toContain('saveActivity')
        ->toContain('deleteSelectedActivity')
        ->toContain('acceptMeet')
        ->toContain('rejectMeet')
        ->toContain('addNote')
        ->toContain('activityForm.start_time')
        ->toContain('activityForm.end_time')
        ->toContain('activityForm.department')
        ->toContain('CorporateAgendaDepartment::values()')
        ->toContain('invitationRejectionNote')
        ->toContain('Debes indicar el motivo del rechazo.')
        ->toContain('getCurrentParticipantForSelectedActivityProperty')
        ->toContain('json_encode($rawDepartments')
        ->toContain('Str::contains($normalizedDepartment, \'SUPERADMIN\')')
        ->toContain('resolveCurrentParticipantForActivity')
        ->toContain('after_or_equal:today')
        ->toContain('ensureSelectedCollaboratorsAreAvailable')
        ->toContain('updatedActivityFormActivityDate')
        ->toContain('No puedes seleccionar fechas pasadas para crear o mover actividades.')
        ->toContain('No puedes registrar actividades en días pasados.')
        ->toContain("'is_past_date' =>")
        ->toContain('if ($this->selectedActivityId === $activityId)')
        ->toContain('No se pudo guardar: estos colaboradores ya tienen actividad en ese rango horario')
        ->toContain('CorporateAgendaInvitationWhatsAppService::notifyCreatorAboutInvitationResponse')
        ->toContain('CorporateAgendaInvitationWhatsAppService::dispatchInvitationToParticipant');

    expect($shellContents)->toContain('wire:click="openDayModal(');

    expect($viewContents)
        ->toContain('wire:submit.prevent="saveActivity"')
        ->toContain('wire:model="activityForm.start_time"')
        ->toContain('wire:model="activityForm.end_time"')
        ->toContain('wire:model="activityForm.department"')
        ->toContain('wire:model.defer="invitationRejectionNote"')
        ->toContain('Tu confirmación de participación')
        ->toContain('Último motivo registrado')
        ->toContain('Motivo de rechazo')
        ->toContain('wire:click="acceptMeet(')
        ->toContain('wire:click="rejectMeet(')
        ->toContain('wire:click="addNote"');

    expect($pageContents)
        ->not->toContain('canSuperAdminRespondToMeet')
        ->not->toContain('superAdminAcceptParticipant')
        ->not->toContain('superAdminRejectParticipant')
        ->not->toContain('respondToParticipantAsSuperAdmin');

    expect($viewContents)
        ->not->toContain('wire:model.defer="superAdminRejectionNotes.')
        ->not->toContain('Confirmaciones de participantes (SUPERADMIN)')
        ->not->toContain('Aceptar (SUPERADMIN)')
        ->not->toContain('Rechazar (SUPERADMIN)');
});
