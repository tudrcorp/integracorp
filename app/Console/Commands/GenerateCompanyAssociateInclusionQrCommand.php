<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Companies\CompanyAssociateInclusionQrGenerator;
use Illuminate\Console\Command;

class GenerateCompanyAssociateInclusionQrCommand extends Command
{
    protected $signature = 'company-associate:generate-inclusion-qr
                            {--pdf= : Ruta absoluta al PDF de Canales de Comunicación}';

    protected $description = 'Publica el PDF de canales de comunicación y genera el QR de inclusión para tarjetas de nuevos negocios';

    public function handle(): int
    {
        $pdfPath = (string) ($this->option('pdf') ?: storage_path('app/imports/canales-de-comunicacion.pdf'));

        try {
            $result = CompanyAssociateInclusionQrGenerator::generate($pdfPath);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('PDF publicado en: '.$result['pdf_url']);
        $this->info('QR generado en: '.$result['qr_url']);
        $this->info('Logo del QR: '.$result['logo_path']);

        return self::SUCCESS;
    }
}
