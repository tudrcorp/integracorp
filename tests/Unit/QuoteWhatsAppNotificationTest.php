<?php

declare(strict_types=1);

use App\Enums\QuoteWhatsAppNotification;
use App\Jobs\SendQuoteWhatsAppNotificationJob;
use App\Models\User;
use App\Support\Quotes\QuoteWhatsAppDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(Tests\TestCase::class);

beforeEach(fn () => bootQuoteWhatsAppSqliteSchema());

function usuarioDePruebaWhatsApp(): User
{
    $id = DB::table('users')->insertGetId([
        'name' => 'Analista de negocios',
        'email' => 'analista-'.uniqid().'@tudrencasa.com',
        'password' => bcrypt('secret-de-prueba'),
        'status' => 'ACTIVO',
        'departament' => json_encode(['NEGOCIOS']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return User::query()->findOrFail($id);
}

function rutaDeLosPuntosDeEnvio(string $path): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/'.ltrim($path, '/'));
}

it('el aviso sale por cola y nunca desde la pantalla', function (): void {
    $fuentes = [
        'app/Filament/Business/Resources/IndividualQuotes/Pages/CreateIndividualQuote.php',
        'app/Filament/Business/Resources/CorporateQuotes/Pages/CreateCorporateQuote.php',
        'app/Filament/Business/Resources/CorporateQuotes/Tables/CorporateQuotesTable.php',
    ];

    foreach ($fuentes as $fuente) {
        $codigo = rutaDeLosPuntosDeEnvio($fuente);

        expect($codigo)
            ->not->toContain('NotificationController::')
            ->toContain('QuoteWhatsAppDispatcher::queue(');
    }
});

it('el correo del link interactivo tampoco espera al SMTP', function (): void {
    $tabla = rutaDeLosPuntosDeEnvio('app/Filament/Business/Resources/CorporateQuotes/Tables/CorporateQuotesTable.php');

    expect($tabla)
        ->toContain('->queue(new MailLinkIndividualQuote($link))')
        ->not->toContain('->send(new MailLinkIndividualQuote($link))');
});

it('encola con el usuario que hizo la acción y espera al commit', function (): void {
    Queue::fake();

    $user = usuarioDePruebaWhatsApp();
    Auth::login($user);

    QuoteWhatsAppDispatcher::queue(
        QuoteWhatsAppNotification::IndividualQuoteCreated,
        ['code' => 'COT-IND-0004011', 'agent' => 'Analista de negocios'],
        'https://www.integracorp.test/business/individual-quotes/1',
    );

    Queue::assertPushed(SendQuoteWhatsAppNotificationJob::class, function (SendQuoteWhatsAppNotificationJob $job) use ($user): bool {
        expect($job->notification)->toBe(QuoteWhatsAppNotification::IndividualQuoteCreated)
            ->and($job->payload['code'])->toBe('COT-IND-0004011')
            ->and($job->payload['url'])->toContain('/business/individual-quotes/')
            ->and($job->notifiableUserId)->toBe((int) $user->id)
            /** Los paneles corren en transacción: el job no puede salir antes del commit. */
            ->and($job->afterCommit)->toBeTrue();

        return true;
    });
});

it('es un job en cola con reintentos escalonados', function (): void {
    $job = new SendQuoteWhatsAppNotificationJob(
        QuoteWhatsAppNotification::CorporateQuoteCreated,
        ['code' => 'COT-CORP-0000123', 'agent' => 'Analista'],
    );

    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([15, 60, 180])
        ->and($job->timeout)->toBe(240)
        ->and($job->failOnTimeout)->toBeTrue();
});

it('no envía dos veces lo mismo mientras haya un envío en curso', function (): void {
    $notificacion = QuoteWhatsAppNotification::IndividualQuoteCreated;
    $payload = ['code' => 'COT-IND-0004011', 'agent' => 'Analista'];

    /** Otro proceso tiene el candado: el job se retira sin tocar UltraMsg. */
    $candado = Cache::lock($notificacion->lockKey($payload), 60);
    expect($candado->get())->toBeTrue();

    $job = new SendQuoteWhatsAppNotificationJob($notificacion, $payload);

    $job->handle();

    expect(true)->toBeTrue();

    $candado->release();
});

it('distingue el candado por cotización, teléfono y observación', function (): void {
    $creada = QuoteWhatsAppNotification::IndividualQuoteCreated;
    $observacion = QuoteWhatsAppNotification::CorporateObservationAdded;

    expect($creada->lockKey(['code' => 'A']))->not->toBe($creada->lockKey(['code' => 'B']))
        ->and($creada->lockKey(['code' => 'A']))->not->toBe($observacion->lockKey(['code' => 'A']))
        ->and($observacion->lockKey(['code' => 'A', 'observation' => 'uno']))
        ->not->toBe($observacion->lockKey(['code' => 'A', 'observation' => 'dos']))
        ->and($creada->lockKey(['code' => 'A']))->toBe($creada->lockKey(['code' => 'A']));
});

it('avisa en el panel cuando el mensaje no se pudo entregar', function (): void {
    $user = usuarioDePruebaWhatsApp();

    $job = new SendQuoteWhatsAppNotificationJob(
        QuoteWhatsAppNotification::IndividualQuoteCreated,
        ['code' => 'COT-IND-0004011', 'agent' => 'Analista'],
        (int) $user->id,
    );

    $job->failed(new RuntimeException('UltraMsg no responde'));

    $notificacion = DB::table('notifications')->where('notifiable_id', $user->id)->first();

    expect($notificacion)->not->toBeNull();

    $data = json_decode((string) $notificacion->data, true);

    expect($data['title'])->toBe('No se pudo enviar el WhatsApp')
        ->and($data['body'])->toContain('COT-IND-0004011')
        ->and($data['body'])->toContain('se guardó correctamente')
        ->and($data['body'])->toContain('Avise por otra vía')
        ->and($data['status'] ?? null)->toBe('danger');
});

it('explica en español qué se envió y a quién', function (): void {
    $individual = QuoteWhatsAppNotification::IndividualQuoteCreated;
    $link = QuoteWhatsAppNotification::InteractiveLinkSent;

    expect($individual->label())->toBe('Aviso de cotización individual')
        ->and($individual->audience())->toBe('al equipo de análisis')
        ->and($individual->successBody(['code' => 'COT-IND-0004011']))
        ->toContain('El equipo de análisis recibió el aviso de la cotización COT-IND-0004011')
        ->and($link->audience())->toBe('al cliente');
});

it('no muestra el teléfono completo del cliente en el panel', function (): void {
    $link = QuoteWhatsAppNotification::InteractiveLinkSent;

    $cuerpo = $link->successBody(['code' => 'COT-CORP-0000123', 'phone' => '04141234567']);

    expect($cuerpo)->toContain('4567')
        ->and($cuerpo)->not->toContain('04141234567')
        ->and($cuerpo)->toContain('•');
});
