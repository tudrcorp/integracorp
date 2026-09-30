<?php

declare(strict_types=1);

use App\Livewire\Operations\CaseFollowUpChatPanel;
use App\Models\User;
use App\Support\Operations\CaseFollowUpChatManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();

    $this->me = User::factory()->create(['departament' => ['SUPERADMIN', 'OPERACIONES'], 'supplier_id' => null]);
    $this->other = User::factory()->create(['departament' => ['OPERACIONES']]);
    Auth::setUser($this->me);

    $now = now();
    $this->caseIds = [];

    foreach (range(1, 45) as $i) {
        $this->caseIds[] = DB::table('telemedicine_cases')->insertGetId([
            'telemedicine_patient_id' => 0,
            'code' => sprintf('ZZPERF-%03d', $i),
            'patient_name' => $i === 7 ? 'PACIENTE BUSCABLE ZZ' : 'PACIENTE '.$i,
            'status' => CaseFollowUpChatManager::FOLLOW_UP_STATUS,
            'managed_by' => 'TDG',
            'created_at' => $now,
            'updated_at' => $now->copy()->addSeconds($i),
        ]);
    }

    $this->message = function (int $caseId, User $author, string $body = 'hola', ?Carbon\CarbonInterface $at = null): int {
        $at ??= now();

        return DB::table('telemedicine_case_messages')->insertGetId([
            'telemedicine_case_id' => $caseId, 'user_id' => $author->id, 'body' => $body,
            'created_at' => $at, 'updated_at' => $at,
        ]);
    };
});

afterEach(fn () => DB::rollBack());

