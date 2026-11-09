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
 * corporativa). El analista puede quitar o agregar antes de enviar, y sumar
 * testigos (CC) y copias ocultas (CCO) además de la fija {@see self::MANDATORY_BCC}.
 */
final class CertificateDeliveryRecipients
{
    /** Tope de destinatarios por envío: evita que el generador sirva de envío masivo. */
    public const MAX_RECIPIENTS = 10;

    /** Tope de correos testigos (CC) y, aparte, de copias ocultas (CCO) por envío. */
    public const MAX_COPIES = 10;

    /**
     * Copia oculta que lleva todo envío del generador. No se puede quitar: el
     * servicio la agrega aunque no llegue del formulario.
     *
     * @var list<string>
     */
    public const MANDATORY_BCC = ['solrodriguez@tudrencasa.com'];

    /**
     * Reparte las copias sin repetir a nadie: un correo que ya va en «Para» no
     * se copia, y uno que va en CC no se repite en CCO. La copia oculta fija
     * siempre queda, salvo que ya reciba el correo como destinatario o testigo.
     *
     * @param  list<string>  $to
     * @param  iterable<mixed>  $cc
     * @param  iterable<mixed>  $bcc
     * @return array{cc: list<string>, bcc: list<string>}
     */
    public static function copies(array $to, iterable $cc, iterable $bcc): array
    {
        $taken = array_flip($to);
        $ccClean = array_values(array_filter(self::clean($cc), static fn (string $email): bool => ! isset($taken[$email])));

        $taken += array_flip($ccClean);
        $bccClean = array_values(array_filter(
            self::clean([...self::MANDATORY_BCC, ...self::toList($bcc)]),
            static fn (string $email): bool => ! isset($taken[$email]),
        ));

        return ['cc' => $ccClean, 'bcc' => $bccClean];
    }

    /**
     * La copia oculta fija al frente, seguida de lo que haya escrito el analista.
     *
     * @param  iterable<mixed>  $bcc
     * @return list<string>
     */
    public static function withMandatoryBcc(iterable $bcc): array
    {
        return self::clean([...self::MANDATORY_BCC, ...self::toList($bcc)]);
    }

    /**
     * @param  iterable<mixed>  $values
     * @return list<mixed>
     */
    private static function toList(iterable $values): array
    {
        $list = [];

        foreach ($values as $value) {
            $list[] = $value;
        }

        return $list;
    }

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
