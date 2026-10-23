<?php

declare(strict_types=1);

use App\Filament\Marketing\Resources\BirthdayNotifications\Pages\ListBirthdayNotifications;
use App\Filament\Marketing\Resources\BirthdayNotifications\Schemas\BirthdayNotificationForm;
use App\Support\BirthdayNotificationAudience;
use Filament\Resources\Pages\ListRecords;

it('agrupa los tipos de destinatario en afiliados y colaboradores', function (): void {
    expect(BirthdayNotificationAudience::dataTypesFor(BirthdayNotificationAudience::AFFILIATES))
        ->toContain('affiliates', 'affiliate_corporates', 'agents', 'rrhh_colaboradors')
        ->and(BirthdayNotificationAudience::dataTypesFor(BirthdayNotificationAudience::COLLABORATORS))
        ->toContain('users', 'suppliers', 'rrhh_colaboradors', 'capemiacs');
});

it('resuelve la audiencia a partir del tipo de destinatario', function (): void {
    expect(BirthdayNotificationAudience::forDataType('affiliates'))
        ->toBe(BirthdayNotificationAudience::AFFILIATES)
        ->and(BirthdayNotificationAudience::forDataType('users'))
        ->toBe(BirthdayNotificationAudience::COLLABORATORS)
        ->and(BirthdayNotificationAudience::forDataType(['affiliates', 'rrhh_colaboradors']))
        ->toBe(BirthdayNotificationAudience::AFFILIATES)
        ->and(BirthdayNotificationAudience::forDataType(['rrhh_colaboradors']))
        ->toBe(BirthdayNotificationAudience::COLLABORATORS);
});

it('expone todos los destinatarios para la tarjeta animada unificada', function (): void {
    expect(BirthdayNotificationAudience::allRecipientDataTypeKeys())
        ->toContain('affiliates', 'affiliate_corporates', 'agents', 'users', 'capemiacs', 'travel_agents', 'tdev_clients')
        ->and(BirthdayNotificationAudience::recipientOptions())
        ->toHaveKeys(['affiliates', 'users', 'suppliers', 'travel_agents', 'tdev_clients'])
        ->and(BirthdayNotificationAudience::celebrationRecipientDataTypeKeys())
        ->toContain('rrhh_colaboradors', 'agents', 'affiliates', 'affiliate_corporates', 'tdev_clients', 'travel_agents');
});

it('normaliza data_type legado string o lista a una lista unica', function (): void {
    expect(BirthdayNotificationAudience::normalizeDataTypes('affiliates'))
        ->toBe(['affiliates'])
        ->and(BirthdayNotificationAudience::normalizeDataTypes(['affiliates', 'agents', 'affiliates']))
        ->toBe(['affiliates', 'agents'])
        ->and(BirthdayNotificationAudience::normalizeDataTypes('["affiliates","rrhh_colaboradors"]'))
        ->toBe(['affiliates', 'rrhh_colaboradors'])
        ->and(BirthdayNotificationAudience::normalizeDataTypes(null))
        ->toBe([]);
});

it('expone etiquetas legibles para varios destinatarios', function (): void {
    expect(BirthdayNotificationAudience::labelsForDataTypes(['affiliates', 'rrhh_colaboradors']))
        ->toBe(['AFILIADOS INDIVIDUALES TDEC', 'COLABORADORES TDG']);
});

it('el formulario de destinatarios permite seleccion multiple', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/BirthdayNotifications/Schemas/BirthdayNotificationForm.php');

    expect($src)->toContain("Select::make('data_type')")
        ->and($src)->toContain('->multiple()')
        ->and($src)->toContain('recipientOptions()');
});

it('lista todas las notificaciones sin pestañas de audiencia', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/BirthdayNotifications/Pages/ListBirthdayNotifications.php');

    expect(is_subclass_of(ListBirthdayNotifications::class, ListRecords::class))->toBeTrue()
        ->and($src)->not->toContain("where('sends_celebration_card', true)")
        ->and($src)->not->toContain('getTabsContentComponent')
        ->and($src)->not->toContain('activeTab')
        ->and(class_exists(BirthdayNotificationForm::class))->toBeTrue();
});

it('el envio diario solo considera notificaciones con tarjeta animada', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Services/NotificationMasiveService.php');

    expect($src)->toContain("where('sends_celebration_card', true)");
});
