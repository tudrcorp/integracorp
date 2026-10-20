<?php

declare(strict_types=1);

use App\Filament\Marketing\Resources\BirthdayNotifications\Pages\CreateBirthdayNotification;
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

it('limita las opciones del formulario segun la audiencia activa e incluye colaboradores en afiliados', function (): void {
    $affiliateOptions = BirthdayNotificationAudience::recipientOptionsFor(BirthdayNotificationAudience::AFFILIATES);
    $collaboratorOptions = BirthdayNotificationAudience::recipientOptionsFor(BirthdayNotificationAudience::COLLABORATORS);

    expect(array_keys($affiliateOptions))
        ->toContain('affiliates', 'rrhh_colaboradors')
        ->not->toContain('users')
        ->and(array_keys($collaboratorOptions))
        ->toContain('users', 'rrhh_colaboradors')
        ->not->toContain('affiliates')
        ->and($affiliateOptions['rrhh_colaboradors'])->toBe('COLABORADORES');
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
        ->toBe(['AFILIADOS INDIVIDUALES', 'COLABORADORES']);
});

it('el formulario de destinatarios permite seleccion multiple', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/BirthdayNotifications/Schemas/BirthdayNotificationForm.php');

    expect($src)->toContain("Select::make('data_type')")
        ->and($src)->toContain('->multiple()');
});

it('define pestañas horizontales para separar los listados de cumpleaños', function (): void {
    expect(is_subclass_of(ListBirthdayNotifications::class, ListRecords::class))->toBeTrue()
        ->and(method_exists(ListBirthdayNotifications::class, 'getTabs'))->toBeTrue()
        ->and(method_exists(ListBirthdayNotifications::class, 'getTabsContentComponent'))->toBeTrue()
        ->and(method_exists(ListBirthdayNotifications::class, 'getActiveAudience'))->toBeTrue()
        ->and(property_exists(CreateBirthdayNotification::class, 'audience'))->toBeTrue()
        ->and(class_exists(BirthdayNotificationForm::class))->toBeTrue();
});
