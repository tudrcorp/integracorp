<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationServiceOrders\Pages\ViewOperationServiceOrder;
use App\Models\HelpDeskEvent;
use App\Models\User;
use App\Models\UserPermission;
use App\Support\Errors\ErrorReportContext;
use App\Support\Errors\ErrorReportDescription;
use App\Support\Errors\ErrorReportTicket;
use App\Support\HelpdeskBusinessScrumRoles;
use App\Support\HelpdeskPlainText;
use App\Support\HelpdeskTaskStatusOptions;
use App\Support\HelpdeskTicketType;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

/**
 * «Reportar a Sistemas» desde la página de error. Todo lo que escribe (ticket,
 * asignación, bitácora, caché de duplicados, auditoría) va dentro de una
 * transacción que siempre se revierte: la base de desarrollo no cambia.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Mail::fake();
    Queue::fake();
});

afterEach(function (): void {
    DB::rollBack();
});

/** Un usuario real sin el permiso de órdenes de servicio, como el del 403 reportado. */
function erUser(): User
{
    $withPermission = UserPermission::query()
        ->whereIn('permission_id', DB::table('permissions')->where('slug', 'ordenes-servicios')->pluck('id'))
        ->pluck('user_id');

    $user = User::query()->where('status', 'ACTIVO')->whereNotIn('id', $withPermission)->orderBy('id')->first();

    if ($user === null) {
        test()->markTestSkipped('No hay un usuario activo sin el permiso de órdenes de servicio.');
    }

    return $user;
}

/**
 * @return array<string, mixed>
 */
function erContext(User $user, int $status = 403, array $overrides = []): array
{
    return [
        'v' => 1,
        'uid' => (int) $user->getKey(),
        'at' => time(),
        'status' => $status,
        'method' => 'GET',
        'url' => '/operations/operation-service-orders/462',
        'host' => 'www.integracorp.test',
        'route' => 'filament.operations.resources.operation-service-orders.view',
        'action' => ViewOperationServiceOrder::class,
        'params' => ['record' => '462'],
        'panel' => 'operations',
        'referer' => '/operations/operation-service-orders',
        'ua' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
        'ip' => '10.0.0.8',
        'detail' => null,
        'exception' => null,
        ...$overrides,
    ];
}

it('el contexto sellado solo lo abre el mismo usuario, sin alterar y a tiempo', function (): void {
    $user = erUser();
    $other = User::query()->whereKeyNot($user->getKey())->first();
    $context = erContext($user);
    $sealed = ErrorReportContext::seal($context);
    $tampered = substr_replace($sealed, $sealed[40] === 'A' ? 'B' : 'A', 40, 1);

    expect(ErrorReportContext::open($sealed, $user))->toBe($context)
        ->and(ErrorReportContext::open($sealed, $other))->toBeNull()
        ->and(ErrorReportContext::open($tampered, $user))->toBeNull()
        ->and(ErrorReportContext::open(Crypt::encryptString('{"v":1}'), $user))->toBeNull()
        ->and(ErrorReportContext::open($sealed, $user, time() + ErrorReportContext::MAX_AGE_SECONDS + 5))->toBeNull()
        ->and(ErrorReportContext::open(ErrorReportContext::seal(erContext($user, 200)), $user))->toBeNull();
});

it('arma el contexto desde la petición sin valores de la query ni referer externo', function (): void {
    $user = erUser();
    $request = Request::create('https://www.integracorp.test/operations/x?token=secreto&page=2', server: [
        'HTTP_REFERER' => 'https://evil.example/phish',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 Firefox/131.0',
    ]);

    $failure = new HttpException(500, 'boom', new RuntimeException('SQLSTATE: password=abc123 fallo'));
    $context = ErrorReportContext::build($request, $failure, 500, $user);

    expect($context['url'])->toBe('/operations/x?token=…&page=…')
        ->and($context['referer'])->toBeNull()
        ->and($context['panel'])->toBe('operations')
        ->and($context['uid'])->toBe((int) $user->getKey())
        ->and($context['exception']['class'])->toBe(RuntimeException::class)
        ->and($context['exception']['message'])->not->toContain('abc123')
        ->and($context['exception']['fingerprint'])->toHaveLength(16)
        ->and(ErrorReportContext::build($request, new AccessDeniedHttpException, 403, $user)['exception'])->toBeNull();
});

