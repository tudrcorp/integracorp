<?php

declare(strict_types=1);

use App\Support\GuiaChat\IntegracorpLoginPanels;
use App\Support\GuiaChat\ServiceMenuOption;
use Livewire\Volt\Volt;

uses(Tests\TestCase::class);

it('parsea nombre y apellido desde un solo mensaje', function (): void {
    expect(ServiceMenuOption::parseReporterFullName('Carlos Ruiz'))->toBe([
        'first_name' => 'Carlos',
        'last_name' => 'Ruiz',
    ])->and(ServiceMenuOption::parseReporterFullName('María José Gómez'))->toBe([
        'first_name' => 'María José',
        'last_name' => 'Gómez',
    ]);
});

it('expone las cinco opciones del menu de servicio guia-chat', function (): void {
    $keys = collect(ServiceMenuOption::catalog())->pluck('key')->all();

    expect($keys)->toBe([
        ServiceMenuOption::BUSINESS_ADVISOR,
        ServiceMenuOption::INTEGRACORP_LOGIN,
        ServiceMenuOption::SERVICE_SUGGESTION,
        ServiceMenuOption::GUIA_CHAT_BUG,
        ServiceMenuOption::INTEGRACORP_BUG,
    ]);
});

it('resalta guia-chat en la opcion de reporte de fallas del asistente', function (): void {
    $panel = file_get_contents(base_path('resources/views/pwa/partials/guia-chat-service-menu-panel.blade.php'));

    expect($panel)
        ->toContain('highlight_brand')
        ->toContain('bg-gradient-to-r from-emerald-300 via-cyan-300 to-teal-200')
        ->toContain('GUIA-CHAT');
});

it('renderiza el menu de servicio en el chat publico', function (): void {
    Volt::test('volt.public.ai_chat')
        ->assertSee('Menú GUIA-CHAT')
        ->assertSee('Comunicame con un Asesor de Negocios.')
        ->assertSee('Login directo en INTEGRACORP.')
        ->assertSee('Sugerencias para mejoras del servicio.')
        ->assertSee('Reportar fallas del sistema INTEGRACORP.');
});

it('conecta con asesor de negocios desde el menu de servicio', function (): void {
    Volt::test('volt.public.ai_chat')
        ->call('selectServiceMenuOption', ServiceMenuOption::BUSINESS_ADVISOR)
        ->assertSet('handoffRequested', true)
        ->assertSet('chatFeed.0.role', 'assistant');

    $reply = Volt::test('volt.public.ai_chat')
        ->call('selectServiceMenuOption', ServiceMenuOption::BUSINESS_ADVISOR)
        ->get('chatFeed')[0]['content'];

    expect($reply)
        ->toContain('Asesores Comerciales')
        ->toContain('https://wa.me/584127018390');
});

it('activa modo de sugerencia y confirma recepcion al enviar', function (): void {
    Volt::test('volt.public.ai_chat')
        ->call('selectServiceMenuOption', ServiceMenuOption::SERVICE_SUGGESTION)
        ->assertSet('serviceFeedbackMode', ServiceMenuOption::SERVICE_SUGGESTION)
        ->assertSet('serviceFeedbackStep', ServiceMenuOption::FEEDBACK_STEP_MESSAGE)
        ->set('draft', 'Me gustaría ver más planes corporativos.')
        ->call('sendMessage')
        ->assertSet('serviceFeedbackMode', null)
        ->assertSet('chatFeed.1.content', 'Me gustaría ver más planes corporativos.')
        ->assertSet('chatFeed.2.role', 'assistant');

    $ack = Volt::test('volt.public.ai_chat')
        ->call('selectServiceMenuOption', ServiceMenuOption::SERVICE_SUGGESTION)
        ->set('draft', 'Me gustaría ver más planes corporativos.')
        ->call('sendMessage')
        ->get('chatFeed')[2]['content'];

    expect($ack)->toContain('Recibimos tu sugerencia');
});

it('expone solo agencia master, agencia general y agente en login integracorp', function (): void {
    $labels = collect(IntegracorpLoginPanels::forMenu())->pluck('label')->all();

    expect($labels)->toBe(['AGENCIA MASTER', 'AGENCIA GENERAL', 'AGENTE'])
        ->and($labels)->not->toContain('NEGOCIOS')
        ->and($labels)->not->toContain('ADMIN');
});

it('cambia el placeholder del textarea segun el paso del feedback', function (): void {
    Volt::test('volt.public.ai_chat')
        ->call('selectServiceMenuOption', ServiceMenuOption::GUIA_CHAT_BUG)
        ->assertSee('Escribe tu nombre y apellido...')
        ->set('draft', 'Ana López')
        ->call('sendMessage')
        ->assertSee('Describe la falla del GUIA-CHAT...');
});

it('el menu quiero usa la misma ui optimizada que el menu de servicio', function (): void {
    $actionMenu = file_get_contents(base_path('resources/views/pwa/guia-chat-action-menu.blade.php'));
    $actionPanel = file_get_contents(base_path('resources/views/pwa/partials/guia-chat-action-menu-panel.blade.php'));

    expect($actionMenu)
        ->toContain('rounded-t-[1.35rem]')
        ->toContain('bg-[#071a3d]/95')
        ->toContain('backdrop-blur-2xl')
        ->toContain('guia-chat-action-menu-panel');

    expect($actionPanel)
        ->toContain('tracking-[0.14em] text-cyan-300/75')
        ->toContain('rounded-2xl border border-white/10')
        ->toContain('h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br');

    Volt::test('volt.public.ai_chat')
        ->assertSee('¿Qué quieres hacer?')
        ->assertSee('Elige una opción para comenzar el chat guiado');
});
