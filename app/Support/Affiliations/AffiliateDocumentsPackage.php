<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Services\AffiliationCorporateBusinessDocumentsService;
use App\Support\WhiteCompanies\WhiteCompanyDocumentBrand;
use RuntimeException;
use ZipArchive;

/**
 * Documentación descargable de un afiliado concreto: el condicionado del plan que le
 * corresponde y su carnet.
 *
 * La red comercial (agentes, agencias MASTER y GENERAL) solo lee lo que Negocios ya
 * emitió: aquí nunca se genera ni se regenera un PDF. Si un documento no está en disco
 * se informa como faltante y se entrega el resto.
 */
final class AffiliateDocumentsPackage
{
    public const CONDICIONADO_LABEL = 'Condicionado del plan';

    public const CARNET_LABEL = 'Carnet de afiliado';

    private function __construct(
        public readonly string $affiliationCode,
        public readonly string $affiliateName,
        public readonly string $identification,
        public readonly ?string $condicionadoPath,
        public readonly ?string $carnetPath,
    ) {}

    public static function forIndividual(Affiliation $affiliation, Affiliate $affiliate): self
    {
        $planId = self::intOrNull($affiliate->plan_id) ?? self::intOrNull($affiliation->plan_id);

        return new self(
            affiliationCode: trim((string) $affiliation->code),
            affiliateName: trim((string) $affiliate->full_name),
            identification: trim((string) $affiliate->nro_identificacion),
            condicionadoPath: WhiteCompanyDocumentBrand::forAffiliation($affiliation)
                ->condicionadoAbsolutePath($planId),
            carnetPath: self::resolveIndividualCarnetPath($affiliation, $affiliate),
        );
    }

    public static function forCorporate(AffiliationCorporate $affiliation, AffiliateCorporate $affiliate): self
    {
        /**
         * En una corporativa cada persona puede tener plan propio, así que el condicionado
         * se resuelve por afiliado y solo se cae al plan contratado de cabecera si la fila
         * no lo trae.
         */
        $planId = self::intOrNull($affiliate->plan_id)
            ?? AffiliationCorporateBusinessDocumentsService::resolvePlanId($affiliation);

        return new self(
            affiliationCode: trim((string) $affiliation->code),
            affiliateName: trim(trim((string) $affiliate->first_name).' '.trim((string) $affiliate->last_name)),
            identification: trim((string) $affiliate->nro_identificacion),
            condicionadoPath: WhiteCompanyDocumentBrand::forCorporate($affiliation)
                ->condicionadoAbsolutePath($planId),
            carnetPath: self::resolveCorporateCarnetPath($affiliation, $affiliate),
        );
    }

    public function hasAnyDocument(): bool
    {
        return $this->paths() !== [];
    }

    /**
     * Etiquetas de los documentos que no están emitidos todavía.
     *
     * @return list<string>
     */
    public function missingLabels(): array
    {
        $missing = [];

        if ($this->condicionadoPath === null) {
            $missing[] = self::CONDICIONADO_LABEL;
        }

        if ($this->carnetPath === null) {
            $missing[] = self::CARNET_LABEL;
        }

        return $missing;
    }

    /**
     * Archivos del paquete indexados por el nombre que llevarán dentro del ZIP.
     *
     * @return array<string, string>
     */
    public function paths(): array
    {
        $files = [];

        if ($this->condicionadoPath !== null) {
            $files[basename($this->condicionadoPath)] = $this->condicionadoPath;
        }

        if ($this->carnetPath !== null) {
            $files['Carnet-'.$this->fileSafeIdentification().'.pdf'] = $this->carnetPath;
        }

        return $files;
    }

    public function downloadFilename(): string
    {
        return $this->zipBasename().'.zip';
    }

    /**
     * Arma el ZIP en un temporal privado. Quien lo descargue debe borrarlo después
     * (`deleteFileAfterSend`): contiene datos personales del afiliado.
     */
    public function buildZip(): string
    {
        $files = $this->paths();

        if ($files === []) {
            throw new RuntimeException('El afiliado no tiene documentación emitida para descargar.');
        }

        $directory = storage_path('app/affiliate-documents-temp/');

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('No se pudo preparar el directorio temporal de la documentación.');
        }

        $zipPath = $directory.$this->zipBasename().'-'.bin2hex(random_bytes(4)).'.zip';

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo ZIP con la documentación del afiliado.');
        }

        foreach ($files as $name => $path) {
            $zip->addFile($path, $name);
        }

        $zip->close();

        return $zipPath;
    }

    public function zipBasename(): string
    {
        $code = self::fileSafe($this->affiliationCode);
        $identification = $this->fileSafeIdentification();

        return 'DOCUMENTOS-'.($code !== '' ? $code.'-' : '').$identification;
    }

    public static function carnetDirectory(): string
    {
        return public_path('storage/tarjeta-afiliacion/');
    }

    private static function resolveIndividualCarnetPath(Affiliation $affiliation, Affiliate $affiliate): ?string
    {
        $directory = self::carnetDirectory();
        $path = $directory.'TAR-'.$affiliation->code.'-'.$affiliate->getKey().'.pdf';

        if (is_file($path)) {
            return $path;
        }

        /**
         * Afiliaciones antiguas guardaron la tarjeta del titular sin el id del afiliado,
         * así que ese archivo solo puede atribuirse a quien comparte la cédula del titular.
         */
        $titularIdentification = trim((string) $affiliation->nro_identificacion_ti);
        $affiliateIdentification = trim((string) $affiliate->nro_identificacion);
        $legacyPath = $directory.'TAR-'.$affiliation->code.'.pdf';

        if (
            $titularIdentification !== ''
            && strcasecmp($titularIdentification, $affiliateIdentification) === 0
            && is_file($legacyPath)
        ) {
            return $legacyPath;
        }

        return null;
    }

    private static function resolveCorporateCarnetPath(AffiliationCorporate $affiliation, AffiliateCorporate $affiliate): ?string
    {
        $path = self::carnetDirectory().'TAR-'.$affiliation->code.'-'.$affiliate->getKey().'.pdf';

        return is_file($path) ? $path : null;
    }

    private function fileSafeIdentification(): string
    {
        $identification = self::fileSafe($this->identification);

        return $identification !== '' ? $identification : 'SIN-CEDULA';
    }

    private static function fileSafe(string $value): string
    {
        return trim((string) preg_replace('/[^A-Za-z0-9._-]/', '', $value));
    }

    private static function intOrNull(mixed $value): ?int
    {
        return filled($value) ? (int) $value : null;
    }
}
