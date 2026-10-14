<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use App\Models\Affiliation;
use App\Models\AffiliationCertificateIssue;
use App\Models\AffiliationCorporate;

/**
 * Destinatarios sugeridos al enviar un certificado por correo: el dueño de la
 * afiliación (el agente; si no tiene, la agencia) y los correos de la propia
 * afiliación (titular y pagador en la individual; principal y de contacto en la
 * corporativa). El analista puede quitar o agregar antes de enviar.
 */
final class CertificateDeliveryRecipients
{
    /** Tope de destinatarios por envío: evita que el generador sirva de envío masivo. */
    public const MAX_RECIPIENTS = 10;

    /**
     * @return list<array{email: string, label: string}>
     */
    public static function suggestionsFor(AffiliationCertificateIssue $issue): array
    {
        $affiliation = $issue->isIndividual()
            ? Affiliation::query()->with(['agent:id,name,email', 'agency:id,code,name_corporative,email'])->find($issue->affiliation_id)
            : AffiliationCorporate::query()->with(['agent:id,name,email', 'agency:id,code,name_corporative,email'])->find($issue->affiliation_id);

        if ($affiliation === null) {
            return [];
        }

        $candidates = [];

        if ($affiliation->agent_id !== null && $affiliation->agent !== null) {
            $candidates[] = ['email' => $affiliation->agent->email, 'label' => 'Agente · '.$affiliation->agent->name];
        } elseif ($affiliation->agency !== null) {
            $candidates[] = ['email' => $affiliation->agency->email, 'label' => 'Agencia · '.$affiliation->agency->name_corporative];
        }

        if ($affiliation instanceof Affiliation) {
            $candidates[] = ['email' => $affiliation->email_ti, 'label' => 'Titular'];
            $candidates[] = ['email' => $affiliation->email_payer, 'label' => 'Pagador'];
        } else {
            $candidates[] = ['email' => $affiliation->email, 'label' => 'Empresa'];
            $candidates[] = ['email' => $affiliation->email_contact, 'label' => 'Contacto de la empresa'];
        }

        $suggestions = [];

        foreach ($candidates as $candidate) {
            $email = self::normalize($candidate['email'] ?? null);

            if ($email !== null && ! isset($suggestions[$email])) {
                $suggestions[$email] = ['email' => $email, 'label' => trim((string) $candidate['label'])];
            }
        }

        return array_values($suggestions);
    }

    /**
     * @return list<string>
     */
    public static function defaultsFor(AffiliationCertificateIssue $issue): array
    {
        return array_column(self::suggestionsFor($issue), 'email');
    }

    /**
     * Correos válidos, en minúsculas y sin repetir. Lo inválido se descarta.
     *
     * @param  iterable<mixed>  $emails
     * @return list<string>
     */
    public static function clean(iterable $emails): array
    {
        $clean = [];

        foreach ($emails as $email) {
            $normalized = self::normalize($email);

            if ($normalized !== null) {
                $clean[$normalized] = $normalized;
            }
        }

        return array_values($clean);
    }

    public static function normalize(mixed $email): ?string
    {
        if (! is_scalar($email)) {
            return null;
        }

        $email = mb_strtolower(trim((string) $email));

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }
}
