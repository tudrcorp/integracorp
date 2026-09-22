<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

use App\Jobs\NotifyProspectAgentTaskAssigneeJob;
use App\Jobs\SendNotificacionWhatsApp;
use App\Mail\ProspectAgentTaskAssignedMail;
use App\Models\ProspectAgent;
use App\Models\ProspectAgentTask;
use App\Models\RrhhColaborador;
use App\Models\User;
use App\Support\Companies\CompanyAssociateDocumentsBellAlert;
use App\Support\ProspectAgents\ProspectAgentTaskAssigneeNotifier;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    DB::beginTransaction();
    Cache::setDefaultDriver('array');
});

afterEach(function (): void {
    DB::rollBack();
});

it('arma un mensaje de whatsapp con prospecto, responsable y descripción', function (): void {
    $body = ProspectAgentTaskAssigneeNotifier::whatsAppBody(
        taskId: 88,
        prospectName: 'VIAJES Y TURISMOS IFAMIL',
        assignedBy: 'Ana Pérez',
        description: 'Llamar al prospecto para agendar una reunión.',
        assignedAt: '22/09/2026 11:40',
        prospectUrl: 'https://www.integracorp.test/business/prospect-agents/163',
    );

    expect($body)
        ->toContain('Tarea N.º 88')
        ->toContain('Prospecto: VIAJES Y TURISMOS IFAMIL')
        ->toContain('Asignada por: Ana Pérez')
        ->toContain('22/09/2026 11:40')
        ->toContain('Llamar al prospecto para agendar una reunión.')
        ->toContain('https://www.integracorp.test/business/prospect-agents/163');
});

it('prefiere el correo y el teléfono corporativos del colaborador', function (): void {
    $colaborador = new RrhhColaborador([
        'emailCorporativo' => 'Christopher@TuDrEnCasa.com',
        'emailPersonal' => 'personal@example.com',
        'telefonoCorporativo' => '04141234567',
        'telefono' => '04129998877',
    ]);

    expect(ProspectAgentTaskAssigneeNotifier::recipientEmail($colaborador))->toBe('christopher@tudrencasa.com')
        ->and(ProspectAgentTaskAssigneeNotifier::recipientPhone($colaborador))->toBe('+584141234567');
});

it('avisa por whatsapp, correo y campana sin repetir el envío', function (): void {
    Bus::fake();
    Mail::fake();
    Notification::fake();

    $task = prospectAgentTaskForNotice();

    ProspectAgentTaskAssigneeNotifier::deliver($task);
    ProspectAgentTaskAssigneeNotifier::deliver($task);

    Bus::assertDispatchedTimes(SendNotificacionWhatsApp::class, 1);
    Bus::assertDispatched(SendNotificacionWhatsApp::class, function (SendNotificacionWhatsApp $job): bool {
        return $job->phone === '+584141234567'
            && str_contains($job->body, 'VIAJES Y TURISMOS IFAMIL')
            && str_contains($job->body, 'Ana Pérez')
            && str_contains($job->body, 'Llamar al prospecto para agendar una reunión.');
    });

    Mail::assertQueued(ProspectAgentTaskAssignedMail::class, function (ProspectAgentTaskAssignedMail $mail): bool {
        return $mail->hasTo('christopher@tudrencasa.com')
            && $mail->prospectName === 'VIAJES Y TURISMOS IFAMIL'
            && $mail->assignedBy === 'Ana Pérez'
            && str_contains($mail->taskDescription, 'Llamar al prospecto');
    });

    expect(CompanyAssociateDocumentsBellAlert::consume(90421))->toBeTrue()
        ->and(CompanyAssociateDocumentsBellAlert::consume(90421))->toBeFalse();
});

it('sigue con correo y campana cuando el colaborador no tiene teléfono', function (): void {
    Bus::fake();
    Mail::fake();
    Notification::fake();

    $task = prospectAgentTaskForNotice(phone: null);

    ProspectAgentTaskAssigneeNotifier::deliver($task);

    Bus::assertNotDispatched(SendNotificacionWhatsApp::class);
    Mail::assertQueued(ProspectAgentTaskAssignedMail::class);
    expect(CompanyAssociateDocumentsBellAlert::consume(90421))->toBeTrue();
});

it('sigue con whatsapp y correo cuando el colaborador no tiene usuario de campana', function (): void {
    Bus::fake();
    Mail::fake();
    Notification::fake();

    $task = prospectAgentTaskForNotice(withUser: false);

    ProspectAgentTaskAssigneeNotifier::deliver($task);

    Bus::assertDispatched(SendNotificacionWhatsApp::class);
    Mail::assertQueued(ProspectAgentTaskAssignedMail::class);
    expect(CompanyAssociateDocumentsBellAlert::consume(90421))->toBeFalse();
});

it('encola el aviso después de guardar la tarea en la tabla y en la ficha del prospecto', function (): void {
    $list = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/ProspectAgents/Pages/ListProspectAgents.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/ProspectAgents/Pages/ViewProspectAgent.php');
    $job = new NotifyProspectAgentTaskAssigneeJob(15);

    expect($list)
        ->toContain('NotifyProspectAgentTaskAssigneeJob::dispatch((int) $task->getKey())')
        ->toContain('El colaborador recibirá el aviso por WhatsApp, correo y la campana del panel.')
        ->and($view)
        ->toContain('NotifyProspectAgentTaskAssigneeJob::dispatch((int) $task->getKey())')
        ->toContain('El colaborador recibirá el aviso por WhatsApp, correo y la campana del panel.')
        ->toContain('$this->redirectMethod($record->getKey())')
        ->and($job)->toBeInstanceOf(ShouldQueueAfterCommit::class)
        ->and($job->taskId)->toBe(15);
});

function prospectAgentTaskForNotice(?string $phone = '04141234567', bool $withUser = true): ProspectAgentTask
{
    $prospect = new ProspectAgent(['name' => 'VIAJES Y TURISMOS IFAMIL']);
    $prospect->id = 163;
    $prospect->exists = true;

    $colaborador = new RrhhColaborador([
        'fullName' => 'Christopher D. Reyes Rodríguez',
        'emailCorporativo' => 'christopher@tudrencasa.com',
        'telefonoCorporativo' => $phone,
        'user_id' => $withUser ? 90421 : null,
    ]);
    $colaborador->id = 44;
    $colaborador->exists = true;

    if ($withUser) {
        $user = new User;
        $user->id = 90421;
        $user->exists = true;
        $colaborador->setRelation('user', $user);
    } else {
        $colaborador->setRelation('user', null);
    }

    $task = new ProspectAgentTask([
        'prospect_agent_id' => 163,
        'rrhh_colaborador_id' => 44,
        'task' => 'Llamar al prospecto para agendar una reunión.',
        'created_by' => 'Ana Pérez',
    ]);
    $task->id = 88;
    $task->exists = true;
    $task->setRelation('prospect_agent', $prospect);
    $task->setRelation('rrhh_colaborador', $colaborador);

    return $task;
}
