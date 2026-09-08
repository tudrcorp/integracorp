<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Única vía de render de los informes médicos (corto, largo y de seguimiento).
 *
 * Antes cada informe se generaba por su lado —el corto desde su job, el largo
 * desde su propio generador—, y esa asimetría ya costó un fallo. Aquí comparten
 * el mismo paso final, incluido el estampado de la firma
 * (ver TelemedicineInformeSignatureStamp).
 */
final class TelemedicineInformePdfRenderer
{
    public const VIEW_CORTO = 'documents.informe-medico-corto';

    public const VIEW_LARGO = 'documents.informe-medico-largo';

    public const VIEW_SEGUIMIENTO = 'documents.informe-seguimiento';

    /**
     * @param  array<string, mixed>  $data
     */
    public static function render(string $view, array $data): string
    {
        ini_set('memory_limit', '2048M');

        $pdf = Pdf::loadView($view, ['data' => $data])->setPaper('a4', 'portrait');

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        // El script se registra tras el layout y se ejecuta al volcar el PDF:
        // para entonces ya se sabe cuál es la última página.
        $stampPath = TelemedicineInformeSignatureStamp::applyTo($dompdf, $data);

        try {
            return (string) $dompdf->output();
        } finally {
            // Se borra la ruta de ESTE render: el sello ya no se comparte entre
            // documentos, así que ninguno puede quedarse sin firma por la limpieza
            // de otro que iba en paralelo.
            TelemedicineInformeSignatureStamp::cleanUp($stampPath);
        }
    }

    /**
     * Renderiza y guarda en `public/storage/<relativePath>`.
     *
     * @param  array<string, mixed>  $data
     */
    public static function save(string $view, array $data, string $relativePath): string
    {
        $absolute = public_path('storage/'.$relativePath);
        $directory = dirname($absolute);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($absolute, self::render($view, $data));

        return $absolute;
    }
}