function countQueries(callable $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $callback();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('cuenta los no leídos de todos los casos en una sola consulta', function (): void {
    [$a, $b, $c] = $this->caseIds;
    ($this->message)($a, $this->other);
    ($this->message)($a, $this->other);
    ($this->message)($b, $this->other, 'viejo', now()->subHour());
    ($this->message)($c, $this->me);
    DB::table('telemedicine_case_chat_reads')->insert([
        'telemedicine_case_id' => $b, 'user_id' => $this->me->id, 'last_read_at' => now()->subMinutes(5),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    CaseFollowUpChatManager::unreadCountsForUser($this->me);

    $counts = [];
    $queries = countQueries(function () use (&$counts): void {
        $counts = CaseFollowUpChatManager::unreadCountsForUser($this->me);
    });

    expect($queries)->toBe(1)
        ->and(array_intersect_key($counts, array_flip($this->caseIds)))->toBe([$a => 2]);
});

it('el ciclo de actualización hace pocas consultas sin importar cuántos casos haya y no reenvía HTML si nada cambió', function (): void {
    $component = Livewire::test(CaseFollowUpChatPanel::class);

    $closedQueries = countQueries(fn () => $component->call('pollHeartbeat'));
    expect($closedQueries)->toBeLessThanOrEqual(3)
        ->and($component->effects['html'] ?? null)->toBeNull();

    $component->call('openPanel');

    $openQueries = countQueries(fn () => $component->call('pollHeartbeat'));
    expect($openQueries)->toBeLessThanOrEqual(5)
        ->and($component->effects['html'] ?? null)->toBeNull();
});

it('el ciclo vuelve a pintar el chat cuando llega un mensaje al caso abierto', function (): void {
    $caseId = $this->caseIds[44];
    $component = Livewire::test(CaseFollowUpChatPanel::class)->call('openPanel', $caseId);

    ($this->message)($caseId, $this->other, 'mensaje nuevo ZZ');

    $component->call('pollHeartbeat')
        ->assertDispatched('operations-case-chat-incoming-message')
        ->assertSee('mensaje nuevo ZZ');

    expect($component->effects['html'] ?? null)->not->toBeNull();
});

it('pagina la lista de casos y busca en la base', function (): void {
    $page = CaseFollowUpChatManager::casesForChatList($this->me, CaseFollowUpChatManager::CONTEXT_OPERATIONS, '', 30);
    expect($page)->toHaveCount(31);

    $found = CaseFollowUpChatManager::casesForChatList($this->me, CaseFollowUpChatManager::CONTEXT_OPERATIONS, 'buscable zz', 30);
    expect($found->pluck('code')->all())->toBe(['ZZPERF-007']);

    $byCode = CaseFollowUpChatManager::casesForChatList($this->me, CaseFollowUpChatManager::CONTEXT_OPERATIONS, 'ZZPERF-04', 30);
    expect($byCode)->toHaveCount(6);

    $literal = CaseFollowUpChatManager::casesForChatList($this->me, CaseFollowUpChatManager::CONTEXT_OPERATIONS, 'ZZPERF_0', 30);
    expect($literal)->toHaveCount(0);

    $component = Livewire::test(CaseFollowUpChatPanel::class)->call('openPanel');
    expect($component->viewData('cases'))->toHaveCount(CaseFollowUpChatPanel::CASE_PAGE_SIZE)
        ->and($component->viewData('hasMoreCases'))->toBeTrue();

    $component->call('loadMoreCases');
    expect($component->get('caseListLimit'))->toBe(CaseFollowUpChatPanel::CASE_PAGE_SIZE * 2);
});

it('muestra los mensajes más recientes en orden cronológico', function (): void {
    $caseId = $this->caseIds[0];

    foreach (range(1, 60) as $i) {
        ($this->message)($caseId, $this->other, 'msg '.$i, now()->subMinutes(60 - $i));
    }

    $messages = CaseFollowUpChatManager::messagesForCase($caseId, 50);

    expect($messages)->toHaveCount(50)
        ->and($messages->first()->body)->toBe('msg 11')
        ->and($messages->last()->body)->toBe('msg 60');

    $component = Livewire::test(CaseFollowUpChatPanel::class)->call('openPanel', $caseId);
    expect($component->viewData('hasOlderMessages'))->toBeTrue();

    $component->call('loadOlderMessages');
    expect($component->viewData('messages'))->toHaveCount(60)
        ->and($component->viewData('hasOlderMessages'))->toBeFalse();
});

it('seleccionar un caso lo marca como leído', function (): void {
    $caseId = $this->caseIds[3];
    ($this->message)($caseId, $this->other);

    $component = Livewire::test(CaseFollowUpChatPanel::class);
    expect($component->get('unreadByCase'))->toHaveKey($caseId);

    $component->call('openPanel', $caseId);

    expect($component->get('unreadByCase'))->not->toHaveKey($caseId)
        ->and(CaseFollowUpChatManager::unreadCountsForUser($this->me))->not->toHaveKey($caseId);
});

it('escribir el mensaje no genera peticiones y el ciclo no vive en la raíz', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/livewire/operations/case-follow-up-chat-panel.blade.php');

    expect($view)
        ->toContain('wire:model="messageBody"')
        ->not->toContain('wire:model.live="messageBody"')
        ->toContain('wire:poll.3s="pollHeartbeat" wire:key="ops-case-chat-poll-open"')
        ->toContain('wire:poll.5s="pollHeartbeat" wire:key="ops-case-chat-poll-closed"')
        ->not->toContain('wire:target="pollHeartbeat');
});

it('muestra arriba los casos sin leer aunque queden fuera de la primera página', function (): void {
    $oldestCase = $this->caseIds[0];
    ($this->message)($oldestCase, $this->other, 'urgente ZZ');

    $component = Livewire::test(CaseFollowUpChatPanel::class)->call('openPanel', $this->caseIds[44]);

    expect($component->viewData('unreadCases')->pluck('id')->intersect($this->caseIds)->values()->all())->toBe([$oldestCase])
        ->and($component->viewData('cases')->pluck('id')->all())->not->toContain($oldestCase)
        ->and($component->viewData('previews')[$oldestCase]['body'])->toBe('urgente ZZ');

    $component->assertSee('Sin leer')
        ->assertSee('Todos los casos')
        ->assertSee('urgente ZZ');
});

it('al abrir el chat entra directo a la conversación sin leer más reciente', function (): void {
    ($this->message)($this->caseIds[2], $this->other, 'anterior', now()->subMinutes(10));
    ($this->message)($this->caseIds[5], $this->other, 'reciente', now()->subMinute());

    $summary = CaseFollowUpChatManager::unreadSummaryForUser($this->me);
    expect(array_slice(array_keys(array_intersect_key($summary, array_flip($this->caseIds))), 0, 2))
        ->toBe([$this->caseIds[5], $this->caseIds[2]]);

    $component = Livewire::test(CaseFollowUpChatPanel::class)->call('openPanel');

    expect($component->get('selectedCaseId'))->toBe($this->caseIds[5])
        ->and($component->get('unreadByCase'))->not->toHaveKey($this->caseIds[5])
        ->and($component->get('unreadByCase'))->toHaveKey($this->caseIds[2]);
});

it('marca con «Mensajes nuevos» el primer mensaje sin leer de la conversación', function (): void {
    $caseId = $this->caseIds[10];
    ($this->message)($caseId, $this->other, 'ya visto ZZ', now()->subHour());
    DB::table('telemedicine_case_chat_reads')->insert([
        'telemedicine_case_id' => $caseId, 'user_id' => $this->me->id, 'last_read_at' => now()->subMinutes(30),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $firstNew = ($this->message)($caseId, $this->other, 'nuevo 1', now()->subMinutes(5));
    ($this->message)($caseId, $this->other, 'nuevo 2', now()->subMinutes(4));

    expect(CaseFollowUpChatManager::firstUnreadMessageId($this->me, $caseId))->toBe($firstNew);

    Livewire::test(CaseFollowUpChatPanel::class)
        ->call('openPanel', $caseId)
        ->assertSet('unreadDividerMessageId', $firstNew)
        ->assertSeeInOrder(['ya visto ZZ', 'Mensajes nuevos', 'nuevo 1', 'nuevo 2']);
});

it('muestra «Todo al día» cuando no hay conversaciones sin leer', function (): void {
    $readAt = now()->addMinute();

    foreach (DB::table('telemedicine_case_messages')->distinct()->pluck('telemedicine_case_id') as $caseId) {
        DB::table('telemedicine_case_chat_reads')->insert([
            'telemedicine_case_id' => $caseId, 'user_id' => $this->me->id, 'last_read_at' => $readAt,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    Livewire::test(CaseFollowUpChatPanel::class)
        ->call('openPanel', $this->caseIds[44])
        ->set('onlyUnread', true)
        ->assertSee('Todo al d');
});

it('el filtro «Solo no leídos» deja solo las conversaciones pendientes', function (): void {
    ($this->message)($this->caseIds[20], $this->other, 'pendiente ZZ');

    $component = Livewire::test(CaseFollowUpChatPanel::class)
        ->call('openPanel', $this->caseIds[44])
        ->set('onlyUnread', true);

    expect($component->viewData('cases'))->toHaveCount(0)
        ->and($component->viewData('hasMoreCases'))->toBeFalse()
        ->and($component->viewData('unreadCases')->pluck('id')->intersect($this->caseIds)->values()->all())->toBe([$this->caseIds[20]]);

    $component->call('selectCase', $this->caseIds[20]);

    expect($component->viewData('unreadCases')->pluck('id')->intersect($this->caseIds)->all())->toBe([]);

    $component->set('caseSearch', 'ZZPERF');
    expect($component->viewData('unreadCases'))->toHaveCount(0);
    $component->assertSee('Sin resultados');
});
