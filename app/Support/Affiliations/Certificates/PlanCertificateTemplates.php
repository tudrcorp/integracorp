<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use App\Models\AffiliationCertificateIssue;
use App\Models\Plan;
use App\Models\PlanCertificateTemplate;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Diseños de la hoja del certificado por plan: subir (limpiar, validar y probar
 * con un PDF real), versiones, restaurar y volver al predeterminado.
 *
 * Solo lo usa el Generador de Certificado. Un plan sin diseño activo sigue con
 * el certificado predeterminado, exactamente como hoy.
 */
final class PlanCertificateTemplates
{
    public const REASON_MIN_LENGTH = 15;

    public static function available(): bool
    {
        static $available = null;

        return $available ??= Schema::hasTable('plan_certificate_templates');
    }

    public static function activeFor(?int $planId): ?PlanCertificateTemplate
    {
        if ($planId === null || $planId < 1 || ! self::available()) {
            return null;
        }

        return PlanCertificateTemplate::query()
            ->where('plan_id', $planId)
            ->where('status', PlanCertificateTemplate::STATUS_ACTIVE)
            ->latest('version')
            ->first();
    }

    /**
     * Revisa un HTML sin guardarlo: limpieza, estructura y un PDF de prueba.
     *
     * @return array{html: string, removed: list<string>, errors: list<string>, pages: int|null, warnings?: list<string>}
     */
    public static function inspect(Plan $plan, string $raw): array
    {
        if (trim($raw) === '') {
            return ['html' => '', 'removed' => [], 'errors' => ['El archivo está vacío.'], 'pages' => null, 'warnings' => []];
        }

        if (strlen($raw) > PlanCertificateTemplateEngine::MAX_BYTES) {
            return ['html' => '', 'removed' => [], 'errors' => ['El archivo pesa más de '.(int) (PlanCertificateTemplateEngine::MAX_BYTES / 1048576).' MB. Use imágenes del sistema ({{imagen.…}}) o comprima las imágenes incrustadas.'], 'pages' => null, 'warnings' => []];
        }

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = (string) mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
        }

        $clean = PlanCertificateTemplateEngine::sanitize($raw);
        $errors = [...self::contentProblems($raw, $clean['html']), ...PlanCertificateTemplateEngine::validate($clean['html'])];
        $pages = null;

        if ($errors === []) {
            try {
                $pages = PlanCertificateCover::pageCount($clean['html'], self::sampleData($plan));
                if ($pages > PlanCertificateCover::MAX_PAGES) {
                    $errors[] = 'El diseño ocupa '.$pages.' hojas; el máximo para la hoja del certificado es '.PlanCertificateCover::MAX_PAGES.'. Revise tamaños de letra y márgenes.';
                }
            } catch (Throwable $exception) {
                report($exception);
                $errors[] = 'El PDF de prueba no se pudo dibujar: '.mb_substr($exception->getMessage(), 0, 300);
            }
        }

