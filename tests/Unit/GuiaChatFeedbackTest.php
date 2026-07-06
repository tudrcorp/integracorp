<?php

declare(strict_types=1);

use App\Filament\Business\Resources\GuiaChatFeedbacks\Pages\ViewGuiaChatFeedback;
use App\Jobs\SendGuiaChatFeedbackMailJob;
use App\Jobs\SendNotificacionWhatsApp;
use App\Models\ChatSession;
use App\Models\GuiaChatFeedback;
use App\Models\User;
use App\Support\GuiaChat\GuiaChatFeedbackRecorder;
use App\Support\GuiaChat\GuiaChatFeedbackType;
use App\Support\GuiaChat\ServiceMenuOption;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Livewire\Volt\Volt;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Schema::dropIfExists('guia_chat_feedbacks');
    Schema::dropIfExists('chat_messages');
    Schema::dropIfExists('chat_sessions');

    Schema::create('chat_sessions', function (Blueprint $table): void {
        $table->id();
        $table->string('public_token', 80)->unique();
        $table->string('status')->default('active');
        $table->string('current_state')->default('saludo');
        $table->string('detected_intent')->nullable();
        $table->boolean('handoff_requested')->default(false);
        $table->text('handoff_reason')->nullable();
        $table->text('context_summary')->nullable();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamp('last_message_at')->nullable();
        $table->timestamps();
    });

    Schema::create('chat_messages', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('chat_session_id');
        $table->string('role', 20);
        $table->longText('content')->nullable();
        $table->json('metadata')->nullable();
        $table->timestamps();
    });

    Schema::create('guia_chat_feedbacks', function (Blueprint $table): void {
        $table->id();
        $table->string('type', 40);
        $table->text('message');
        $table->string('reporter_first_name')->nullable();
        $table->string('reporter_last_name')->nullable();
        $table->foreignId('chat_session_id')->nullable();
        $table->string('public_token', 80)->nullable();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->timestamps();
    });
});

it('guarda sugerencias sin nombre del reportante', function (): void {
    $session = ChatSession::startPublic('127.0.0.1', 'pest');

    $feedback = app(GuiaChatFeedbackRecorder::class)->record(
        type: GuiaChatFeedbackType::ServiceSuggestion->value,
        message: 'Me gustaría ver más planes.',
        session: $session,
        reporterFirstName: 'Juan',
        reporterLastName: 'Pérez',
    );

    expect($feedback->reporter_first_name)->toBeNull()
        ->and($feedback->reporter_last_name)->toBeNull()
        ->and($feedback->type)->toBe(GuiaChatFeedbackType::ServiceSuggestion->value);
});

it('guarda reportes de fallas con nombre y apellido', function (): void {
    $session = ChatSession::startPublic('127.0.0.1', 'pest');

    $feedback = app(GuiaChatFeedbackRecorder::class)->record(
        type: GuiaChatFeedbackType::GuiaChatBug->value,
        message: 'El botón enviar no responde.',
        session: $session,
        reporterFirstName: 'María',
        reporterLastName: 'Gómez',
    );

    expect($feedback->reporterFullName())->toBe('María Gómez')
        ->and(GuiaChatFeedback::query()->count())->toBe(1);
});

it('solicita nombre y apellido en un solo mensaje antes del reporte de falla del guia-chat', function (): void {
    Volt::test('volt.public.ai_chat')
        ->call('selectServiceMenuOption', ServiceMenuOption::GUIA_CHAT_BUG)
        ->assertSet('serviceFeedbackStep', ServiceMenuOption::FEEDBACK_STEP_REPORTER_NAME)
        ->set('draft', 'Carlos Ruiz')
        ->call('sendMessage')
        ->assertSet('serviceFeedbackReporterFirstName', 'Carlos')
        ->assertSet('serviceFeedbackReporterLastName', 'Ruiz')
        ->assertSet('serviceFeedbackStep', ServiceMenuOption::FEEDBACK_STEP_MESSAGE)
        ->set('draft', 'No carga el menú de acciones.')
        ->call('sendMessage')
        ->assertSet('serviceFeedbackMode', null);

    $feedback = GuiaChatFeedback::query()->first();

    expect($feedback)->not->toBeNull()
        ->and($feedback->reporterFullName())->toBe('Carlos Ruiz')
        ->and($feedback->message)->toBe('No carga el menú de acciones.');
});