it('la descripción explica el 403: qué permiso falta, quién, dónde y escapa lo que escribe el usuario', function (): void {
    $html = ErrorReportDescription::html(erContext(erUser()), [
        'name' => 'María José Castillo', 'email' => 'mcastillo@tudrencasa.com', 'id' => 514, 'departments' => ['NEGOCIOS', 'MARKETING'],
    ], "Quise abrir la orden\n<script>alert(1)</script>", [
        'screen' => 'Órdenes de servicio · ver registro',
        'record' => '#462',
        'permission' => ['names' => ['Ordenes de Servicios'], 'slugs' => ['ordenes-servicios'], 'module' => 'OPERACIONES', 'has' => false, 'super_admin_only' => false],
    ]);

    expect($html)->toContain('María José Castillo recibió el <strong>error 403 (Sin permiso)</strong> en <strong>Órdenes de servicio · ver registro</strong> (registro #462)')
        ->toContain('«No tienes permiso para ver esto»')
        ->toContain('panel Operaciones')
        ->toContain('«Ordenes de Servicios»')
        ->toContain('slug: ordenes-servicios · módulo: OPERACIONES')
        ->toContain('el usuario no lo tiene asignado')
        ->toContain('NEGOCIOS, MARKETING')
        ->toContain('Chrome 141 en macOS')
        ->toContain('Quise abrir la orden<br')
        ->toContain('&lt;script&gt;')
        ->not->toContain('<script>');
});

it('la descripción de un 500 trae la falla de código, la huella y la traza', function (): void {
    $html = ErrorReportDescription::html(erContext(erUser(), 500, [
        'exception' => [
            'class' => 'Illuminate\\Database\\QueryException',
            'message' => 'Column not found: ?',
            'location' => 'vendor/laravel/framework/src/Illuminate/Database/Connection.php:825',
            'origin' => 'app/Support/Operations/Algo.php:40',
            'frames' => ['app/Support/Operations/Algo.php:40  Algo::run'],
            'fingerprint' => 'abcdef0123456789',
        ],
    ]), ['name' => 'Ana', 'email' => 'ana@tudrencasa.com', 'id' => 1, 'departments' => []], null, ['screen' => null, 'record' => null, 'permission' => null]);

    expect($html)->toContain('Falla de código: <strong>Illuminate\\Database\\QueryException</strong>')
        ->toContain('app/Support/Operations/Algo.php:40')
        ->toContain('abcdef0123456789')
        ->toContain('Colas y errores')
        ->toContain('<pre>')
        ->toContain('No agregó comentarios')
        ->toContain('Sin departamento');
});

it('crea el incidente asignado a los desarrolladores con la pantalla y el permiso que falta', function (): void {
    $user = erUser();
    $result = ErrorReportTicket::report($user, erContext($user), 'Quise abrir la orden 462');
    $ticket = $result['ticket']->fresh(['rrhhColaboradores']);

    expect($result['created'])->toBeTrue()
        ->and($ticket->ticket_type)->toBe(HelpdeskTicketType::INCIDENT)
        ->and($ticket->priority)->toBe('MEDIA')
        ->and($ticket->status)->toBe(HelpdeskTaskStatusOptions::STATUS_PENDING)
        ->and($ticket->created_by_user_id)->toBe($user->getKey())
        ->and($ticket->uid)->toStartWith('TK-')
        ->and($ticket->first_response_due_at)->not->toBeNull()
        ->and($ticket->rrhhColaboradores->modelKeys())->toEqualCanonicalizing(HelpdeskBusinessScrumRoles::developerIds())
        ->and($ticket->description)->toContain('Ordenes de Servicios · ver registro')->toContain('(registro #462)')->toContain('«Ordenes de Servicios»')->toContain('el usuario no lo tiene asignado')->toContain('Quise abrir la orden 462')
        ->and(HelpDeskEvent::query()->where('help_desk_id', $ticket->getKey())->value('meta'))->toMatchArray(['source' => 'error_page', 'status' => 403]);
});