        return ['html' => $clean['html'], 'removed' => $clean['removed'], 'errors' => array_values(array_unique($errors)), 'pages' => $pages, 'warnings' => self::warnings($raw)];
    }

    /**
     * Problemas que dejan el certificado vacío o con datos fijos, aunque el HTML sea válido.
     *
     * @return list<string>
     */
    public static function contentProblems(string $raw, string $clean): array
    {
        $problems = [];
        $visible = static fn (string $html): string => trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) preg_replace('/<(script|style)\b.*?<\/\1>/is', '', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $scripts = preg_match_all('/<script\b/i', $raw);
        $textLength = mb_strlen($visible($clean));

        if ($scripts > 0 && $textLength < 300) {
            $problems[] = 'El contenido de este archivo lo arma JavaScript al abrirlo en el navegador ('.$scripts.' script(s)). El PDF no ejecuta JavaScript y los scripts se quitan por seguridad: el certificado quedaría casi vacío. El diseño debe ser HTML estático, con los textos y tablas escritos en el archivo y los datos como marcadores (descargue la plantilla de ejemplo).';
        } elseif ($textLength < 40) {
            $problems[] = 'El archivo casi no tiene contenido visible: el certificado quedaría en blanco.';
        }

        if (preg_match('/\{\{\s*[a-z_]+\.[a-z_]+\s*\}\}/', $clean) !== 1) {
            $problems[] = 'El diseño no usa ningún marcador ({{certificado.codigo}}, {{plan.nombre}}, {{contratante.nombre}}…): todos los certificados saldrían con los mismos textos fijos del archivo, no con los datos de cada afiliación.';
        }

        return $problems;
    }

    /**
     * Avisos que no impiden activar el diseño, pero conviene revisar.
     *
     * @return list<string>
     */
    public static function warnings(string $raw): array
    {
        $warnings = [];

        if (preg_match('/display\s*:\s*(flex|grid|inline-flex|inline-grid)/i', $raw) === 1) {
            $warnings[] = 'Usa flex o grid: el PDF no los soporta y esos bloques pueden salir desordenados. Maquete con tablas.';
        }

        if (preg_match('/data:font\/(woff2?|otf)/i', $raw) === 1) {
            $warnings[] = 'Trae fuentes WOFF/OTF incrustadas: el PDF no las admite y usará Helvetica. Use fuentes TTF o las del sistema.';
        }

        return $warnings;
    }

    /**
     * Guarda el diseño como versión nueva y la activa. Todo o nada.
     */
    public static function upload(Plan $plan, string $raw, ?string $originalName, ?string $notes, User $user): PlanCertificateTemplate
    {
        $inspection = self::inspect($plan, $raw);
        if ($inspection['errors'] !== []) {
            throw new RuntimeException(implode(' ', $inspection['errors']));
        }

        $template = DB::transaction(static function () use ($plan, $raw, $originalName, $notes, $user, $inspection): PlanCertificateTemplate {
            Plan::query()->whereKey($plan->getKey())->lockForUpdate()->first();
            $version = (int) PlanCertificateTemplate::query()->where('plan_id', $plan->getKey())->max('version') + 1;

            self::deactivateCurrent($plan, $user, 'Reemplazado por la versión '.$version.'.');

            return PlanCertificateTemplate::query()->create([
                'plan_id' => $plan->getKey(),
                'version' => $version,
                'status' => PlanCertificateTemplate::STATUS_ACTIVE,
                'html' => $inspection['html'],
                'original_name' => $originalName !== null ? mb_substr($originalName, 0, 255) : null,
                'original_sha1' => sha1($raw),
                'removed' => $inspection['removed'],
                'page_count' => (int) $inspection['pages'],
                'notes' => filled($notes) ? mb_substr(trim((string) $notes), 0, 1000) : null,
                'created_by' => $user->getKey(),
                'created_by_name' => (string) $user->name,
                'activated_at' => now(),
            ]);
        });

        SecurityAudit::log('AUDIT_PLAN_CERTIFICATE_TEMPLATE_UPLOADED', 'business.plans.certificate-template', [
            'plan_id' => $plan->getKey(),
            'plan_code' => $plan->code,
            'template_id' => $template->getKey(),
            'version' => $template->version,
            'file' => $originalName,
            'removed' => $inspection['removed'],
            'pages' => $inspection['pages'],
        ], $user);

        return $template;
    }

    /**
     * Vuelve a activar una versión anterior.
     */
    public static function restore(PlanCertificateTemplate $template, User $user, string $reason): PlanCertificateTemplate
    {
        $reason = self::assertReason($reason);
        $plan = Plan::query()->findOrFail($template->plan_id);

        DB::transaction(static function () use ($template, $plan, $user, $reason): void {
            Plan::query()->whereKey($plan->getKey())->lockForUpdate()->first();
            self::deactivateCurrent($plan, $user, 'Se restauró la versión '.$template->version.'. '.$reason);
            $template->refresh();
            $template->forceFill(['status' => PlanCertificateTemplate::STATUS_ACTIVE, 'activated_at' => now(), 'deactivated_at' => null, 'deactivated_by_name' => null, 'deactivation_reason' => null])->save();
        });

        SecurityAudit::log('AUDIT_PLAN_CERTIFICATE_TEMPLATE_RESTORED', 'business.plans.certificate-template', [
            'plan_id' => $plan->getKey(),
            'template_id' => $template->getKey(),
            'version' => $template->version,
            'reason' => $reason,
        ], $user);

        return $template->fresh() ?? $template;
    }

    /**
     * Quita el diseño propio: el plan vuelve al certificado predeterminado.
     */
    public static function useDefault(Plan $plan, User $user, string $reason): void
    {
        $reason = self::assertReason($reason);

        $count = DB::transaction(static function () use ($plan, $user, $reason): int {
            Plan::query()->whereKey($plan->getKey())->lockForUpdate()->first();

            return self::deactivateCurrent($plan, $user, $reason);
        });

        if ($count === 0) {
            throw new RuntimeException('Este plan ya usa el certificado predeterminado.');
        }

        SecurityAudit::log('AUDIT_PLAN_CERTIFICATE_TEMPLATE_REMOVED', 'business.plans.certificate-template', [
            'plan_id' => $plan->getKey(),
            'plan_code' => $plan->code,
            'reason' => $reason,
        ], $user);
    }

    /**
     * PDF de muestra de la hoja del certificado con datos del plan.
     */
    public static function samplePdf(Plan $plan, string $html): string
    {
        return PlanCertificateCover::pdf(PlanCertificateTemplateEngine::render($html, self::sampleData($plan)));
    }

    /**
     * Datos de muestra armados como un certificado real: nombre, beneficios y
     * colores del plan salen de la base; el contratante y los afiliados son de ejemplo.
     *
     * @return array<string, mixed>
     */
    public static function sampleData(Plan $plan): array
    {
        $coverage = $plan->coverages()->orderBy('price')->first();
        $benefits = CertificateBenefits::for($plan, $coverage?->getKey() ? (int) $coverage->getKey() : null, $coverage?->price !== null ? (float) $coverage->price : null);

        $issue = new AffiliationCertificateIssue;
        $issue->forceFill(['verification_key' => 'CER-MUES-TRA0-0000', 'created_at' => now()]);

        $people = [];
        foreach ([['MARÍA FERNANDA PÉREZ ROJAS', 'V-12.345.678', 'TITULAR'], ['JOSÉ LUIS PÉREZ ROJAS', 'V-23.456.789', 'ESPOSO'], ['SOFÍA PÉREZ ROJAS', 'V-34.567.890', 'HIJA']] as $index => [$name, $doc, $relationship]) {
            $people[] = ['id' => $index + 1, 'num' => $index + 1, 'nombre' => $name, 'docFmt' => $doc, 'nac' => '01/01/'.(1980 + $index * 10), 'parentesco' => $relationship, 'coberturaMonto' => $benefits['carnet'], 'cobertura' => $benefits['carnet']];
        }

        $data = CertificateDocumentData::assemble([
            'tipo' => 'individual',
            'codigo' => 'TDEC-IND-MUESTRA',
            'planLabel' => (string) ($plan->description ?: 'Plan'),
            'benefits' => $benefits,
            'preexNote' => false,
            'heroSub' => 'Vista de muestra con datos de ejemplo',
            'period' => null,
            'contratante' => 'EMPRESA DE EJEMPLO, C.A.',
            'contratanteId' => 'J-12345678-9',
            'agente' => 'AGENTE DE EJEMPLO',
            'tarifaAnual' => 'US$ 1.200,00',
            'fechaAfiliacion' => now()->subYear()->format('d/m/Y'),
            'people' => $people,
            'carnets' => [],
            'grupoTitulo' => 'Grupo familiar afiliado',
            'issue' => $issue,
        ]);

        $data['benefitRows'] = $benefits['rows'];
        $data['coverPeople'] = $people;
        $data['desde'] = now()->format('d/m/Y');
        $data['hasta'] = now()->addYear()->subDay()->format('d/m/Y');
        $data['periodoFacturado'] = now()->format('m/Y');

        return $data;
    }

    private static function deactivateCurrent(Plan $plan, User $user, string $reason): int
    {
        return PlanCertificateTemplate::query()
            ->where('plan_id', $plan->getKey())
            ->where('status', PlanCertificateTemplate::STATUS_ACTIVE)
            ->update([
                'status' => PlanCertificateTemplate::STATUS_INACTIVE,
                'deactivated_at' => now(),
                'deactivated_by_name' => (string) $user->name,
                'deactivation_reason' => mb_substr($reason, 0, 1000),
                'updated_at' => now(),
            ]);
    }

    private static function assertReason(string $reason): string
    {
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));
        if (mb_strlen($reason) < self::REASON_MIN_LENGTH) {
            throw new RuntimeException('Explique el motivo (mínimo '.self::REASON_MIN_LENGTH.' caracteres).');
        }

        return mb_substr($reason, 0, 1000);
    }
}
