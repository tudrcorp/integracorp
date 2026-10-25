<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\TelemedicinePanelClientEventRequest;
use App\Support\Telemedicine\PanelLog\TelemedicinePanelClientEvent;
use App\Support\Telemedicine\PanelLog\TelemedicinePanelLog;
use Illuminate\Http\Response;

/**
 * Recibe los fallos que el navegador del médico detecta en el panel de
 * Telemedicina. Responde vacío; la escritura ocurre al terminar la petición.
 */
class TelemedicinePanelClientEventController extends Controller
{
    public function __invoke(TelemedicinePanelClientEventRequest $request): Response
    {
        $path = $request->pagePath();

        if (TelemedicinePanelLog::isTelemedicinePath($path)) {
            $description = TelemedicinePanelClientEvent::describe($request->validated());

            TelemedicinePanelLog::current()->record(
                TelemedicinePanelClientEvent::category(),
                $description['level'],
                (string) $request->validated('event'),
                $description['title'],
                array_filter($description['attributes'], static fn (mixed $value): bool => $value !== null && $value !== []) + ['url' => $path],
            );
        }

        return response()->noContent();
    }
}
