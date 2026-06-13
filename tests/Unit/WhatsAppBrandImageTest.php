<?php

declare(strict_types=1);

use App\Support\WhatsAppBrandImage;
use Tests\TestCase;

uses(TestCase::class);

it('expone la ruta publica de la imagen corporativa de whatsapp', function (): void {
    expect(WhatsAppBrandImage::RELATIVE_PATH)->toBe('images-whatsapp/integracorp.png')
        ->and(WhatsAppBrandImage::publicUrl())->toContain('images-whatsapp/integracorp.png');
});

it('convierte mensajes tipo url a imagen corporativa en notificationBirthday', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/NotificationController.php'))
        ->toContain("if (\$type === 'url')")
        ->toContain('WhatsAppBrandImage::RELATIVE_PATH');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/ScheduledTaskRunReport.php'))
        ->toContain('sendSummaryWithBrandImage')
        ->toContain("'image'");
});