it('no duplica: el mismo error devuelve el ticket abierto; cerrado, abre uno nuevo', function (): void {
    $user = erUser();
    $first = ErrorReportTicket::report($user, erContext($user), null);
    $again = ErrorReportTicket::report($user, erContext($user), 'otra vez');

    expect($again['created'])->toBeFalse()
        ->and($again['ticket']->getKey())->toBe($first['ticket']->getKey());

    $first['ticket']->update(['status' => HelpdeskTaskStatusOptions::STATUS_DONE]);
    $after = ErrorReportTicket::report($user, erContext($user), null);

    $otherRecord = ErrorReportTicket::report($user, erContext($user, 403, ['params' => ['record' => '463']]), null);

    expect($after['created'])->toBeTrue()
        ->and($after['ticket']->getKey())->not->toBe($first['ticket']->getKey())
        ->and($otherRecord['created'])->toBeTrue();
});

it('un 500 entra con prioridad ALTA', function (): void {
    $user = erUser();
    $result = ErrorReportTicket::report($user, erContext($user, 500, ['action' => null, 'params' => []]), null);

    expect($result['ticket']->priority)->toBe('ALTA');
});

it('el endpoint crea el ticket, rechaza datos alterados, notas largas e invitados', function (): void {
    $user = erUser();
    $sealed = ErrorReportContext::seal(erContext($user));
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $this->postJson(route('error-report.store'), ['context' => $sealed])->assertUnauthorized();

    $this->actingAs($user);

    $created = $this->postJson(route('error-report.store'), ['context' => $sealed, 'note' => 'Desde el listado'])
        ->assertOk()
        ->assertJson(['ok' => true, 'created' => true]);

    expect($created->json('message'))->toContain('Creamos el ticket N.º '.$created->json('id'));

    $this->postJson(route('error-report.store'), ['context' => $sealed])
        ->assertOk()
        ->assertJson(['ok' => true, 'created' => false, 'id' => $created->json('id')]);

    $this->postJson(route('error-report.store'), ['context' => substr_replace($sealed, $sealed[40] === 'A' ? 'B' : 'A', 40, 1)])
        ->assertUnprocessable()
        ->assertJson(['ok' => false]);

    $this->postJson(route('error-report.store'), ['context' => $sealed, 'note' => str_repeat('a', 1001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['note' => 'hasta 1000 caracteres']);

    $this->postJson(route('error-report.store'), ['context' => ErrorReportContext::seal(erContext($user, 419))])
        ->assertUnprocessable();
});

it('la página de error ofrece el reporte a quien tiene sesión y no en errores que no son fallas', function (): void {
    $user = erUser();
    $render = function (Throwable $exception): string {
        $request = Request::create('https://www.integracorp.test/operations/operation-service-orders/462');
        $session = app('session')->driver();
        $session->start();
        $request->setLaravelSession($session);
        app()->instance('request', $request);

        return app(ExceptionHandler::class)->render($request, $exception)->getContent();
    };

    $guest = $render(new AccessDeniedHttpException);

    $this->actingAs($user);
    $logged = $render(new AccessDeniedHttpException);
    $maintenance = $render(new HttpException(503));

    expect($guest)->not->toContain('Reportar a Sistemas')
        ->and($logged)->toContain('Reportar a Sistemas')->toContain('data-context="')->toContain(route('error-report.store'))
        ->and($maintenance)->not->toContain('Reportar a Sistemas');
});

it('correo y WhatsApp leen la descripción HTML como texto, sin tocar las de texto plano', function (): void {
    $html = '<p><strong>Reporte automático.</strong></p><p>Ana recibió el error 403.</p><h3>Quién</h3><ul><li><strong>Usuario:</strong> Ana</li></ul>';

    expect(HelpdeskPlainText::readable($html))->toBe("Reporte automático.\n\nAna recibió el error 403.\n\nQuién\n\n• Usuario: Ana")
        ->and(HelpdeskPlainText::inline($html))->toBe('Reporte automático. · Ana recibió el error 403. · Quién · Usuario: Ana')
        ->and(HelpdeskPlainText::readable("Línea 1\nsi a < b"))->toBe("Línea 1\nsi a < b")
        ->and(HelpdeskPlainText::inline("Texto & más\nlíneas"))->toBe("Texto & más\nlíneas");
});
