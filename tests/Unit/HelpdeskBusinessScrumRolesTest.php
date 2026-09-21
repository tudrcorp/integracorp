<?php

declare(strict_types=1);

use App\Models\HelpDesk;
use App\Models\User;
use App\Support\HelpdeskBusinessScrumRoles;
use App\Support\HelpdeskBusinessScrumWorkflow;
use App\Support\HelpdeskTaskStatusOptions;

it('reconoce a Becky, Anthony y Gustavo aunque el nombre en RRHH tenga más tokens', function (): void {
    expect(HelpdeskBusinessScrumRoles::isProductOwnerName('BECKY K, ACOSTA QUINTERO'))->toBeTrue()
        ->and(HelpdeskBusinessScrumRoles::isDeveloperName('ANTHONY JESUS AULAR GUZMAN'))->toBeTrue()
        ->and(HelpdeskBusinessScrumRoles::isDeveloperName('GUSTAVO CAMACHO'))->toBeTrue()
        ->and(HelpdeskBusinessScrumRoles::isProductOwnerName('Ana Pérez'))->toBeFalse()
        ->and(HelpdeskBusinessScrumRoles::isDeveloperName('Becky Acosta'))->toBeFalse();
});

it('identifica al Product Owner y a los desarrolladores por el nombre de usuario', function (): void {
    $productOwner = new User;
    $productOwner->name = 'Becky Acosta';

    $developer = new User;
    $developer->name = 'Gustavo Camacho';

    $analyst = new User;
    $analyst->name = 'Ana Pérez';

    expect(HelpdeskBusinessScrumRoles::isProductOwnerUser($productOwner))->toBeTrue()
        ->and(HelpdeskBusinessScrumRoles::isDeveloperUser($developer))->toBeTrue()
        ->and(HelpdeskBusinessScrumRoles::isProductOwnerUser($analyst))->toBeFalse()
        ->and(HelpdeskBusinessScrumRoles::isDeveloperUser($analyst))->toBeFalse();
});

it('encola el ticket nuevo al Product Owner solo si se asignó a Becky', function (): void {
    $scrum = HelpdeskBusinessScrumWorkflow::queueForProductOwner([
        'description' => 'Falla al emitir cotización',
        'rrhhColaboradores' => [6],
    ], 6);

    $normal = HelpdeskBusinessScrumWorkflow::queueForProductOwner([
        'description' => 'Consulta directa',
        'rrhhColaboradores' => [99],
        'status' => HelpdeskTaskStatusOptions::STATUS_PENDING,
    ], 6);

    expect(HelpdeskBusinessScrumWorkflow::isQueuedForProductOwner(['rrhhColaboradores' => 6], 6))->toBeTrue()
        ->and(HelpdeskBusinessScrumWorkflow::isQueuedForProductOwner(['rrhhColaboradores' => [99]], 6))->toBeFalse()
        ->and($scrum['rrhhColaboradores'])->toBe([6])
        ->and($scrum['status'])->toBe(HelpdeskTaskStatusOptions::STATUS_PENDING)
        ->and($normal['rrhhColaboradores'])->toBe([99])
        ->and(HelpdeskBusinessScrumWorkflow::assignmentIsProductOwnerInbox([6], 6, [12, 20]))->toBeTrue()
        ->and(HelpdeskBusinessScrumWorkflow::assignmentIsProductOwnerInbox([6, 20], 6, [12, 20]))->toBeFalse()
        ->and(HelpdeskBusinessScrumWorkflow::assignmentIsProductOwnerInbox([20], 6, [12, 20]))->toBeFalse();
});

