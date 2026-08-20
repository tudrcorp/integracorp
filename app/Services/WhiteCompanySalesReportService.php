<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Affiliation;
use App\Models\User;
use App\Models\WhiteCompany;
use App\Support\SecurityAudit;
use App\Support\WhiteCompanies\WhiteCompanyDocumentBrand;
use App\Support\WhiteCompanies\WhiteCompanySalesReportKey;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Reporte de ventas de una empresa aliada en un rango de fechas.
 *
 * Toma la neta congelada en cada afiliación (`white_company_neta`), no la matriz
 * de negociación vigente: si mañana se renegocian tarifas, los reportes ya
 * emitidos siguen cuadrando.
 */
final class WhiteCompanySalesReportService
{
    /**
     * @return array{
     *   company: WhiteCompany,
     *   from: string,
     *   to: string,
     *   rows: list<array<string, mixed>>,
     *   totals: array{sale_price: float, neta_tdg: float, neta_partner: float, affiliates: int},
     *   security_key: string,
     *   generated_at: string,
     *   generated_by: string
     * }
     */
    public static function build(WhiteCompany $company, string $from, string $to): array
    {
        $affiliations = self::affiliationsInRange($company, $from, $to);
        $brand = WhiteCompanyDocumentBrand::fromCompany($company);

        $rows = [];
        $totals = ['sale_price' => 0.0, 'neta_tdg' => 0.0, 'neta_partner' => 0.0, 'affiliates' => 0];

        foreach ($affiliations as $affiliation) {
            $salePrice = (float) ($affiliation->white_company_sale_price ?? 0);
            $netaTdg = (float) ($affiliation->white_company_neta ?? 0);
            $netaPartner = round($salePrice - $netaTdg, 2);
            $planId = $affiliation->plan_id !== null ? (int) $affiliation->plan_id : null;
            $affiliatesCount = $affiliation->affiliates->count();

            $rows[] = [
                'code' => (string) $affiliation->code,
                'titular' => trim((string) $affiliation->full_name_ti),
                'identification' => (string) $affiliation->nro_identificacion_ti,
                'activated_at' => (string) $affiliation->activated_at,
                'plan' => $brand->planDisplayName($planId, (string) ($affiliation->plan?->description ?? '')),
                'coverage' => $affiliation->coverage?->price !== null
                    ? (float) $affiliation->coverage->price
                    : null,
                'payment_frequency' => (string) ($affiliation->payment_frequency ?? ''),
                'affiliates_count' => $affiliatesCount,
                'sale_price' => $salePrice,
                'neta_tdg' => $netaTdg,
                'neta_partner' => $netaPartner,
            ];

            $totals['sale_price'] += $salePrice;
            $totals['neta_tdg'] += $netaTdg;
            $totals['neta_partner'] += $netaPartner;
            $totals['affiliates'] += $affiliatesCount;
        }

        $totals['sale_price'] = round($totals['sale_price'], 2);
        $totals['neta_tdg'] = round($totals['neta_tdg'], 2);
        $totals['neta_partner'] = round($totals['neta_partner'], 2);

        $keyRows = array_map(
            static fn (array $row): array => [
                'code' => $row['code'],
                'sale_price' => $row['sale_price'],
                'neta_tdg' => $row['neta_tdg'],
                'neta_partner' => $row['neta_partner'],
            ],
            $rows,
        );

        return [
            'company' => $company,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totals' => $totals,
            'security_key' => WhiteCompanySalesReportKey::make(
                (int) $company->getKey(),
                $from,
                $to,
                $keyRows,
                $totals,
            ),
            'generated_at' => now()->format('d/m/Y H:i'),
            'generated_by' => (string) (Auth::user()?->name ?? 'Sistema'),
        ];
    }

