<?php

declare(strict_types=1);

use App\Models\Agent;
use App\Models\BirthdayNotification;
use App\Support\BirthdayNotificationRecipientCatalog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

uses(Tests\TestCase::class);

it('resuelve el query de destinatarios segun data_type', function (): void {
    $notification = new BirthdayNotification([
        'data_type' => ['agents'],
        'channels' => ['whatsapp'],
    ]);

    $query = BirthdayNotificationRecipientCatalog::queryFor($notification);
    $config = BirthdayNotificationRecipientCatalog::configForNotification($notification);

    expect($query)->toBeInstanceOf(Builder::class)
        ->and($query->getModel())->toBeInstanceOf(Agent::class)
        ->and($query->toSql())->toContain('tdec')
        ->and($config['name'])->toBe('name')
        ->and($config['email'])->toBe('email')
        ->and($config['phone'])->toBe('phone')
        ->and($config['birth_date'])->toBe('birth_date');
});

it('soporta agentes de viajes y clientes tdev en el catalogo', function (): void {
    $notification = new BirthdayNotification([
        'data_type' => ['travel_agents', 'tdev_clients'],
        'channels' => ['whatsapp'],
    ]);

    $travel = BirthdayNotificationRecipientCatalog::configForNotification($notification, 'travel_agents');
    $tdev = BirthdayNotificationRecipientCatalog::queryFor($notification, 'tdev_clients');

    expect($travel['birth_date'])->toBe('fechaNacimiento')
        ->and($tdev->toSql())->toContain('business_unit_id');
});

it('permite resolver un data_type concreto cuando hay varios destinatarios', function (): void {
    $notification = new BirthdayNotification([
        'data_type' => ['affiliates', 'rrhh_colaboradors'],
        'channels' => ['email'],
    ]);

    $config = BirthdayNotificationRecipientCatalog::configForNotification($notification, 'rrhh_colaboradors');

    expect($config['name'])->toBe('fullName')
        ->and(BirthdayNotificationRecipientCatalog::resolveDataType($notification))->toBe('affiliates');
});

it('filtra destinatarios por dia y mes de cumpleaños', function (): void {
    $query = Agent::query();
    $filtered = BirthdayNotificationRecipientCatalog::applyBirthdayDateFilter(
        $query,
        'birth_date',
        Carbon::parse('2026-07-17'),
    );

    expect($filtered->toSql())->toContain('like')
        ->and($filtered->getBindings())->toContain('17/07/%');
});

it('el relation manager usa el padron de destinatarios', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/BirthdayNotifications/RelationManagers/BirthdayNotificationDeliveriesRelationManager.php');

    expect($src)->toContain('BirthdayNotificationRecipientCatalog::queryFor')
        ->and($src)->toContain('Destinatarios de la notificación')
        ->and($src)->toContain("Filter::make('birthday_date')")
        ->and($src)->toContain('Fecha de cumpleaños')
        ->and($src)->toContain('padronSwitcherActions')
        ->and($src)->toContain('viewerDataType')
        ->and($src)->toContain('toolbarActions')
        ->and($src)->toContain('ActionGroup::make')
        ->and($src)->toContain("->label('Padrón')")
        ->and($src)->toContain('dropdownWidth(Width::ExtraSmall)')
        ->and($src)->toContain('compactPadronLabel')
        ->and($src)->toContain('menú «Padrón» junto al buscador')
        ->and($src)->not->toContain('AboveContentCollapsible')
        ->and($src)->not->toContain('->headerActions($this->padronSwitcherActions');
});