it('solo el Product Owner puede revertir o asignar, y no sobre un ticket terminado', function (): void {
    $productOwner = new User;
    $productOwner->name = 'Becky Acosta';

    $developer = new User;
    $developer->name = 'Anthony Aular';

    $open = new HelpDesk;
    $open->status = HelpdeskTaskStatusOptions::STATUS_PENDING;

    $done = new HelpDesk;
    $done->status = HelpdeskTaskStatusOptions::STATUS_DONE;

    $reverted = new HelpDesk;
    $reverted->status = HelpdeskTaskStatusOptions::STATUS_REVERTED;

    expect(HelpdeskBusinessScrumWorkflow::canRevertToAnalyst($open, $productOwner, true))->toBeTrue()
        ->and(HelpdeskBusinessScrumWorkflow::canRevertToAnalyst($open, $productOwner, false))->toBeFalse()
        ->and(HelpdeskBusinessScrumWorkflow::canRevertToAnalyst($open, $developer, true))->toBeFalse()
        ->and(HelpdeskBusinessScrumWorkflow::canRevertToAnalyst($done, $productOwner, true))->toBeFalse()
        ->and(HelpdeskBusinessScrumWorkflow::canRevertToAnalyst($reverted, $productOwner, true))->toBeFalse()
        ->and(HelpdeskBusinessScrumWorkflow::canAssignToSprint($open, $productOwner, true))->toBeTrue()
        ->and(HelpdeskBusinessScrumWorkflow::canAssignToSprint($open, $productOwner, false))->toBeFalse()
        ->and(HelpdeskBusinessScrumWorkflow::canAssignToSprint($open, $developer, true))->toBeFalse()
        ->and(HelpdeskBusinessScrumWorkflow::canAssignToSprint($done, $productOwner, true))->toBeFalse()
        ->and(HelpdeskBusinessScrumWorkflow::canAssignToSprint($reverted, $productOwner, true))->toBeTrue();
});

it('cada panel helpdesk cablea creación, acciones Scrum y visibilidad al Product Owner', function (string $panel): void {
    $base = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks";
    $create = file_get_contents("{$base}/Pages/CreateHelpdesk.php");
    $actions = file_get_contents("{$base}/Actions/HelpdeskTicketModalActions.php");
    $view = file_get_contents("{$base}/Pages/ViewHelpdesk.php");
    $edit = file_get_contents("{$base}/Pages/EditHelpdesk.php");

    expect($create)
        ->toContain('HelpdeskBusinessScrumWorkflow::queueForProductOwner')
        ->toContain('HelpdeskBusinessScrumWorkflow::isQueuedForProductOwner')
        ->toContain('HelpdeskBusinessScrumWorkflow::ticketIsProductOwnerInbox')
        ->toContain('HelpdeskBusinessScrumWorkflow::syncProductOwnerInbox')
        ->toContain('productOwnerReadinessError')
        ->and($actions)
        ->toContain('makeRevertToAnalystAction')
        ->toContain('makeAssignToSprintAction')
        ->and($view)
        ->toContain('makeRevertToAnalystAction')
        ->toContain('makeAssignToSprintAction')
        ->toContain('creatorResubmitButtonLabel')
        ->and($edit)
        ->toContain('canCreatorResubmit')
        ->toContain('prepareCreatorResubmitData')
        ->toContain('finalizeCreatorResubmit')
        ->toContain('creatorResubmitSaveLabel');
})->with(['Business', 'Administration', 'Marketing', 'Operations']);

it('las acciones modales compartidas implementan revertir y asignar al sprint', function (): void {
    $shared = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/Helpdesks/Actions/HelpdeskTicketModalActions.php');
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskFormSchema.php');
    $workflow = file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskBusinessScrumWorkflow.php');

    expect($shared)
        ->toContain("Action::make('scrumRevertToAnalyst')")
        ->toContain("Action::make('scrumAssignToSprint')")
        ->toContain('HelpdeskBusinessScrumWorkflow::revertToAnalyst')
        ->toContain('HelpdeskBusinessScrumWorkflow::assignToSprint')
        ->and($workflow)
        ->toContain('buildRevertedToCreatorBody')
        ->toContain('buildSprintReassignedBody')
        ->toContain('dispatchToTicketCreatorWithReport')
        ->toContain('sendRevertedToCreatorWithReport')
        ->toContain('canCreatorResubmit')
        ->toContain('prepareCreatorResubmitData')
        ->toContain('finalizeCreatorResubmit')
        ->toContain('buildResubmittedToProductOwnerBody')
        ->and($form)
        ->toContain('scrumProductOwnerInbox')
        ->toContain('Becky Acosta')
        ->toContain('disableOnEditUnlessRevertedResubmit')
        ->toContain('ticket_resubmit_intro');
});

