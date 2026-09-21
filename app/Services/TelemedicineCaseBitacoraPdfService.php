<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TelemedicineCase;
use App\Support\Operations\TelemedicineCaseBitacora;
use Barryvdh\DomPDF\Facade\Pdf;

final class TelemedicineCaseBitacoraPdfService
{
    public static function relativePath(TelemedicineCase $case): string
    {
        return 'telemedicina-doc/bitacoras/'.TelemedicineCaseBitacora::documentName($case);
    }

    public static function ensure(TelemedicineCase $case): string
    {
        $relativePath = self::relativePath($case);
        $absolute = public_path('storage/'.$relativePath);
        $directory = dirname($absolute);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($absolute, self::render($case));

        return $relativePath;
    }

    public static function render(TelemedicineCase $case): string
    {
        ini_set('memory_limit', '1024M');

        $pdf = Pdf::loadView('documents.bitacora-caso', [
            'dossier' => TelemedicineCaseBitacora::dossier($case),
        ])->setPaper('a4', 'portrait');

        return $pdf->output();
    }
}
