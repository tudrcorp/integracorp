<?php

namespace App\Http\Controllers;

use App\Support\DomPdfBatchRenderOptions;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class TarjetaAfiliacionController extends Controller
{
    /**
     * Campos derivados para la vista PDF (evita lógica y resoluciones en Blade).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepareDataForTarjetaPdfView(array $data): array
    {
        $split = UtilsController::splitName(isset($data['name']) ? (string) $data['name'] : null);
        $data['name_first_part'] = $split['first_part'];
        $data['name_second_part'] = $split['second_part'];
        $data['plan_tarjeta_etiqueta'] = match ((string) ($data['plan'] ?? '')) {
            'PLAN INICIAL' => 'INICIAL',
            'PLAN IDEAL' => 'IDEAL',
            'PLAN ESPECIAL' => 'ESPECIAL',
            default => '',
        };
        $coberturaVal = $data['cobertura'] ?? null;
        $data['cobertura_display'] = (filled($coberturaVal) && $coberturaVal !== '')
            ? number_format((float) $coberturaVal, 2, ',', '.').' US$'
            : '';

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return bool|string true si OK, string con mensaje de error si falla
     */
    public static function generateTarjetaAfiliacion(
        array $data,
        bool $silent = false,
        bool $ensureOutputDirectory = true,
        bool $applyResourceLimits = true,
    ): bool|string {
        try {

            if (empty($data['code'])) {
                throw new \Exception('No se proporcionó un código válido para generar la tarjeta.');
            }

            if ($applyResourceLimits) {
                ini_set('memory_limit', '512M');
                set_time_limit(60);
            }

            $name_pdf = isset($data['output_filename']) && is_string($data['output_filename']) && $data['output_filename'] !== ''
                ? $data['output_filename']
                : 'TAR-'.$data['code'].'.pdf';

            $directory = public_path('storage/tarjeta-afiliacion/');
            $fullPath = $directory.$name_pdf;

            if ($ensureOutputDirectory && ! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $dataForView = $data;
            unset($dataForView['output_filename']);
            $data = self::prepareDataForTarjetaPdfView($dataForView);

            $pdf = Pdf::loadView('documents.tarjeta-afiliado', compact('data'));
            DomPdfBatchRenderOptions::apply($pdf);
            $pdf->save($fullPath);

            if (! $silent) {
                Log::info("Tarjeta de afiliación generada con éxito: {$name_pdf}");
            }

            return true;

        } catch (\Throwable $th) {
            Log::error('Fallo al generar tarjeta de afiliación', [
                'error' => $th->getMessage(),
                'code' => $data['code'] ?? 'N/A',
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);

            if (! $silent) {
                Notification::make()
                    ->title('Error en proceso')
                    ->body('No se pudo generar la tarjeta de afiliación. Se ha registrado el incidente técnico.')
                    ->danger()
                    ->send();
            }

            return 'Error al generar la tarjeta de afiliación: '.$th->getMessage();
        }
    }
}
