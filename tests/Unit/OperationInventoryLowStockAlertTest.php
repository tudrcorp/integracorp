<?php

declare(strict_types=1);

use App\Enums\SystemNotificationKey;
use App\Jobs\SendOperationInventoryLowStockAlert;
use App\Mail\OperationInventoryLowStockMail;
use App\Models\SystemNotificationRecipientSetting;
use App\Support\SystemNotificationRecipients;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

it('programa la alerta de stock bajo diariamente a las 8:00am', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');

    expect($source)
        ->toContain('SendOperationInventoryLowStockAlert')
        ->toContain("Schedule::job(new SendOperationInventoryLowStockAlert, 'system')")
        ->toContain("->dailyAt('8:00')")
        ->toContain('->when($operationInventoryLowStockIsActive)')
        ->toContain('SystemNotificationRecipients::isActive(SystemNotificationKey::OperationInventoryLowStock)');
});

it('el job notifica a destinatarios del centro de notificaciones', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendOperationInventoryLowStockAlert.php');

    expect($source)
        ->toContain('SystemNotificationKey::OperationInventoryLowStock')
        ->toContain('SystemNotificationRecipients::emails')
        ->toContain('SystemNotificationRecipients::phones')
        ->toContain('SendNotificacionWhatsApp::dispatch')
        ->toContain('OperationInventoryLowStockMail')
        ->toContain('OperationInventoryLowStockReporter');

    expect(SystemNotificationKey::OperationInventoryLowStock->label())
        ->toBe('Stock bajo de inventario')
        ->and(SystemNotificationKey::OperationInventoryLowStock->value)
        ->toBe('operation_inventory_low_stock');
});

it('incluye la clave de stock bajo en el centro de notificaciones gestionado', function (): void {
    expect(SystemNotificationKey::managed())
        ->toContain(SystemNotificationKey::OperationInventoryLowStock)
        ->and(SystemNotificationKey::OperationInventoryLowStock->pausesScheduledTask())->toBeTrue();

    if (! Schema::hasTable('system_notification_recipient_settings')) {
        return;
    }

    $setting = SystemNotificationRecipientSetting::for(SystemNotificationKey::OperationInventoryLowStock);

    expect($setting->notification_key)->toBe(SystemNotificationKey::OperationInventoryLowStock)
        ->and(SystemNotificationRecipients::isActive(SystemNotificationKey::OperationInventoryLowStock))->toBeBool();
});

it('el mailable usa la vista de alerta de stock bajo', function (): void {
    $mail = new OperationInventoryLowStockMail([
        'threshold' => 3,
        'generated_at' => '22/07/2026 08:00',
        'products' => [],
    ], 'inventario@example.com');

    expect($mail->envelope()->subject)
        ->toContain('Alerta de stock bajo')
        ->toContain('umbral ≤ 3')
        ->and($mail->envelope()->subject)->not->toContain('Alerta inmediata');

    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/mails/operation-inventory-low-stock.blade.php');

    expect($view)
        ->toContain('Alerta diaria de stock bajo')
        ->toContain('Alerta inmediata de stock bajo')
        ->toContain('Detalle por almacén')
        ->toContain('$threshold');

    expect(class_exists(SendOperationInventoryLowStockAlert::class))->toBeTrue();
});
