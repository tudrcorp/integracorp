<?php

declare(strict_types=1);

use App\Livewire\CrmIncomingAlertModal;
use App\Models\CrmAnalystPresence;
use App\Models\CrmHandoffEnvelope;
use App\Models\User;
use App\Support\CrmInbox\CrmInboxCache;
use App\Support\CrmInbox\CrmInboxNotices;
use App\Support\CrmInbox\CrmInboxPresence;
use App\Support\CrmInbox\CrmInboxPulse;
use App\Support\CrmInbox\CrmIncomingAlerts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Modal «llegó una conversación» del CRM. Escribe dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    CrmInboxCache::reset();
    CrmIncomingAlerts::forgetMemo();
    CrmInboxPresence::forgetSchema();

    $this->makeUser = function (array $departments, int $id, string $status = 'ACTIVO', string $email = 'qa@tudrencasa.com'): User {
        $user = (new User)->forceFill(['name' => 'Ana Pérez', 'email' => $email, 'status' => $status, 'departament' => $departments]);
        $user->id = $id;
        $user->syncOriginal();

        return $user;
    };

    $this->me = ($this->makeUser)(['NEGOCIOS', 'SUPERADMIN'], random_int(900000000, 909999999));
    $this->envelope = fn (array $extra = []): CrmHandoffEnvelope => CrmHandoffEnvelope::query()->create([
        'handoff_id' => (string) random_int(800000000, 899999999),
        'phone' => '58414'.random_int(1000000, 9999999),
        'area' => 'comercial',
        'motivo' => 'Quiere cotizar un plan familiar',
        'payload' => ['name' => 'María José Rivas', 'ultimo_mensaje' => '¿Cuánto cuesta para 4 personas?'],
        'accepted_at' => now()->subMinutes(3),
        ...$extra,
    ]);
    $this->ids = fn (array $result): array => array_column($result['items'], 'id');
});

afterEach(function (): void {
    Auth::logout();
    DB::rollBack();
});

it('solo la ve quien atiende un área del CRM y está activo', function (): void {
    expect(CrmIncomingAlerts::panelsFor($this->me))->toBe(['business', 'operations', 'administration'])
        ->and(CrmIncomingAlerts::receives(($this->makeUser)(['MARKETING'], 910000001)))->toBeFalse()
        ->and(CrmIncomingAlerts::receives(($this->makeUser)(['SUPERADMIN'], 910000002, 'INACTIVO')))->toBeFalse()
        ->and(CrmIncomingAlerts::receives(($this->makeUser)(['SUPERADMIN'], 910000003, 'ACTIVO', 'externo@gmail.com')))->toBeFalse()
        ->and(CrmIncomingAlerts::receives(null))->toBeFalse();
});

it('muestra lo sin tomar y lo que te pasaron, primero lo tuyo, con el detalle del caso', function (): void {
    $mine = (int) $this->me->id;
    $colleague = 920000001;
    CrmAnalystPresence::query()->create(['user_id' => $colleague, 'status' => 'available', 'status_at' => now(), 'seen_at' => now()]);

    $new = ($this->envelope)();
    $assigned = ($this->envelope)(['assigned_to' => $mine, 'assigned_at' => now(), 'accepted_at' => now()->subMinute()]);
    $forColleague = ($this->envelope)(['assigned_to' => $colleague, 'assigned_at' => now()]);
    $taken = ($this->envelope)(['taken_at' => now(), 'taken_by' => $colleague]);
    $closed = ($this->envelope)(['closed_at' => now()]);
    $moved = ($this->envelope)(['area' => 'emergencias', 'moved_at' => now(), 'moved_by' => $colleague]);

    $result = CrmIncomingAlerts::pendingFor($this->me);
    $ids = ($this->ids)($result);
    $items = collect($result['items'])->keyBy('id');

    expect($ids[0])->toBe((string) $assigned->handoff_id)
        ->and($ids)->toContain((string) $new->handoff_id)
        ->and($ids)->not->toContain((string) $forColleague->handoff_id)
        ->and($ids)->not->toContain((string) $taken->handoff_id)
        ->and($ids)->not->toContain((string) $closed->handoff_id)
        ->and($items[(string) $assigned->handoff_id]['kind'])->toBe('assign')
        ->and($items[(string) $assigned->handoff_id]['title'])->toBe('Te pasaron una conversación')
        ->and($items[(string) $new->handoff_id]['kind'])->toBe('new')
        ->and($items[(string) $new->handoff_id]['initials'])->toBe('MJ')
        ->and($items[(string) $new->handoff_id]['motivo'])->toBe('Quiere cotizar un plan familiar')
        ->and($items[(string) $new->handoff_id]['last_line'])->toBe('¿Cuánto cuesta para 4 personas?')
        ->and($items[(string) $new->handoff_id]['url'])->toContain('/business/atencion-whatsapp?caso='.$new->handoff_id);

    if (isset($items[(string) $moved->handoff_id])) {
        expect($items[(string) $moved->handoff_id]['kind'])->toBe('move')
            ->and($items[(string) $moved->handoff_id]['url'])->toContain('/operations/atencion-whatsapp');
    }
});

