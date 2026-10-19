<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreErrorReportRequest;
use App\Models\User;
use App\Support\Errors\ErrorPage;
use App\Support\Errors\ErrorReportContext;
use App\Support\Errors\ErrorReportException;
use App\Support\Errors\ErrorReportTicket;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * «Reportar a Sistemas» de la página de error: abre (o recupera) el ticket y
 * responde JSON con el mensaje listo para mostrar.
 */
class ErrorReportController extends Controller
{
    public function __invoke(StoreErrorReportRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $context = ErrorReportContext::open((string) $request->validated('context'), $user);

        if ($context === null || ! ErrorPage::reportable((int) $context['status'])) {
            return response()->json([
                'ok' => false,
                'message' => 'No pudimos validar los datos del error (la página lleva mucho tiempo abierta o cambió de sesión). Recarga la página e inténtalo de nuevo.',
            ], 422);
        }

        try {
            $result = ErrorReportTicket::report($user, $context, $request->validated('note'));
        } catch (ErrorReportException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => 'No pudimos crear el ticket en este momento. Inténtalo de nuevo en unos minutos.',
            ], 500);
        }

        $ticket = $result['ticket'];

        return response()->json([
            'ok' => true,
            'created' => $result['created'],
            'id' => $ticket->getKey(),
            'uid' => $ticket->uid,
            'url' => ErrorReportTicket::ticketUrl($ticket, $context),
            'message' => $result['created']
                ? 'Creamos el ticket N.º '.$ticket->getKey().' y avisamos al equipo de Sistemas. Te responderán por el helpdesk.'
                : 'Este error ya está reportado en el ticket N.º '.$ticket->getKey().' y Sistemas lo está atendiendo.',
        ]);
    }
}
