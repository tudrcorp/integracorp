<?php

declare(strict_types=1);

use App\Support\Errors\ErrorPage;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(Tests\TestCase::class);

/**
 * Páginas de error propias: en español, con salida (atrás / inicio del panel /
 * reintentar) y sin filtrar detalles internos. Solo renderiza: no toca la base.
 */
function renderErrorPage(Throwable $exception, string $uri, string $method = 'GET', array $server = []): Response
{
    $request = Request::create($uri, $method, server: $server);
    app()->instance('request', $request);

    return app(ExceptionHandler::class)->render($request, $exception);
}

it('el 403 muestra la vista propia en español con volver atrás y el inicio del panel', function (): void {
    $response = renderErrorPage(new AccessDeniedHttpException('This action is unauthorized.'), 'https://www.integracorp.test/operations/operation-service-orders/462');
    $html = $response->getContent();

    expect($response->getStatusCode())->toBe(403)
        ->and($html)->toContain('No tienes permiso para ver esto')
        ->toContain('Volver atrás')
        ->toContain('Ir al inicio de Operaciones')
        ->toContain('href="https://www.integracorp.test/operations"')
        ->toContain('/operations/operation-service-orders/462')
        ->toContain('Datos para soporte')
        ->not->toContain('This action is unauthorized')
        ->not->toContain('FORBIDDEN')
        ->not->toContain('Reintentar');
});

it('muestra el mensaje en español del abort pero nunca el de un error 5xx', function (): void {
    $notFound = renderErrorPage(new NotFoundHttpException('Documento no encontrado.'), 'https://www.integracorp.test/business/documento/9');
    $server = renderErrorPage(new HttpException(500, 'SQLSTATE[42S22]: Column not found: secreto'), 'https://www.integracorp.test/business');

    expect($notFound->getContent())->toContain('No encontramos lo que buscas')->toContain('Documento no encontrado.')->toContain('Ir al inicio de Negocios')
        ->and($server->getStatusCode())->toBe(500)
        ->and($server->getContent())->toContain('Algo salió mal de nuestro lado')->toContain('Reintentar')
        ->not->toContain('SQLSTATE')->not->toContain('secreto');
});

it('cubre los códigos sin vista del framework con 4xx y 5xx', function (int $status, string $title): void {
    expect(renderErrorPage(new HttpException($status), 'https://www.integracorp.test/admin')->getContent())->toContain($title);
})->with([
    'método no permitido' => [405, 'Esta acción no está disponible'],
    'solicitud inválida' => [400, 'No pudimos procesar la solicitud'],
    'gateway' => [502, 'Algo salió mal de nuestro lado'],
    'mantenimiento' => [503, 'Estamos en mantenimiento'],
    'sesión vencida' => [419, 'Tu sesión expiró'],
    'demasiados intentos' => [429, 'Demasiados intentos'],
]);

it('fuera de un panel lleva al inicio del sitio', function (): void {
    $home = ErrorPage::home(Request::create('https://www.integracorp.test/plk/abc'));

    expect($home)->toBe(['url' => url('/'), 'label' => 'Ir al inicio']);
});

it('volver atrás no sale del sitio ni repite la página que falló', function (): void {
    $fallback = 'https://www.integracorp.test/operations';

    $external = Request::create('https://www.integracorp.test/operations/x', server: ['HTTP_REFERER' => 'https://evil.example/phish']);
    app()->instance('request', $external);
    url()->setRequest($external);
    $externalBack = ErrorPage::backUrl($external, $fallback);

    $internal = Request::create('https://www.integracorp.test/operations/x', server: ['HTTP_REFERER' => 'https://www.integracorp.test/operations/operation-service-orders']);
    app()->instance('request', $internal);
    url()->setRequest($internal);
    $internalBack = ErrorPage::backUrl($internal, $fallback);

    expect($externalBack)->toBe($fallback)
        ->and($internalBack)->toBe('https://www.integracorp.test/operations/operation-service-orders');
});

it('reintentar recarga un GET pero no reenvía un formulario', function (): void {
    $get = Request::create('https://www.integracorp.test/business/x');
    $post = Request::create('https://www.integracorp.test/business/x', 'POST');

    expect(ErrorPage::retryUrl($get, 500, '/atras'))->toBe('https://www.integracorp.test/business/x')
        ->and(ErrorPage::retryUrl($post, 500, '/atras'))->toBe('/atras')
        ->and(ErrorPage::retryUrl($get, 419, '/atras'))->toBe('/atras');
});

it('un estado fuera de rango o una excepción común se tratan como 500', function (): void {
    expect(ErrorPage::status(null))->toBe(500)
        ->and(ErrorPage::status(new RuntimeException('x')))->toBe(500)
        ->and(ErrorPage::status(new HttpException(302)))->toBe(500)
        ->and(ErrorPage::detail(new HttpException(403, str_repeat('a', 400)), 403))->toBeNull();
});
