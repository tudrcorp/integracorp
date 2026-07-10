<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Support\AffiliateCard\AffiliateCardStampedPdfGenerator;
use App\Support\AffiliateCard\IndividualAffiliationPlanQrGenerator;
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
        $data['cobertura_display'] = match (true) {
            ! filled($coberturaVal) || $coberturaVal === '' => '',
            is_numeric($coberturaVal) => number_format((float) $coberturaVal, 2, ',', '.').' US$',
            default => (string) $coberturaVal,
        };

        $isIndividualAffiliation = ($data['card_layout'] ?? null) === 'individual-affiliation'
            || ($data['template_key'] ?? null) === 'individual-affiliation';

        if ($isIndividualAffiliation) {
            $resolvedPlanId = $planId ?? TarjetaAfiliacionQrPlanCatalog::findPlanByDescription($planDescription)?->id;
            $data['plan_qr_filename'] = null;
            $data['plan_qr_absolute_path'] = IndividualAffiliationPlanQrGenerator::qrAbsolutePathForPlanId(
                $resolvedPlanId !== null ? (int) $resolvedPlanId : null,
            );
        } else {
            $data['plan_qr_filename'] = TarjetaAfiliacionQrPlanCatalog::resolveQrFilename($planId, $planDescription);
            $data['plan_qr_absolute_path'] = self::resolveQrAbsolutePath($data['plan_qr_filename']);
        }

        if (($data['card_layout'] ?? null) === 'individual' || ($data['template_key'] ?? null) === 'individual') {
            $data['plan_qr_size_px'] = 80;
            $data['plan_qr_top_px'] = 425;
            $data['plan_qr_right_px'] = 135;
        } elseif (
            ($data['card_layout'] ?? null) === 'individual-affiliation'
            || ($data['template_key'] ?? null) === 'individual-affiliation'
        ) {
            $data['plan_qr_size_px'] = 55;
            $data['plan_qr_top_px'] = 46;
            $data['plan_qr_right_px'] = 23;
        } else {
            $data['plan_qr_size_px'] = 82;
            $data['plan_qr_top_px'] = 378;
            $data['plan_qr_right_px'] = 108;
        }

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

    public function associateCompanyAssociateInclusionQr(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_image' => ['required', 'image', 'mimes:png', 'max:2048'],
        ]);

        Storage::disk('public')->putFileAs(
            'tarjeta-afiliacion/planes',
            $request->file('qr_image'),
            'qr-plan-inclusion.png',
        );

        return response()->json([
            'ok' => true,
            'plan' => 'INCLUSIÓN',
            'affiliation_scope' => 'company_associate',
            'filename' => 'qr-plan-inclusion.png',
            'public_url' => asset('storage/tarjeta-afiliacion/planes/qr-plan-inclusion.png'),
        ]);
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

        $path = self::findQrPlanFilePath($fileName);

        if ($path !== null) {
            return $path;
        }

        if (app()->environment('local')) {
            return self::findQrPlanFilePath('qr-plan-inclusion.png');
        }

        return null;
    }

    private static function findQrPlanFilePath(string $fileName): ?string
    {
        $relativePath = 'tarjeta-afiliacion/planes/'.$fileName;

        foreach ([
            public_path('storage/'.$relativePath),
            storage_path('app/public/'.$relativePath),
        ] as $absolutePath) {
            if (is_file($absolutePath)) {
                return $absolutePath;
            }
        }

        return null;
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

            $name_pdf = isset($data['output_filename']) && is_string($data['output_filename']) && $data['output_filename'] !== ''
                ? $data['output_filename']
                : 'TAR-'.$data['code'].'.pdf';

            $directory = public_path('storage/tarjeta-afiliacion/');
            $fullPath = $directory.$name_pdf;

            if ($ensureOutputDirectory && ! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $cardLayout = $data['card_layout'] ?? null;
            $dataForView = $data;
            unset($dataForView['output_filename'], $dataForView['card_layout']);
            $preparedData = self::prepareDataForTarjetaPdfView(array_merge($dataForView, [
                'card_layout' => $cardLayout,
            ]));

            if (AffiliateCardStampedPdfGenerator::canGenerate($preparedData)) {
                AffiliateCardStampedPdfGenerator::generate($preparedData, $fullPath);

                if (! $silent) {
                    Log::info("Tarjeta de afiliación generada por estampado: {$name_pdf}");
                }

                return true;
            }

            if ($applyResourceLimits) {
                ini_set('memory_limit', '512M');
                set_time_limit(60);
            }

            $view = $cardLayout === 'individual'
                ? 'documents.tarjeta-afiliado-individual'
                : 'documents.tarjeta-afiliado';

            $pdf = Pdf::loadView($view, ['data' => $preparedData]);
            DomPdfBatchRenderOptions::apply($pdf);
            $pdf->save($fullPath);

            if (! $silent) {
                Log::info("Tarjeta de afiliación generada con DomPDF: {$name_pdf}");
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

    /**
     * Genera un PDF con varios carnets individual-affiliation (8 por hoja A4).
     *
     * @param  list<array<string, mixed>>  $cards
     * @return bool|string true si OK, string con mensaje de error si falla
     */
    public static function generateTarjetaAfiliacionBatch(
        array $cards,
        string $outputFilename,
        bool $silent = false,
        bool $ensureOutputDirectory = true,
    ): bool|string {
        try {
            if ($cards === []) {
                throw new \Exception('No hay carnets para generar.');
            }

            $directory = public_path('storage/tarjeta-afiliacion/');
            $fullPath = $directory.$outputFilename;

            if ($ensureOutputDirectory && ! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $preparedCards = [];

            foreach ($cards as $card) {
                $cardLayout = $card['card_layout'] ?? null;
                $dataForView = $card;
                unset($dataForView['output_filename'], $dataForView['card_layout'], $dataForView['affiliate_count'], $dataForView['sheet_slot']);
                $preparedCards[] = self::prepareDataForTarjetaPdfView(array_merge($dataForView, [
                    'card_layout' => $cardLayout,
                ]));
            }

            if (! AffiliateCardStampedPdfGenerator::canGenerateBatch($preparedCards)) {
                throw new \Exception('No hay plantilla de estampado para el lote de carnets.');
            }

            AffiliateCardStampedPdfGenerator::generateIndividualAffiliationBatch($preparedCards, $fullPath);

            if (! $silent) {
                Log::info("Lote de tarjetas de afiliación generado por estampado: {$outputFilename}");
            }

            return true;
        } catch (\Throwable $th) {
            Log::error('Fallo al generar lote de tarjetas de afiliación', [
                'error' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
            ]);

            return 'Error al generar el lote de tarjetas: '.$th->getMessage();
        }
    }
}
