<?php

declare(strict_types=1);

namespace App\Support\AffiliateCard;

use App\Http\Controllers\TarjetaAfiliacionController;
use App\Support\DomPdfBatchRenderOptions;
use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;

final class AffiliateCardTemplateBuilder
{
    /**
     * @return list<string> Rutas absolutas de plantillas generadas
     */
    public static function buildAll(): array
    {
        $generated = [];

        foreach ((array) config('affiliate-card.template_keys', []) as $qrFilename => $templateKey) {
            $qrPath = config('affiliate-card.qr_plans_path').'/'.$qrFilename;

            if (! is_file($qrPath)) {
                continue;
            }

            $outputPath = self::templatePathForKey((string) $templateKey);
            self::buildForTemplateKey((string) $templateKey, $outputPath);
            $generated[] = $outputPath;
        }

        foreach ((array) config('affiliate-card.standalone_template_keys', []) as $templateKey) {
            $outputPath = self::templatePathForKey((string) $templateKey);
            self::buildForTemplateKey((string) $templateKey, $outputPath);
            $generated[] = $outputPath;
        }

        if ($generated === []) {
            throw new RuntimeException('No se generó ninguna plantilla: faltan archivos QR en storage.');
        }

        return $generated;
    }

    public static function buildForTemplateKey(
        string $templateKey,
        ?string $outputPath = null,
    ): string {
        $outputPath ??= self::templatePathForKey($templateKey);
        self::ensureDirectory(dirname($outputPath));

        $planLabel = self::planLabelForTemplateKey($templateKey);

        $data = TarjetaAfiliacionController::prepareDataForTarjetaPdfView([
            'name' => '',
            'ci' => '',
            'code' => '',
            'plan' => $planLabel,
            'frecuencia' => '',
            'cobertura' => '',
            'desde' => '',
            'hasta' => '',
            'card_layout' => in_array($templateKey, [
                AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL,
                AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION,
            ], true)
                ? $templateKey
                : null,
        ]);

        $data = self::withoutStampedFields($data);

        if (in_array($templateKey, [
            AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL,
            AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION,
        ], true)) {
            $data['plan_qr_absolute_path'] = null;
        }

        $view = match ($templateKey) {
            AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL => 'documents.tarjeta-afiliado-individual',
            AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION => 'documents.tarjeta-afiliado-individual-affiliation',
            default => 'documents.tarjeta-afiliado',
        };

        $pdf = Pdf::loadView($view, ['data' => $data]);
        DomPdfBatchRenderOptions::apply($pdf);
        $pdf->save($outputPath);

        return $outputPath;
    }

    public static function templatePathForKey(string $templateKey): string
    {
        return (string) config('affiliate-card.templates_path').'/carnet-'.$templateKey.'.pdf';
    }

    public static function templateExists(string $templateKey): bool
    {
        return is_file(self::templatePathForKey($templateKey));
    }

    public static function resolveTemplateKey(?string $qrFilename): ?string
    {
        if (! is_string($qrFilename) || $qrFilename === '') {
            return null;
        }

        $map = (array) config('affiliate-card.template_keys', []);

        return isset($map[$qrFilename]) ? (string) $map[$qrFilename] : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withoutStampedFields(array $data): array
    {
        $data['name_first_part'] = '';
        $data['name_second_part'] = '';
        $data['name'] = '';
        $data['ci'] = '';
        $data['code'] = '';
        $data['plan_tarjeta_etiqueta'] = '';
        $data['desde'] = '';
        $data['hasta'] = '';
        $data['frecuencia'] = '';
        $data['cobertura_display'] = '';

        return $data;
    }

    private static function planLabelForTemplateKey(string $templateKey): string
    {
        return match ($templateKey) {
            'inclusion' => 'INCLUSIÓN',
            'inicial' => 'INICIAL',
            'ideal' => 'IDEAL',
            'especial' => 'ESPECIAL',
            'individual' => 'INCLUSIÓN',
            'individual-affiliation' => 'INCLUSIÓN',
            default => mb_strtoupper($templateKey),
        };
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
