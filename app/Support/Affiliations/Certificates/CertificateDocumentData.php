<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use App\Models\AffiliationCertificateIssue;
use App\Support\QrCode\GdPngQrCodeGenerator;
use Illuminate\Support\Str;

/**
 * Ensambla lo común del certificado (individual o corporativo): tema, imágenes,
 * QR de verificación y la paginación del diseño «Voucher Tu Dr en Casa».
 *
 * La paginación replica la de la maqueta (hoja carta, filas de alto fijo) para que
 * cada página se dibuje entera y DomPDF nunca parta un bloque.
 */
final class CertificateDocumentData
{
    /** Alto útil de la hoja carta menos márgenes, en px a 96 ppp (como la maqueta). */
    private const CONTENT_HEIGHT = 998;

    private const BENEFIT_ROW = 20;

    private const SAFE = 4;

    /** Filas de la relación de afiliados por página. */
    public const RELATION_ROWS_PER_PAGE = 40;

    /** Hasta 28 caben en una página junto con el cierre; si no, se reparten de a 40. */
    private const RELATION_LAST_PAGE_ROWS = 28;

    public const CARNETS_PER_PAGE = 4;

    /**
     * @param  array{tipo: string, codigo: string, planLabel: string, benefits: array{rows: list<array{t: string, cob: string, limited: bool}>, hero: array{label: string, value: string, note: string}, carnet: string, emergency: bool}, preexNote: bool, heroSub: string, period: ?CertificatePaymentPeriod, contratante: string, contratanteId: string, agente: string, tarifaAnual: string, fechaAfiliacion: string, people: list<array<string, mixed>>, carnets: list<array<string, mixed>>, grupoTitulo: string, relationPlanColumn?: bool, issue: AffiliationCertificateIssue}  $in
     * @return array<string, mixed>
     */
    public static function assemble(array $in): array
    {
        $issue = $in['issue'];
        $theme = CertificateTheme::forPlan($in['planLabel']);
        $period = $in['period'];
        $benefits = $in['benefits'];
        $people = $in['people'];
        $count = count($people);
        $hasEmergency = $benefits['emergency'];

        $verificationUrl = route('affiliation-certificate.verify', ['key' => $issue->verification_key]);

        $pages = self::paginate(
            benefitRows: $benefits['rows'],
            people: $people,
            carnets: $in['carnets'],
            individual: $count === 1,
            hasEmergency: $hasEmergency,
        );

        return [
            'tipo' => $in['tipo'],
            'codigo' => $in['codigo'],
            'planLabel' => $in['planLabel'],
            'planNombre' => self::shortPlanName($in['planLabel']),
            'th' => $theme,
            'images' => [
                'logo' => self::imageDataUri('logo-tu-doctor-en-casa.png'),
                'logoTheme' => self::imageDataUri($theme['logo']),
                'seal' => self::imageDataUri('sello-tu-doctor-en-casa.png'),
                'qr' => 'data:image/png;base64,'.base64_encode(GdPngQrCodeGenerator::generate($verificationUrl, 264, 'M', 0)),
                'heroWave' => self::waveDataUri($theme['wave'], 720, 60, 'M0 44 C 180 10, 380 64, 720 18 L720 60 L0 60 Z'),
                'carnetWave' => self::waveDataUri($theme['wave'], 324, 40, 'M0 30 C 90 6, 190 42, 324 12 L324 40 L0 40 Z'),
            ],
            'heroLabel' => $benefits['hero']['label'],
            'heroValor' => $benefits['hero']['value'],
            'heroNota' => $benefits['hero']['note'],
            'heroSub' => $in['heroSub'],
            'desde' => $period?->start->format('d/m/Y') ?? '—',
            'hasta' => $period?->end->format('d/m/Y') ?? '—',
            'periodoFacturado' => $period?->currentPeriodLabel() ?? '—',
            'pagado' => (bool) $period?->currentIsPaid,
            'periodoPagado' => $period?->paidPeriodLabel(),
            'contratante' => $in['contratante'],
            'contratanteId' => $in['contratanteId'],
            'agente' => $in['agente'],
            'tarifaAnual' => $in['tarifaAnual'],
            'fechaAfiliacion' => $in['fechaAfiliacion'],
            'emision' => $issue->created_at?->format('d/m/Y') ?? now()->format('d/m/Y'),
            'esIndividual' => $count === 1,
            'esGrupo' => $count > 1,
            'beneficiarios' => array_slice($people, 0, 1),
            'nAfiliados' => $count,
            'grupoTitulo' => $in['grupoTitulo'],
            'relPaginas' => self::relationPagesLabel($pages),
            'nBeneficios' => count(array_filter($benefits['rows'], static fn (array $row): bool => ! isset($row['section']))),
            'relationPlanColumn' => (bool) ($in['relationPlanColumn'] ?? false),
            'preexNote' => $in['preexNote'] || $hasEmergency,
            'carnetCobertura' => $benefits['carnet'],
            'carnetPreex' => $hasEmergency,
            'verificationKey' => $issue->verification_key,
            'verificationUrl' => $verificationUrl,
            'pages' => $pages,
            'totalPaginas' => count($pages),
        ];
    }