    /**
     * `activated_at` se guarda como texto `d/m/Y`, así que el rango se resuelve
     * con STR_TO_DATE en lugar de comparar cadenas.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Affiliation>
     */
    public static function affiliationsInRange(WhiteCompany $company, string $from, string $to): \Illuminate\Database\Eloquent\Collection
    {
        $agencyCodes = self::agencyCodesFor($company);

        return Affiliation::query()
            ->where(function ($query) use ($company, $agencyCodes): void {
                /**
                 * El vínculo directo es `white_company_id`, pero las afiliaciones
                 * anteriores a ese campo solo se reconocen por el código de agencia
                 * de la aliada, que es como las resuelve el resto del sistema.
                 */
                $query->where('white_company_id', $company->getKey());

                if ($agencyCodes !== []) {
                    $query->orWhereIn('code_agency', $agencyCodes);
                }
            })
            ->whereNotNull('activated_at')
            ->where('activated_at', '!=', '')
            ->whereRaw("STR_TO_DATE(activated_at, '%d/%m/%Y') BETWEEN ? AND ?", [
                self::toSqlDate($from),
                self::toSqlDate($to),
            ])
            ->with(['plan', 'coverage', 'affiliates'])
            ->orderByRaw("STR_TO_DATE(activated_at, '%d/%m/%Y')")
            ->orderBy('code')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public static function pdfFilename(array $report): string
    {
        /** @var WhiteCompany $company */
        $company = $report['company'];

        return 'REPORTE-VENTAS-'.str_replace(' ', '-', strtoupper((string) $company->name))
            .'-'.str_replace('/', '', $report['from']).'-'.str_replace('/', '', $report['to']).'.pdf';
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public static function renderPdfToString(array $report): string
    {
        return self::pdf($report)->output();
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public static function savePdf(array $report): string
    {
        $directory = public_path('storage/reportes-aliadas/');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.self::pdfFilename($report);
        self::pdf($report)->save($path);

        return $path;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private static function pdf(array $report): \Barryvdh\DomPDF\PDF
    {
        /** @var WhiteCompany $company */
        $company = $report['company'];
        $brand = WhiteCompanyDocumentBrand::fromCompany($company);

        return Pdf::loadView('documents.white-company-sales-report', [
            'report' => $report,
            'company' => $company,
            'partnerLogo' => $brand->logoDataUri(),
            'tdgLogo' => self::tdgLogoDataUri(),
            'brandColor' => $brand->primaryColor,
            'verificationUrl' => route('white-company-sales-report.verify', [
                'key' => $report['security_key'],
            ]),
        ])->setPaper('a4', 'landscape');
    }

    public static function tdgLogoDataUri(): string
    {
        $path = public_path('image/logoNewTDG.png');

        if (! is_file($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            return '';
        }

        return 'data:image/png;base64,'.base64_encode($contents);
    }

    /**
     * Deja rastro de la emisión para poder verificar la llave más adelante.
     *
     * @param  array<string, mixed>  $report
     */
    public static function auditIssue(array $report, string $route, ?string $recipient = null): void
    {
        /** @var WhiteCompany $company */
        $company = $report['company'];

        SecurityAudit::log('AUDIT_WHITE_COMPANY_SALES_REPORT_ISSUED', $route, [
            'panel' => 'administration',
            'module' => 'white_companies',
            'white_company_id' => $company->getKey(),
            'white_company_name' => $company->name,
            'from' => $report['from'],
            'to' => $report['to'],
            'rows' => count($report['rows']),
            'totals' => $report['totals'],
            'security_key' => $report['security_key'],
            'recipient' => $recipient,
        ]);
    }

    /**
     * Texto que acompaña al PDF en WhatsApp. Mismo tono formal del correo, en
     * la extensión que admite un pie de documento.
     *
     * @param  array<string, mixed>  $report
     */
    public static function whatsAppCaption(array $report): string
    {
        /** @var WhiteCompany $company */
        $company = $report['company'];
        $totals = $report['totals'];
        $money = static fn (float $value): string => number_format($value, 2, ',', '.').' US$';

        return implode("\n", [
            '*Tu Dr Group — Estado de cuenta*',
            '',
            'Estimados señores de '.$company->name.':',
            '',
            'Adjuntamos el estado de cuenta correspondiente a la conciliación de las afiliaciones '
                .'ejecutadas entre el '.$report['from'].' y el '.$report['to'].'.',
            '',
            'Afiliaciones ejecutadas: '.count($report['rows']),
            'Monto total a pagar: '.$money((float) $totals['sale_price']),
            'Neta Tu Dr Group: '.$money((float) $totals['neta_tdg']),
            'Neta '.$company->name.': '.$money((float) $totals['neta_partner']),
            '',
            'Llave de verificación: '.$report['security_key'],
            '',
            'Quedamos a su disposición para cualquier aclaratoria.',
        ]);
    }

    /**
     * Códigos de agencia con los que opera la aliada.
     *
     * @return list<string>
     */
    public static function agencyCodesFor(WhiteCompany $company): array
    {
        return User::query()
            ->where('white_company_id', $company->getKey())
            ->whereNotNull('code_agency')
            ->where('code_agency', '!=', '')
            ->distinct()
            ->pluck('code_agency')
            ->map(static fn (mixed $code): string => (string) $code)
            ->values()
            ->all();
    }

    private static function toSqlDate(string $value): string
    {
        return Carbon::createFromFormat('d/m/Y', $value)->startOfDay()->format('Y-m-d');
    }
}
