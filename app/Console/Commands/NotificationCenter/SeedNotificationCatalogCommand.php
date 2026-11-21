<?php

declare(strict_types=1);

namespace App\Console\Commands\NotificationCenter;

use App\Support\NotificationCenter\NotificationCatalog;
use Illuminate\Console\Command;

class SeedNotificationCatalogCommand extends Command
{
    protected $signature = 'notifications:catalog-seed';

    protected $description = 'Carga (de forma aditiva) los tipos de notificación conocidos en el Centro de notificaciones';

    public function handle(): int
    {
        if (! NotificationCatalog::tablesExist()) {
            $this->error('Faltan las tablas del Centro de notificaciones: corra la migración 2026_11_21_120000_create_notification_center_tables.php.');

            return self::FAILURE;
        }

        $created = NotificationCatalog::seed();
        $this->info('Tipos nuevos en el catálogo: '.$created.'. Lo existente no se tocó.');

        return self::SUCCESS;
    }
}