it('lo marcado para un compañero ausente sí le llega a los demás', function (): void {
    $absent = 920000002;
    CrmAnalystPresence::query()->create(['user_id' => $absent, 'status' => 'away', 'status_at' => now(), 'seen_at' => now()]);
    $case = ($this->envelope)(['assigned_to' => $absent, 'assigned_at' => now()]);

    expect(($this->ids)(CrmIncomingAlerts::pendingFor($this->me)))->toContain((string) $case->handoff_id);
});

it('«recordar en 1 min» lo saca de la modal y avisa cuándo vuelve', function (): void {
    $case = ($this->envelope)();
    $id = (string) $case->handoff_id;

    CrmIncomingAlerts::snooze((int) $this->me->id, [$id, "x'; DROP"]);
    $result = CrmIncomingAlerts::pendingFor($this->me);

    expect(($this->ids)($result))->not->toContain($id)
        ->and($result['wake_at'])->toBeGreaterThan(time())
        ->and($result['wake_at'])->toBeLessThanOrEqual(time() + CrmIncomingAlerts::SNOOZE_SECONDS)
        ->and(CrmIncomingAlerts::snoozed((int) $this->me->id, [$id]))->toHaveKey($id);
});

it('el componente suena con lo nuevo, se cierra con recordar y calla si el analista está ausente', function (): void {
    Auth::login($this->me);
    $case = ($this->envelope)();
    $id = (string) $case->handoff_id;

    $component = Livewire::test(CrmIncomingAlertModal::class)->call('refresh');

    expect(array_column($component->get('items'), 'id'))->toContain($id)
        ->and($component->get('announced'))->toContain($id)
        ->and($component->html())->toContain('Tomar y responder')->toContain('Recordar en 1 min')->toContain('María José Rivas');

    $component->call('remind');
    expect(array_column($component->get('items'), 'id'))->not->toContain($id);

    CrmInboxPresence::set((int) $this->me->id, CrmInboxPresence::AWAY);
    $other = ($this->envelope)();
    $component->call('refresh');

    expect($component->get('items'))->toBe([])
        ->and(CrmAnalystPresence::query()->where('user_id', $this->me->id)->value('status'))->toBe('away');
});

it('si otro analista lo toma, desaparece de la modal', function (): void {
    Auth::login($this->me);
    $case = ($this->envelope)();
    $component = Livewire::test(CrmIncomingAlertModal::class)->call('refresh');

    CrmHandoffEnvelope::query()->whereKey($case->id)->update(['taken_at' => now(), 'taken_by' => 920000003]);
    CrmInboxPulse::bump();
    $component->call('refresh');

    expect(array_column($component->get('items'), 'id'))->not->toContain((string) $case->handoff_id);
});

it('«tomar» de un caso que ya tomó otro no lo vuelve a tomar', function (): void {
    Auth::login($this->me);
    $case = ($this->envelope)(['taken_at' => now(), 'taken_by' => 920000004]);

    Livewire::test(CrmIncomingAlertModal::class)->call('take', (string) $case->handoff_id);

    expect((int) $case->fresh()->taken_by)->toBe(920000004);
});

it('una conversación nueva no sale además como aviso flotante para quien tiene la modal', function (): void {
    Auth::login($this->me);
    $case = ($this->envelope)();
    session()->forget('filament.notifications');

    CrmInboxNotices::toast('Nueva conversación', 'María · Comercial', (string) $case->handoff_id);
    CrmInboxNotices::toast('Caso sin tomar', 'María · Comercial', (string) $case->handoff_id);

    $titles = array_column((array) session('filament.notifications', []), 'title');

    expect($titles)->not->toContain('Nueva conversación')->and($titles)->toContain('Caso sin tomar');
});

it('vive en los cinco paneles y la bandeja no repite el aviso de llegada', function (): void {
    foreach (['Business', 'Operations', 'Administration', 'Marketing', 'Metrics'] as $panel) {
        expect(file_get_contents(app_path("Providers/Filament/{$panel}PanelProvider.php")))->toContain("view('filament.hooks.crm-incoming-alert')");
    }

    expect(file_get_contents(resource_path('views/filament/hooks/crm-incoming-alert.blade.php')))->toContain('@persist(')
        ->and(file_get_contents(resource_path('views/filament/crm/pages/atencion-whatsapp.blade.php')))->toContain('$incomingModal');
});
