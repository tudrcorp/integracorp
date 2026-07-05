<?php

declare(strict_types=1);

namespace App\Support\AffiliateCard;

use App\Http\Controllers\UtilsController;
use RuntimeException;
use setasign\Fpdi\Fpdi;

final class AffiliateCardStampedPdfGenerator
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function generate(array $data, string $outputPath): void
    {
        if (! (bool) config('affiliate-card.stamped_generation_enabled', true)) {
            throw new RuntimeException('La generación por estampado está deshabilitada.');
        }

        $templateKey = self::resolveTemplateKey($data);

        if ($templateKey === null) {
            throw new RuntimeException('No hay plantilla de estampado para este carnet.');
        }

        $templatePath = AffiliateCardTemplateBuilder::templatePathForKey($templateKey);

        if (! is_file($templatePath)) {
            throw new RuntimeException("No se encontró la plantilla PDF: {$templatePath}");
        }

        self::ensureDirectory(dirname($outputPath));

        $prepared = self::prepareViewData($data);

        $pdf = new Fpdi('P', 'mm', [AffiliateCardPageLayout::WIDTH_MM, AffiliateCardPageLayout::HEIGHT_MM]);
        $pdf->AddPage();
        $pdf->setSourceFile($templatePath);
        $templateId = $pdf->importPage(1);
        $pdf->useTemplate($templateId, 0, 0, AffiliateCardPageLayout::WIDTH_MM, AffiliateCardPageLayout::HEIGHT_MM);

        $pdf->SetFont(
            AffiliateCardPageLayout::FONT_FAMILY,
            AffiliateCardPageLayout::FONT_STYLE,
            AffiliateCardPageLayout::FONT_SIZE_PT,
        );
        $pdf->SetTextColor(0, 0, 0);

        self::writeField($pdf, 'code', self::upper((string) ($prepared['code'] ?? '')));
        self::writeField($pdf, 'name_first_part', self::upper((string) ($prepared['name_first_part'] ?? '')));
        self::writeField($pdf, 'name_second_part', self::upper((string) ($prepared['name_second_part'] ?? '')));
        self::writeField($pdf, 'ci', self::upper((string) ($prepared['ci'] ?? '')));
        self::writeField($pdf, 'plan', self::upper((string) ($prepared['plan_tarjeta_etiqueta'] ?? '')));
        self::writeField($pdf, 'desde', (string) ($prepared['desde'] ?? ''));
        self::writeField($pdf, 'frecuencia', self::upper((string) ($prepared['frecuencia'] ?? '')));
        self::writeField($pdf, 'hasta', (string) ($prepared['hasta'] ?? ''));
        self::writeField($pdf, 'cobertura', self::upper((string) ($prepared['cobertura_display'] ?? '')));

        $pdf->Output('F', $outputPath);

        if (! is_file($outputPath)) {
            throw new RuntimeException('El carnet estampado no se guardó en disco.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function canGenerate(array $data): bool
    {
        if (! (bool) config('affiliate-card.stamped_generation_enabled', true)) {
            return false;
        }

        $templateKey = self::resolveTemplateKey($data);

        return $templateKey !== null
            && AffiliateCardTemplateBuilder::templateExists($templateKey);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function resolveTemplateKey(array $data): ?string
    {
        if (isset($data['template_key']) && is_string($data['template_key']) && $data['template_key'] !== '') {
            return $data['template_key'];
        }

        $qrFilename = $data['plan_qr_filename'] ?? null;

        if (! is_string($qrFilename) || $qrFilename === '') {
            $planId = isset($data['plan_id']) ? (int) $data['plan_id'] : null;
            $planDescription = (string) ($data['plan'] ?? '');
            $qrFilename = \App\Support\TarjetaAfiliacionQrPlanCatalog::resolveQrFilename($planId, $planDescription);
        }

        return AffiliateCardTemplateBuilder::resolveTemplateKey($qrFilename);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function prepareViewData(array $data): array
    {
        $split = UtilsController::splitName(isset($data['name']) ? (string) $data['name'] : null);

        $planId = isset($data['plan_id']) ? (int) $data['plan_id'] : null;
        $planDescription = (string) ($data['plan'] ?? '');

        $coberturaVal = $data['cobertura'] ?? null;
        $coberturaDisplay = match (true) {
            ! filled($coberturaVal) || $coberturaVal === '' => '',
            is_numeric($coberturaVal) => number_format((float) $coberturaVal, 2, ',', '.').' US$',
            default => (string) $coberturaVal,
        };

        return [
            'code' => $data['code'] ?? '',
            'name_first_part' => $split['first_part'],
            'name_second_part' => $split['second_part'],
            'ci' => $data['ci'] ?? '',
            'plan_tarjeta_etiqueta' => $data['plan_tarjeta_etiqueta']
                ?? \App\Support\TarjetaAfiliacionQrPlanCatalog::displayTagForPlan($planId, $planDescription),
            'desde' => $data['desde'] ?? '',
            'hasta' => $data['hasta'] ?? '',
            'frecuencia' => $data['frecuencia'] ?? '',
            'cobertura_display' => $coberturaDisplay,
        ];
    }

    private static function writeField(Fpdi $pdf, string $field, string $value): void
    {
        if ($value === '') {
            return;
        }

        $position = AffiliateCardPageLayout::fieldPosition($field);

        $pdf->SetXY($position['x'], $position['y']);
        $pdf->Cell(
            $position['width_mm'],
            4,
            self::toPdfEncoding($value),
            0,
            0,
            $position['align'],
        );
    }

    private static function toPdfEncoding(string $value): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value);

        return is_string($converted) ? $converted : $value;
    }

    private static function upper(string $value): string
    {
        return mb_strtoupper($value);
    }

    private static function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException("No se pudo crear el directorio: {$directory}");
        }
    }
}
