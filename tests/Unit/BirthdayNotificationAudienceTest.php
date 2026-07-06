<?php

declare(strict_types=1);

use App\Filament\Marketing\Resources\BirthdayNotifications\Pages\CreateBirthdayNotification;
use App\Filament\Marketing\Resources\BirthdayNotifications\Pages\ListBirthdayNotifications;
use App\Support\BirthdayNotificationAudience;
use Filament\Resources\Pages\ListRecords;

it('agrupa los tipos de destinatario en afiliados y colaboradores', function (): void {
    expect(BirthdayNotificationAudience::dataTypesFor(BirthdayNotificationAudience::AFFILIATES))
        ->toContain('affiliates', 'affiliate_corporates', 'agents')
        ->and(BirthdayNotificationAudience::dataTypesFor(BirthdayNotificationAudience::COLLABORATORS))
        ->toContain('users', 'suppliers', 'rrhh_colaboradors', 'capemiacs');
});

it('resuelve la audiencia a partir del tipo de destinatario', function (): void {
    expect(BirthdayNotificationAudience::forDataType('affiliates'))
        ->toBe(BirthdayNotificationAudience::AFFILIATES)
        ->and(BirthdayNotificationAudience::forDataType('users'))
        ->toBe(BirthdayNotificationAudience::COLLABORATORS);
});

it('limita las opciones del formulario segun la audiencia activa', function (): void {
    $affiliateOptions = BirthdayNotificationAudience::recipientOptionsFor(BirthdayNotificationAudience::AFFILIATES);
    $collaboratorOptions = BirthdayNotificationAudience::recipientOptionsFor(BirthdayNotificationAudience::COLLABORATORS);

    expect(array_keys($affiliateOptions))
        ->toContain('affiliates')
        ->not->toContain('users')
        ->and(array_keys($collaboratorOptions))
        ->toContain('users')
        ->not->toContain('affiliates');
});

it('define pestañas horizontales para separar los listados de cumpleaños', function (): void {
    expect(is_subclass_of(ListBirthdayNotifications::class, ListRecords::class))->toBeTrue()
        ->and(method_exists(ListBirthdayNotifications::class, 'getTabs'))->toBeTrue()
        ->and(method_exists(ListBirthdayNotifications::class, 'getTabsContentComponent'))->toBeTrue()
        ->and(method_exists(ListBirthdayNotifications::class, 'getActiveAudience'))->toBeTrue()
        ->and(property_exists(CreateBirthdayNotification::class, 'audience'))->toBeTrue();
});
