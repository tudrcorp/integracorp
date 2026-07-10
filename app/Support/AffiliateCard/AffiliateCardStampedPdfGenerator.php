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

        if ($templateKey === AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION) {
            self::stampIndividualAffiliationCard(
                $data,
                $prepared,
                $templatePath,
                $outputPath,
                sheetSlot: 0,
                singleAffiliate: true,
            );

            return;
        }

        $page = AffiliateCardPageLayout::pageDimensions($templateKey);

        $pdf = new Fpdi($page['orientation'], 'mm', [$page['width_mm'], $page['height_mm']]);
        $pdf->AddPage();
        $pdf->setSourceFile($templatePath);
        $templateId = $pdf->importPage(1);
        $pdf->useTemplate($templateId, 0, 0, $page['width_mm'], $page['height_mm']);

        self::stampFields($pdf, $data, $prepared, $templateKey);

        $pdf->Output('F', $outputPath);

        if (! is_file($outputPath)) {
            throw new RuntimeException('El carnet estampado no se guardó en disco.');
        }
    }

    /**
     * Genera un PDF con varios carnets (8 por hoja A4 vertical).
     *
     * @param  list<array<string, mixed>>  $cards
     */
    public static function generateIndividualAffiliationBatch(array $cards, string $outputPath): void
    {
        if ($cards === []) {
            throw new RuntimeException('No hay carnets para generar.');
        }

        if (! (bool) config('affiliate-card.stamped_generation_enabled', true)) {
            throw new RuntimeException('La generación por estampado está deshabilitada.');
        }

        $templateKey = AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION;
        $templatePath = AffiliateCardTemplateBuilder::templatePathForKey($templateKey);

        if (! is_file($templatePath)) {
            throw new RuntimeException("No se encontró la plantilla PDF: {$templatePath}");
        }

        self::ensureDirectory(dirname($outputPath));

        $sheet = AffiliateCardPageLayout::sheetDimensions();
        $pdf = new Fpdi($sheet['orientation'], 'mm', [$sheet['width_mm'], $sheet['height_mm']]);
        $pdf->setSourceFile($templatePath);
        $templateId = $pdf->importPage(1);

        foreach ($cards as $index => $cardData) {
            $slotOnPage = $index % AffiliateCardPageLayout::CARDS_PER_SHEET;

            if ($slotOnPage === 0) {
                $pdf->AddPage();
            }

            $prepared = self::prepareViewData($cardData);
            $origin = AffiliateCardPageLayout::sheetCardOrigin($slotOnPage, false);
            $unit = AffiliateCardPageLayout::individualAffiliationUnitDimensions(false);

            $pdf->useTemplate(
                $templateId,
                $origin['x_mm'],
                $origin['y_mm'],
                $unit['width_mm'],
                $unit['height_mm'],
            );

            self::stampFields(
                $pdf,
                $cardData,
                $prepared,
                $templateKey,
                $origin['x_mm'],
                $origin['y_mm'],
                singleAffiliate: false,
            );
        }

        $pdf->Output('F', $outputPath);

        if (! is_file($outputPath)) {
            throw new RuntimeException('El lote de carnets no se guardó en disco.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $prepared
     */
    private static function stampIndividualAffiliationCard(
        array $data,
        array $prepared,
        string $templatePath,
        string $outputPath,
        int $sheetSlot,
        bool $singleAffiliate,
    ): void {
        $templateKey = AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION;
        $origin = AffiliateCardPageLayout::sheetCardOrigin($sheetSlot, $singleAffiliate);
        $unit = AffiliateCardPageLayout::individualAffiliationUnitDimensions($singleAffiliate);
        $sheet = AffiliateCardPageLayout::sheetDimensions();

        $pdf = new Fpdi($sheet['orientation'], 'mm', [$sheet['width_mm'], $sheet['height_mm']]);
        $pdf->AddPage();
        $pdf->setSourceFile($templatePath);
        $templateId = $pdf->importPage(1);
        $pdf->useTemplate(
            $templateId,
            $origin['x_mm'],
            $origin['y_mm'],
            $unit['width_mm'],
            $unit['height_mm'],
        );

        self::stampFields(
            $pdf,
            $data,
            $prepared,
            $templateKey,
            $origin['x_mm'],
            $origin['y_mm'],
            $singleAffiliate,
        );

        $pdf->Output('F', $outputPath);

        if (! is_file($outputPath)) {
            throw new RuntimeException('El carnet estampado no se guardó en disco.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $prepared
     */
    private static function stampFields(
        Fpdi $pdf,
        array $data,
        array $prepared,
        string $templateKey,
        float $offsetXmm = 0.0,
        float $offsetYmm = 0.0,
        bool $singleAffiliate = false,
    ): void {
        $scale = $templateKey === AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION
            ? AffiliateCardPageLayout::individualAffiliationStampScale($singleAffiliate)
            : 1.0;

        self::writeQrImage($pdf, $data, $templateKey, $offsetXmm, $offsetYmm, $scale);

        $pdf->SetFont(
            AffiliateCardPageLayout::FONT_FAMILY,
            AffiliateCardPageLayout::FONT_STYLE,
            AffiliateCardPageLayout::fontSizePt($templateKey, $singleAffiliate),
        );

        if ($templateKey === AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION) {
            $pdf->SetTextColor(0, 51, 102);
        } else {
            $pdf->SetTextColor(0, 0, 0);
        }

        self::writeField($pdf, $templateKey, 'code', self::upper((string) ($prepared['code'] ?? '')), $offsetXmm, $offsetYmm, $scale, $singleAffiliate);

        if ($templateKey === AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION) {
            self::writeField($pdf, $templateKey, 'name_first_part', self::upper((string) ($prepared['name_first_part'] ?? '')), $offsetXmm, $offsetYmm, $scale, $singleAffiliate);
            self::writeField($pdf, $templateKey, 'name_second_part', self::upper((string) ($prepared['name_second_part'] ?? '')), $offsetXmm, $offsetYmm, $scale, $singleAffiliate);
        } else {
            self::writeField($pdf, $templateKey, 'name_first_part', self::upper((string) ($prepared['name_first_part'] ?? '')), $offsetXmm, $offsetYmm, $scale, $singleAffiliate);
            self::writeField($pdf, $templateKey, 'name_second_part', self::upper((string) ($prepared['name_second_part'] ?? '')), $offsetXmm, $offsetYmm, $scale, $singleAffiliate);
        }

        self::writeField($pdf, $templateKey, 'ci', self::upper((string) ($prepared['ci'] ?? '')), $offsetXmm, $offsetYmm, $scale, $singleAffiliate);
        self::writeField($pdf, $templateKey, 'plan', self::upper((string) ($prepared['plan_tarjeta_etiqueta'] ?? '')), $offsetXmm, $offsetYmm, $scale, $singleAffiliate);
        self::writeField($pdf, $templateKey, 'desde', (string) ($prepared['desde'] ?? ''), $offsetXmm, $offsetYmm, $scale, $singleAffiliate);
        self::writeField($pdf, $templateKey, 'frecuencia', self::upper((string) ($prepared['frecuencia'] ?? '')), $offsetXmm, $offsetYmm, $scale, $singleAffiliate);
        self::writeField($pdf, $templateKey, 'hasta', (string) ($prepared['hasta'] ?? ''), $offsetXmm, $offsetYmm, $scale, $singleAffiliate);
        self::writeField($pdf, $templateKey, 'cobertura', self::upper((string) ($prepared['cobertura_display'] ?? '')), $offsetXmm, $offsetYmm, $scale, $singleAffiliate);
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
     * @param  list<array<string, mixed>>  $cards
     */
    public static function canGenerateBatch(array $cards): bool
    {
        if ($cards === []) {
            return false;
        }

        return self::canGenerate($cards[0]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function resolveTemplateKey(array $data): ?string
    {
        if (isset($data['template_key']) && is_string($data['template_key']) && $data['template_key'] !== '') {
            return $data['template_key'];
        }

        if (($data['card_layout'] ?? null) === AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION) {
            return AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION;
        }

        if (($data['card_layout'] ?? null) === AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL) {
            return AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL;
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
    public static function prepareViewData(array $data): array
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
            'name' => trim((string) ($data['name'] ?? '')),
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

    /**
     * @param  array<string, mixed>  $data
     */
    private static function writeQrImage(
        Fpdi $pdf,
        array $data,
        string $templateKey,
        float $offsetXmm = 0.0,
        float $offsetYmm = 0.0,
        float $scale = 1.0,
    ): void {
        $qrPath = $data['plan_qr_absolute_path'] ?? null;

        if (! is_string($qrPath) || $qrPath === '' || ! is_file($qrPath)) {
            return;
        }

        $position = AffiliateCardPageLayout::qrPosition($templateKey, $offsetXmm, $offsetYmm, $scale);

        if ($position === null) {
            return;
        }

        $pdf->Image(
            $qrPath,
            $position['x_mm'],
            $position['y_mm'],
            $position['size_mm'],
            $position['size_mm'],
        );
    }

    private static function writeField(
        Fpdi $pdf,
        string $templateKey,
        string $field,
        string $value,
        float $offsetXmm = 0.0,
        float $offsetYmm = 0.0,
        float $scale = 1.0,
        bool $singleAffiliate = false,
    ): void {
        if ($value === '') {
            return;
        }

        $position = AffiliateCardPageLayout::fieldPosition($field, $templateKey, $offsetXmm, $offsetYmm, $scale, $singleAffiliate);
        $encoded = self::toPdfEncoding($value);

        if ($templateKey === AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION) {
            $pdf->SetFont(
                AffiliateCardPageLayout::FONT_FAMILY,
                AffiliateCardPageLayout::FONT_STYLE,
                AffiliateCardPageLayout::fontSizePtForField($templateKey, $field, $singleAffiliate),
            );
            $pdf->Text($position['x'], $position['y'], $encoded);

            return;
        }

        $pdf->SetXY($position['x'], $position['y']);
        $pdf->Cell(
            $position['width_mm'],
            4,
            $encoded,
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