it('expone recurso guia-chat en negocios configuracion', function (): void {
    $resource = file_get_contents(base_path('app/Filament/Business/Resources/GuiaChatFeedbacks/GuiaChatFeedbackResource.php'));

    expect($resource)
        ->toContain("navigationLabel = 'Guia-Chat'")
        ->toContain("navigationGroup = 'CONFIGURACIÓN'")
        ->toContain("slug = 'guia-chat-feedbacks'")
        ->toContain('GuiaChatFeedbacksTable::configure');
});

it('la tabla de feedbacks prioriza lectura rapida y navegacion al detalle', function (): void {
    $table = file_get_contents(base_path('app/Filament/Business/Resources/GuiaChatFeedbacks/Tables/GuiaChatFeedbacksTable.php'));

    expect($table)
        ->toContain('->striped()')
        ->toContain('->recordUrl(')
        ->toContain('->searchPlaceholder(')
        ->toContain('->emptyStateHeading(')
        ->toContain('countByType');
});

it('el infolist de feedbacks usa pestañas y estilos del sistema', function (): void {
    $infolist = file_get_contents(base_path('app/Filament/Business/Resources/GuiaChatFeedbacks/Schemas/GuiaChatFeedbackInfolist.php'));

    expect($infolist)
        ->toContain('Tabs::make(')
        ->toContain('persistTab()')
        ->toContain('TABS_CONTAINER')
        ->toContain('IOS_SECTION_CLASS')
        ->toContain('Tab::make(\'Resumen\')')
        ->toContain('Tab::make(\'Contenido\')')
        ->toContain('Tab::make(\'Trazabilidad\')');
});

it('carga la pagina de detalle en filament business', function (): void {
    $user = User::factory()->create([
        'email' => 'guia-chat-view-'.uniqid('', true).'@example.com',
        'departament' => ['NEGOCIOS'],
        'status' => 'ACTIVO',
    ]);

    $session = ChatSession::startPublic('127.0.0.1', 'pest');

    $feedback = app(GuiaChatFeedbackRecorder::class)->record(
        type: GuiaChatFeedbackType::GuiaChatBug->value,
        message: 'Detalle de prueba en panel business',
        session: $session,
        reporterFirstName: 'Ana',
        reporterLastName: 'Lopez',
    );

    Filament::setCurrentPanel('business');

    Livewire::actingAs($user)
        ->test(ViewGuiaChatFeedback::class, ['record' => $feedback->getKey()])
        ->assertSuccessful()
        ->assertSee('Detalle de prueba en panel business');
});

it('dispara correo y whatsapp de forma asincrona al registrar feedback', function (): void {
    Queue::fake();

    $session = ChatSession::startPublic('127.0.0.1', 'pest');

    app(GuiaChatFeedbackRecorder::class)->record(
        type: GuiaChatFeedbackType::IntegracorpBug->value,
        message: 'No puedo iniciar sesión en operaciones.',
        session: $session,
        reporterFirstName: 'Ana',
        reporterLastName: 'López',
    );

    Queue::assertPushed(SendGuiaChatFeedbackMailJob::class);
    Queue::assertPushed(SendNotificacionWhatsApp::class, 3);
});

it('el notificador envía correo y whatsapp a los destinatarios indicados', function (): void {
    $src = file_get_contents(base_path('app/Support/GuiaChat/GuiaChatFeedbackNotifier.php'));

    expect($src)
        ->toContain('SendGuiaChatFeedbackMailJob::dispatch')
        ->toContain('SendNotificacionWhatsApp::dispatch')
        ->toContain("'soporte@tudrencasa.com'")
        ->toContain("'solrodriguez@tudrencasa.com'")
        ->toContain("'04127018390'")
        ->toContain("'04121931865'")
        ->toContain("'04143027250'")
        ->toContain('normalizePhoneForWhatsApp');
});

it('el job de correo usa destinatarios correctos y plantilla guia-chat', function (): void {
    $src = file_get_contents(base_path('app/Jobs/SendGuiaChatFeedbackMailJob.php'));

    expect($src)
        ->toContain('implements ShouldQueue')
        ->toContain('GuiaChatFeedbackNotifier::emailTo()')
        ->toContain('GuiaChatFeedbackNotifier::emailCc()')
        ->toContain('GuiaChatFeedbackMail');
});

it('el mailable usa la vista guia-chat-feedback', function (): void {
    $src = file_get_contents(base_path('app/Mail/GuiaChatFeedbackMail.php'));

    expect($src)
        ->toContain("view: 'mails.guia-chat-feedback'")
        ->toContain('presentationForType');
});

it('existe la vista del correo de feedback guia-chat', function (): void {
    expect(file_exists(base_path('resources/views/mails/guia-chat-feedback.blade.php')))->toBeTrue();
});