it('el creador puede reenviar un ticket revertido al Product Owner', function (): void {
    $creator = new User;
    $creator->id = 10;
    $creator->name = 'Ana Pérez';

    $other = new User;
    $other->id = 11;
    $other->name = 'Pedro Gómez';

    $reverted = new HelpDesk;
    $reverted->status = HelpdeskTaskStatusOptions::STATUS_REVERTED;
    $reverted->created_by = 'Ana Pérez';
    $reverted->created_by_user_id = 10;
    $reverted->priority = 'MEDIA';
    $reverted->description = 'Falta captura';

    $pending = new HelpDesk;
    $pending->status = HelpdeskTaskStatusOptions::STATUS_PENDING;
    $pending->created_by_user_id = 10;

    expect(HelpdeskBusinessScrumWorkflow::ticketAllowsContentResubmit($reverted))->toBeTrue()
        ->and(HelpdeskBusinessScrumWorkflow::canCreatorResubmit($reverted, $creator))->toBeTrue()
        ->and(HelpdeskBusinessScrumWorkflow::canCreatorResubmit($reverted, $other))->toBeFalse()
        ->and(HelpdeskBusinessScrumWorkflow::canCreatorResubmit($pending, $creator))->toBeFalse();

    $prepared = HelpdeskBusinessScrumWorkflow::prepareCreatorResubmitData($reverted, [
        'description' => 'Falla al emitir cotización. Adjunto captura.',
        'priority' => 'ALTA',
        'ticket_type' => 'incident',
        'cc_colaboradores' => [6],
    ], $creator);

    expect($prepared['description'])->toBe('Falla al emitir cotización. Adjunto captura.')
        ->and($prepared['priority'])->toBe('ALTA')
        ->and($prepared['status'])->toBe(HelpdeskTaskStatusOptions::STATUS_PENDING)
        ->and($prepared['cancellation_reason'])->toBeNull()
        ->and($prepared['created_by'])->toBe('Ana Pérez')
        ->and($prepared['updated_by'])->toBe('Ana Pérez')
        ->and($prepared['first_responded_at'])->toBeNull()
        ->and($prepared['cc_colaboradores'])->toBe([6]);
});

it('la auditoría de edición no convierte arrays a string', function (): void {
    $page = (new ReflectionClass(App\Filament\Business\Resources\Helpdesks\Pages\EditHelpdesk::class))
        ->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(App\Filament\Business\Resources\Helpdesks\Pages\EditHelpdesk::class, 'diffAuditChanges');

    $record = new HelpDesk;
    $record->cc_colaboradores = [6];
    $record->description = 'Caso original';
    $record->priority = 'MEDIA';

    $changes = $method->invoke($page, $record, [
        'cc_colaboradores' => [6],
        'description' => 'Caso original',
        'priority' => 'ALTA',
    ]);

    expect($changes)->toHaveKey('priority')
        ->and($changes)->not->toHaveKey('cc_colaboradores')
        ->and($changes)->not->toHaveKey('description');
});

it('la vista y edición de cada panel permiten corregir y reenviar el ticket revertido', function (string $panel): void {
    $base = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Pages";
    $view = file_get_contents("{$base}/ViewHelpdesk.php");
    $edit = file_get_contents("{$base}/EditHelpdesk.php");

    expect($view)
        ->toContain('creatorResubmitButtonLabel')
        ->toContain('ticketAllowsContentResubmit')
        ->and($edit)
        ->toContain('canCreatorResubmit')
        ->toContain('prepareCreatorResubmitData')
        ->toContain('finalizeCreatorResubmit')
        ->toContain('creatorResubmitSaveLabel')
        ->toContain('auditValuesAreEquivalent');
})->with(['Business', 'Administration', 'Marketing', 'Operations']);

it('actualizar estado oculta la acción cuando el creador debe reenviar el ticket revertido', function (): void {
    $shared = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/Helpdesks/Actions/HelpdeskTicketModalActions.php');

    expect($shared)
        ->toContain('HelpdeskBusinessScrumWorkflow::canCreatorResubmit')
        ->toContain('HelpdeskTaskStatusOptions::terminalStatuses()');
});
