<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MedicalGuard;
use App\Models\User;
use App\Support\Operations\MedicalGuards\MedicalGuardAccess;
use App\Support\Operations\MedicalGuards\MedicalGuardScheduler;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MedicalGuardSchedulePdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && MedicalGuardAccess::canView($user), 403);

        $monthText = (string) $request->query('mes', '');

        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthText) === 1, 404);

        $month = CarbonImmutable::createFromFormat('!Y-m', $monthText);
        $sites = MedicalGuardScheduler::between($month, $month->endOfMonth()->startOfDay())
            ->groupBy(static fn (MedicalGuard $guard): string => (string) $guard->site?->name)
            ->sortKeys();

        return Pdf::loadView('documents.medical-guards-schedule', [
            'monthLabel' => ucfirst($month->locale('es')->translatedFormat('F Y')),
            'sites' => $sites,
        ])->download('guardias-medicas-'.$monthText.'.pdf');
    }
}