    /**
     * «PLAN ESPECIAL» → «ESPECIAL»: el prefijo ya lo pone el diseño.
     */
    public static function shortPlanName(string $planLabel): string
    {
        $name = trim(preg_replace('/^PLAN\s+/iu', '', trim($planLabel)) ?? $planLabel);

        return Str::upper($name !== '' ? $name : $planLabel);
    }

    /**
     * @param  list<array{t: string, cob: string, limited: bool}>  $benefitRows
     * @param  list<array<string, mixed>>  $people
     * @param  list<array<string, mixed>>  $carnets
     * @return list<array<string, mixed>>
     */
    public static function paginate(array $benefitRows, array $people, array $carnets, bool $individual, bool $hasEmergency): array
    {
        $count = count($people);
        // Alturas medidas en el PDF real (no las de la maqueta, que restaba 150 px al bloque de grupo).
        $fixedCert = 45 + 176 + 84 + ($individual ? 116 : 66) + 56 + 60 + 14 * 5;
        $fixedCont = 58 + 52 + 34 + 14 * 3;
        $seal = 121 + 14 + ($hasEmergency ? 22 : 0);

        $raw = [];
        $rest = $benefitRows;
        $first = true;

        while (true) {
            $fixed = $first ? $fixedCert : $fixedCont;
            $capWithSeal = max(1, intdiv(self::CONTENT_HEIGHT - $fixed - $seal - self::SAFE, self::BENEFIT_ROW));
            $capWithoutSeal = max(1, intdiv(self::CONTENT_HEIGHT - $fixed - self::SAFE, self::BENEFIT_ROW));

            if (count($rest) <= $capWithSeal) {
                $raw[] = ['kind' => $first ? 'cert' : 'cont', 'rows' => $rest, 'seal' => true];
                break;
            }

            $take = min($capWithoutSeal, count($rest) - 1);
            $raw[] = ['kind' => $first ? 'cert' : 'cont', 'rows' => array_slice($rest, 0, $take), 'seal' => false];
            $rest = array_slice($rest, $take);
            $first = false;
        }

        if ($count > 1) {
            $offset = 0;

            while ($count - $offset > self::RELATION_LAST_PAGE_ROWS) {
                $take = min(self::RELATION_ROWS_PER_PAGE, $count - $offset - 1);
                $raw[] = ['kind' => 'rel', 'people' => array_slice($people, $offset, $take)];
                $offset += $take;
            }

            $raw[] = ['kind' => 'rel', 'people' => array_slice($people, $offset)];
        }

        if ($carnets !== []) {
            $offset = 0;
            $total = count($carnets);

            // La última hoja lleva la guía de uso: caben dos carnets con ella.
            while ($total - $offset > 2) {
                $take = min(self::CARNETS_PER_PAGE, $total - $offset - 1);
                $raw[] = ['kind' => 'carnet', 'people' => array_slice($carnets, $offset, $take)];
                $offset += $take;
            }

            $raw[] = ['kind' => 'carnet', 'people' => array_slice($carnets, $offset), 'info' => true];
        }

        $pages = [];

        foreach ($raw as $index => $page) {
            $kind = $page['kind'];
            $next = $raw[$index + 1]['kind'] ?? null;

            $pages[] = [
                'num' => $index + 1,
                'kind' => $kind,
                'isCert' => $kind === 'cert',
                'title' => match ($kind) {
                    'cont' => 'Beneficios del plan (continuación)',
                    'rel' => 'Relación de afiliados',
                    'carnet' => count($carnets) > 1 ? 'Carnets de afiliado' : 'Tu carnet de afiliado',
                    default => '',
                },
                'note' => match ($kind) {
                    'rel' => $count.' afiliados en este certificado',
                    'carnet' => 'Recorta por la línea punteada o guarda esta página en tu teléfono.',
                    default => '',
                },
                'rows' => $page['rows'] ?? [],
                'hasBenef' => isset($page['rows']),
                'hasSeal' => (bool) ($page['seal'] ?? false),
                'people' => $page['people'] ?? [],
                'lastRel' => $kind === 'rel' && $next !== 'rel',
                'hasInfo' => (bool) ($page['info'] ?? false),
            ];
        }

        return $pages;
    }

    /**
     * @param  list<array<string, mixed>>  $pages
     */
    private static function relationPagesLabel(array $pages): string
    {
        $relation = array_values(array_filter($pages, static fn (array $page): bool => $page['kind'] === 'rel'));

        if ($relation === []) {
            return '';
        }

        $first = $relation[0]['num'];
        $last = $relation[count($relation) - 1]['num'];

        return $first === $last ? 'la página '.$first : 'las páginas '.$first.' a '.$last;
    }

    private static function imageDataUri(string $file): string
    {
        $path = public_path('image/certificado-afiliacion/'.$file);
        $mime = str_ends_with($file, '.jpg') ? 'image/jpeg' : 'image/png';

        return is_file($path) ? 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path)) : '';
    }

    private static function waveDataUri(string $color, int $width, int $height, string $path): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'" preserveAspectRatio="none" width="'.$width.'" height="'.$height.'">'
            .'<path d="'.$path.'" fill="'.$color.'"/></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
