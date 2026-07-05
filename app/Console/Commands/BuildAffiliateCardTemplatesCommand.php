<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\AffiliateCard\AffiliateCardTemplateBuilder;
use Illuminate\Console\Command;

class BuildAffiliateCardTemplatesCommand extends Command
{
    protected $signature = 'affiliate-card:build-templates
                            {--template= : Genera solo una plantilla (inclusion, inicial, ideal, especial)}';

    protected $description = 'Genera plantillas PDF base (DomPDF sin campos variables) para carnets por estampado';

    public function handle(): int
    {
        $onlyTemplate = $this->option('template');

        try {
            if (is_string($onlyTemplate) && $onlyTemplate !== '') {
                $path = AffiliateCardTemplateBuilder::buildForTemplateKey($onlyTemplate);
                $this->components->info("Plantilla generada: {$path}");

                return self::SUCCESS;
            }

            $paths = AffiliateCardTemplateBuilder::buildAll();

            foreach ($paths as $path) {
                $this->components->info("Plantilla generada: {$path}");
            }

            $this->components->info('Plantillas DomPDF base listas para estampado en producción.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
