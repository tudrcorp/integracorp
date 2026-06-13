<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Support\DomPdfBatchRenderOptions;
use App\Support\TarjetaAfiliacionQrPlanCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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

        $planId = isset($data['plan_id']) ? (int) $data['plan_id'] : null;
        $planDescription = (string) ($data['plan'] ?? '');

        $data['plan_tarjeta_etiqueta'] = TarjetaAfiliacionQrPlanCatalog::displayTagForPlan($planId, $planDescription);
        $coberturaVal = $data['cobertura'] ?? null;
        $data['cobertura_display'] = (filled($coberturaVal) && $coberturaVal !== '')
            ? number_format((float) $coberturaVal, 2, ',', '.').' US$'
            : '';
        $data['plan_qr_filename'] = TarjetaAfiliacionQrPlanCatalog::resolveQrFilename($planId, $planDescription);
        $data['plan_qr_absolute_path'] = self::resolveQrAbsolutePath($data['plan_qr_filename']);
        $data['plan_qr_size_px'] = 73;
        $data['plan_qr_top_px'] = 450;
        $data['plan_qr_right_px'] = 210;

        return $data;
    }

    public function associatePlanQr(Request $request): JsonResponse
    {
        return $this->storePlanQr($request, 'individual');
    }

    public function associateCorporatePlanQr(Request $request): JsonResponse
    {
        return $this->storePlanQr($request, 'corporate');
    }

    private function storePlanQr(Request $request, string $affiliationScope): JsonResponse
    {
        $allowedPlanIds = $affiliationScope === 'corporate'
            ? TarjetaAfiliacionQrPlanCatalog::allowedCorporatePlanIds()
            : TarjetaAfiliacionQrPlanCatalog::allowedIndividualPlanIds();

        $validated = $request->validate([
            'plan_id' => ['required', 'integer', Rule::in($allowedPlanIds)],
            'qr_image' => ['required', 'image', 'mimes:png', 'max:2048'],
        ]);

        $planId = (int) $validated['plan_id'];
        $filename = TarjetaAfiliacionQrPlanCatalog::qrFilenameForPlanId($planId);
        Storage::disk('public')->putFileAs(
            'tarjeta-afiliacion/planes',
            $request->file('qr_image'),
            $filename
        );

        $plan = Plan::query()->find($planId);

        return response()->json([
            'ok' => true,
            'plan_id' => $planId,
            'plan' => $plan?->description,
            'affiliation_scope' => $affiliationScope,
            'filename' => $filename,
            'public_url' => asset('storage/tarjeta-afiliacion/planes/'.$filename),
        ]);
    }

    private static function resolveQrAbsolutePath(?string $fileName): ?string
    {
        if (! is_string($fileName) || $fileName === '') {
            return null;
        }

        $absolutePath = public_path('storage/tarjeta-afiliacion/planes/'.$fileName);

        return is_file($absolutePath) ? $absolutePath : null;
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
