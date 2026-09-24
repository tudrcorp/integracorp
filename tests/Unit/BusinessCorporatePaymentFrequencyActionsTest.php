<?php

declare(strict_types=1);

use App\Support\Filament\BusinessFilamentActionPermissionRegistry;

function paymentFrequencySource(string $relativePath): string
{
    return file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);
}

it('expone cambiar frecuencia por fila y en lote con permiso granular', function (): void {
    $source = paymentFrequencySource('app/Filament/Business/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php');

    expect($source)
        ->toContain("Action::make('change_payment_frequency')")
        ->toContain("BulkAction::make('change_payment_frequency_bulk')")
        ->toContain('self::changePaymentFrequencyAction(),')
        ->toContain('self::changePaymentFrequencyBulkAction(),')
        ->toContain('CorporatePaymentFrequencyChanger::change($record,')
        ->toContain('CorporatePaymentFrequencyChanger::changeMany($records,')
        ->toContain('BusinessFilamentActionPermissionRegistry::CHANGE_CORPORATE_PAYMENT_FREQUENCY')
        ->toContain('CorporatePaymentFrequencyChanger::BLOCKED_AFFILIATION_STATUSES');

    $definition = BusinessFilamentActionPermissionRegistry::all()[BusinessFilamentActionPermissionRegistry::CHANGE_CORPORATE_PAYMENT_FREQUENCY] ?? null;

    expect($definition)->not->toBeNull()
        ->and($definition['modules'])->toBe(['NEGOCIOS']);
});

it('el botón de cargar pago usa la cobranza real en los tres paneles', function (string $path): void {
    expect(paymentFrequencySource($path))
        ->toContain('->hidden(fn (AffiliationCorporate $record): bool => CorporatePaymentUploadAvailability::isFullyPaid($record))')
        ->not->toContain('paid_membership_corporates()->count() == 4');
})->with([
    'negocios' => ['app/Filament/Business/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php'],
    'general' => ['app/Filament/General/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php'],
    'master' => ['app/Filament/Master/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php'],
]);

it('ningún PDF corporativo lee la columna subtotal_semestral que no existe', function (string $view): void {
    $source = paymentFrequencySource('resources/views/documents/'.$view.'.blade.php');

    expect($source)
        ->toContain('CorporateDocumentPlanAmounts::periodAmount(')
        ->not->toContain("['subtotal_semestral']")
        ->not->toContain('->first()->description');
})->with(['aviso-de-cobro-corporativo', 'aviso-de-pago-corporativo', 'factura-corporativa', 'regenerar-aviso-de-pago-corporativo']);

it('el primer pago mensual genera cuotas mes a mes con correlativo de avisos', function (): void {
    $source = paymentFrequencySource('app/Http/Controllers/PaidMembershipCorporateController.php');

    expect($source)
        ->toContain("self::parseDateForStorage((string) \$prox_date)->addMonthNoOverflow()->format('d/m/Y')")
        ->toContain('UtilsController::generateCorrelativeCollection($lastInvoiceNumberCollection);')
        ->not->toContain('generateCorrelativeCollection($lastInvoiceNumber->invoice_number)');
});

it('el registro de cambios vive en Administración, es de solo lectura y revertir exige permiso propio', function (): void {
    $base = 'app/Filament/Administration/Resources/AffiliationCorporatePaymentFrequencyChanges/';
    $resource = paymentFrequencySource($base.'AffiliationCorporatePaymentFrequencyChangeResource.php');
    $view = paymentFrequencySource($base.'Pages/ViewAffiliationCorporatePaymentFrequencyChange.php');

    expect($resource)
        ->toContain("protected static ?string \$navigationLabel = 'Cambios de frecuencia de pago';")
        ->toContain('use AuthorizesDepartmentNavigation;')
        ->toContain("public static function canCreate(): bool\n    {\n        return false;")
        ->toContain("public static function canEdit(Model \$record): bool\n    {\n        return false;")
        ->toContain("public static function canDelete(Model \$record): bool\n    {\n        return false;");

    expect($view)
        ->toContain("Action::make('reverseChange')")
        ->toContain("->color('danger')")
        ->toContain('BusinessFilamentActionPermissionRegistry::REVERSE_CORPORATE_PAYMENT_FREQUENCY_CHANGE')
        ->toContain('Reverser::blockReason($record)')
        ->toContain("Textarea::make('reason')")
        ->toContain("TextInput::make('confirmation')")
        ->toContain("Action::make('validateChange')");

    $definition = BusinessFilamentActionPermissionRegistry::all()[BusinessFilamentActionPermissionRegistry::REVERSE_CORPORATE_PAYMENT_FREQUENCY_CHANGE] ?? null;

    expect($definition)->not->toBeNull()
        ->and($definition['modules'])->toBe(['ADMINISTRACION']);

    expect(App\Support\Filament\DepartmentNavigationPermissionRegistry::slugsFor(
        App\Filament\Administration\Resources\AffiliationCorporatePaymentFrequencyChanges\AffiliationCorporatePaymentFrequencyChangeResource::class
    ))->toBe(['cambios-de-frecuencia-de-pago']);
});

it('el aviso de cambio de frecuencia es configurable en el Centro de notificaciones', function (): void {
    $key = App\Enums\SystemNotificationKey::CorporatePaymentFrequencyChange;

    expect(App\Enums\SystemNotificationKey::managed())->toContain($key)
        ->and($key->label())->toBe('Cambio de frecuencia de pago corporativa')
        ->and($key->pausesScheduledTask())->toBeFalse()
        ->and($key->flowSteps())->toHaveCount(4);
});
