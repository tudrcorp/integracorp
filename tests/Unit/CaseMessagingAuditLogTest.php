<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

use App\Models\TelemedicineCase;
use App\Models\TelemedicineCaseMessage;
use App\Models\User;
use App\Support\Operations\CaseMessagingAuditLog;

it('expone el contexto de auditoria para la bitacora de mensajeria del caso', function (): void {
    $case = new TelemedicineCase([
        'code' => 'caso-001',
        'status' => 'EN SEGUIMIENTO',
        'managed_by' => 'ATENMEDI',
        'patient_name' => 'Paciente Demo',
    ]);
    $case->id = 99;

    $user = new User([
        'name' => 'Analista Uno',
        'email' => 'analista@example.com',
        'departament' => ['OPERACIONES'],
    ]);
    $user->id = 7;

    $firstMessage = new TelemedicineCaseMessage([
        'telemedicine_case_id' => 99,
        'user_id' => 7,
        'body' => 'Primer mensaje de seguimiento',
    ]);
    $firstMessage->id = 1;
    $firstMessage->setRawAttributes(array_merge($firstMessage->getAttributes(), [
        'created_at' => '2026-06-01 09:15:00',
        'updated_at' => '2026-06-01 09:15:00',
    ]));
    $firstMessage->setRelation('user', $user);

    $secondMessage = new TelemedicineCaseMessage([
        'telemedicine_case_id' => 99,
        'user_id' => 7,
        'body' => 'Segundo mensaje con detalle clínico',
    ]);
    $secondMessage->id = 2;
    $secondMessage->setRawAttributes(array_merge($secondMessage->getAttributes(), [
        'created_at' => '2026-06-02 14:30:00',
        'updated_at' => '2026-06-02 14:30:00',
    ]));
    $secondMessage->setRelation('user', $user);

    $case->setRelation('caseMessages', collect([$firstMessage, $secondMessage]));

    $context = CaseMessagingAuditLog::viewContext($case);

    expect($context)
        ->toHaveKeys(['caseCode', 'caseStatus', 'managedBy', 'patientName', 'stats', 'participants', 'messages'])
        ->and($context['caseCode'])->toBe('caso-001')
        ->and($context['stats']['total'])->toBe(2)
        ->and($context['stats']['participants'])->toBe(1)
        ->and($context['participants'])->toHaveCount(1)
        ->and($context['participants'][0]['message_count'])->toBe(2)
        ->and($context['messages'])->toHaveCount(2)
        ->and($context['messages'][0]['id'])->toBe(1)
        ->and($context['messages'][0]['body'])->toBe('Primer mensaje de seguimiento')
        ->and($context['messages'][0]['thread_position'])->toBe('start')
        ->and($context['messages'][0]['show_author_header'])->toBeTrue()
        ->and($context['messages'][1]['show_date_divider'])->toBeTrue()
        ->and($context['messages'][1]['thread_position'])->toBe('end');
});

it('agrupa mensajes consecutivos del mismo analista en el hilo conversacional', function (): void {
    $case = new TelemedicineCase(['code' => 'caso-002']);
    $case->id = 100;

    $user = new User(['name' => 'Analista A', 'email' => 'a@example.com']);
    $user->id = 1;

    $messages = collect();

    foreach ([1, 2, 3] as $id) {
        $message = new TelemedicineCaseMessage([
            'telemedicine_case_id' => 100,
            'user_id' => 1,
            'body' => 'Mensaje '.$id,
        ]);
        $message->id = $id;
        $message->setRawAttributes(array_merge($message->getAttributes(), [
            'created_at' => '2026-06-01 10:0'.$id.':00',
            'updated_at' => '2026-06-01 10:0'.$id.':00',
        ]));
        $message->setRelation('user', $user);
        $messages->push($message);
    }

    $case->setRelation('caseMessages', $messages);

    $context = CaseMessagingAuditLog::viewContext($case);

    expect($context['messages'][0]['thread_position'])->toBe('start')
        ->and($context['messages'][1]['thread_position'])->toBe('middle')
        ->and($context['messages'][1]['show_author_header'])->toBeFalse()
        ->and($context['messages'][2]['thread_position'])->toBe('end')
        ->and($context['participants'][0]['lane'])->toBe('left');
});

it('incluye tab y vista de bitacora de mensajeria en el infolist de casos', function (): void {
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/Schemas/TelemedicineCaseInfolist.php');
    $resource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/TelemedicineCaseResource.php');
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/telemedicine-cases/case-messaging-audit-log.blade.php');

    expect($infolist)
        ->toContain("Tab::make('Bitácora Mensajería Interna')")
        ->toContain('CaseMessagingAuditLog::viewContext')
        ->toContain('case-messaging-audit-log');

    expect($resource)
        ->toContain('caseMessages.user:id,name,email')
        ->not->toContain('caseMessages.user:id,name,email,departament');

    expect($view)
        ->toContain('fi-case-messaging-audit')
        ->toContain('fi-case-messaging-audit-thread')
        ->toContain('fi-case-messaging-audit-message')
        ->toContain('filteredMessages()')
        ->toContain('scrollToLatest()')
        ->toContain('Final del hilo')
        ->not->toContain('author_departments');
});
