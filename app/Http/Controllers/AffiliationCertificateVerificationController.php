<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Affiliation;
use App\Models\AffiliationCertificateIssue;
use App\Models\AffiliationCorporate;
use App\Support\Affiliations\Certificates\CertificateVerificationKey;
use App\Support\Affiliations\Certificates\CorporateCertificateDocument;
use App\Support\Affiliations\Certificates\IndividualCertificateDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Página pública a la que lleva el QR del certificado: el carnet digital de Tu
 * Doctor en Casa (contactos 24/7) con la verificación de la afiliación.
 *
 * Muestra lo mínimo para confirmar que el documento es auténtico y está vigente:
 * plan, vigencia y estado, sin datos personales del grupo familiar.
 */
class AffiliationCertificateVerificationController extends Controller
{
    public function __invoke(Request $request, ?string $key = null): View
    {
        $candidate = $key ?? (string) $request->query('llave', '');
        $normalized = CertificateVerificationKey::normalize($candidate);

        $issue = $normalized !== null
            ? AffiliationCertificateIssue::query()->where('verification_key', $normalized)->first()
            : null;

        return view('affiliation-certificate-verification', [
            'key' => $normalized ?? trim($candidate),
            'status' => match (true) {
                trim($candidate) === '' => 'empty',
                $issue === null => 'not_found',
                default => 'found',
            },
            'verification' => $issue !== null ? $this->verification($issue) : null,
        ]);
    }

    /**
     * @return array{corporate: bool, code: string, plan: string, holder: string, valid_from: ?string, valid_until: ?string, active: bool, paid: bool, status_label: string, issued_at: string}
     */
    private function verification(AffiliationCertificateIssue $issue): array
    {
        $affiliation = $issue->isIndividual()
            ? Affiliation::query()->with(['plan:id,description', 'affiliates'])->find($issue->affiliation_id)
            : AffiliationCorporate::query()->find($issue->affiliation_id);

        $active = $affiliation !== null && Str::upper(trim((string) $affiliation->status)) === 'ACTIVA';

        // El estado de pago se recalcula hoy: el documento pudo emitirse pagado y vencer después.
        $period = match (true) {
            $affiliation instanceof Affiliation => IndividualCertificateDocument::paymentPeriod($affiliation),
            $affiliation instanceof AffiliationCorporate => CorporateCertificateDocument::paymentPeriod($affiliation),
            default => null,
        };
        $paid = $period !== null ? $period->currentIsPaid : (bool) $issue->current_period_paid;

        $holder = $affiliation instanceof Affiliation
            ? (string) (IndividualCertificateDocument::printableAffiliates($affiliation)->first()?->full_name ?? $affiliation->full_name_ti)
            : (string) ($affiliation?->name_corporate ?? '');

        return [
            'corporate' => ! $issue->isIndividual(),
            'code' => $issue->affiliation_code,
            'plan' => (string) ($issue->plan_name ?? '—'),
            // La razón social no es dato personal: se muestra completa; el nombre de una persona, abreviado.
            'holder' => $affiliation instanceof AffiliationCorporate ? Str::upper(trim($holder)) : self::maskName($holder),
            'valid_from' => $period?->start->format('d/m/Y') ?? $issue->valid_from?->format('d/m/Y'),
            'valid_until' => $period?->end->format('d/m/Y') ?? $issue->valid_until?->format('d/m/Y'),
            'active' => $active,
            'paid' => $paid,
            'status_label' => match (true) {
                ! $active => 'Afiliación no vigente',
                ! $paid => 'Afiliación activa · período pendiente de pago',
                default => 'Afiliación activa y al día',
            },
            'issued_at' => $issue->created_at?->format('d/m/Y') ?? '—',
        ];
    }

    /**
     * «NOIRALIH SANCHEZ» → «Noiralih S.»: confirma a quién corresponde sin exponer el nombre completo.
     */
    public static function maskName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        if ($parts === []) {
            return '—';
        }

        $first = mb_convert_case(mb_strtolower($parts[0]), MB_CASE_TITLE, 'UTF-8');
        $initial = isset($parts[1]) ? ' '.mb_strtoupper(mb_substr($parts[1], 0, 1)).'.' : '';

        return $first.$initial;
    }
}
