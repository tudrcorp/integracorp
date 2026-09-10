<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use App\Models\Affiliation;
use App\Services\AffiliationBusinessDocumentsService;

/**
 * Resuelve y verifica los tres documentos del kit de bienvenida de una afiliación individual.
 *
 * La tarjeta dejó de llamarse siempre `TAR-{code}.pdf`: hoy se emite una por familiar
 * (`TAR-{code}-{affiliate_id}.pdf`) y el nombre legado solo existe en afiliaciones antiguas.
 * Adjuntar la ruta a ciegas hacía fallar el envío en el worker, sin que el analista lo supiera.
 */
final class WelcomeKitAttachments
{
    private function __construct(
        public readonly ?string $certificatePath,
        public readonly ?string $cardPath,
        public readonly ?string $condicionadoPath,
    ) {}

    public static function forAffiliation(Affiliation $record): self
    {
        return new self(
            AffiliationBusinessDocumentsService::resolveCertificateAbsolutePath($record),
            self::resolveCardAbsolutePath($record),
            AffiliationBusinessDocumentsService::condicionadoAbsolutePathForAffiliation($record),
        );
    }

    /**
     * Primera tarjeta existente entre los nombres válidos para el titular (nuevo y legado).
     */
    private static function resolveCardAbsolutePath(Affiliation $record): ?string
    {
        $directory = public_path('storage/tarjeta-afiliacion/');

        foreach (AffiliationBusinessDocumentsService::titularTarjetaCandidateFilenames($record) as $filename) {
            $path = $directory.$filename;

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public function isComplete(): bool
    {
        return $this->missingLabels() === [];
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return array_values(array_filter(
            [$this->certificatePath, $this->cardPath, $this->condicionadoPath],
            static fn (?string $path): bool => $path !== null && is_file($path),
        ));
    }

    /**
     * @return list<string>
     */
    public function filenames(): array
    {
        return array_map(static fn (string $path): string => basename($path), $this->paths());
    }

    /**
     * Documentos que faltan, en español, para decirle al analista qué generar antes de reenviar.
     *
     * @return list<string>
     */
    public function missingLabels(): array
    {
        $missing = [];

        if ($this->certificatePath === null) {
            $missing[] = 'el certificado de afiliación';
        }

        if ($this->cardPath === null) {
            $missing[] = 'la tarjeta del titular';
        }

        if ($this->condicionadoPath === null) {
            $missing[] = 'el condicionado del plan';
        }

        return $missing;
    }

    public function missingSummary(): string
    {
        $missing = $this->missingLabels();

        if ($missing === []) {
            return '';
        }

        if (count($missing) === 1) {
            return 'Falta '.$missing[0].'.';
        }

        $last = array_pop($missing);

        return 'Faltan '.implode(', ', $missing).' y '.$last.'.';
    }
}
