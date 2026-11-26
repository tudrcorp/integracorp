<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\ClinicalRecord\ClinicalRecordPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga del PDF de la ficha clínica: enlace firmado y solo para el usuario que lo pidió.
 */
class ClinicalRecordPdfController extends Controller
{
    public function __invoke(Request $request, string $file): StreamedResponse
    {
        $owner = ClinicalRecordPdf::owner($file);

        abort_unless($owner !== null && (int) $request->user()?->getKey() === $owner['user_id'], 403);

        $path = ClinicalRecordPdf::DIRECTORY.'/'.$file;
        abort_unless(Storage::disk('local')->exists($path), 404, 'El PDF ya venció. Vuelve a generarlo desde la ficha.');

        $name = (string) TelemedicinePatient::query()->whereKey($owner['patient_id'])->value('full_name');

        $title = $owner['kind'] === 'tecnica' ? 'Ficha tecnica (uso interno) - ' : 'Ficha clinica - ';

        return Storage::disk('local')->download($path, $title.Str::of($name)->ascii()->title()->limit(60, '').'.pdf', [
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
