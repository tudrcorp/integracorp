<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RrhhColaborador;
use App\Models\User;
use App\Support\TdgCalendar\TdgCalendarSchedulePdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class TdgCalendarSchedulePdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $kind = (string) $request->query('tipo', '');

        try {
            TdgCalendarSchedulePdf::assertKind($kind);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        abort_unless(TdgCalendarSchedulePdf::canDownload($user, $kind), 403);

        $monthText = (string) $request->query('mes', '');

        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthText) === 1, 404);

        $month = Carbon::createFromFormat('Y-m', $monthText);

        abort_unless($month instanceof Carbon, 404);

        $requested = $request->query('colaborador');
        $requestedId = is_numeric($requested) ? (int) $requested : null;

        if ($requestedId !== null && $requestedId < 1) {
            $requestedId = null;
        }

        $colaboradorId = TdgCalendarSchedulePdf::resolvedColaboradorId($user, $kind, $requestedId);

        if (! TdgCalendarSchedulePdf::canDownloadEveryone($user, $kind) && $colaboradorId === null) {
            abort(403, 'Tu usuario no está ligado a un colaborador, así que no hay un cronograma personal para descargar.');
        }

        if ($colaboradorId !== null && ! RrhhColaborador::query()->whereKey($colaboradorId)->exists()) {
            abort(404);
        }

        @set_time_limit(60);

        $document = TdgCalendarSchedulePdf::document($kind, $month, $colaboradorId);
        $binary = TdgCalendarSchedulePdf::binary($document);
        $filename = TdgCalendarSchedulePdf::filename($kind, $month, $document['person_name']);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
