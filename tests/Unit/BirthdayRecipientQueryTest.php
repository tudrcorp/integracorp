<?php

declare(strict_types=1);

use App\Support\Birthday\BirthdayRecipientQuery;
use App\Support\BirthdayNotificationAudience;
use Illuminate\Database\Query\Builder;

uses(Tests\TestCase::class);

it('expone los seis padrones de la tarjeta animada', function (): void {
    expect(BirthdayNotificationAudience::celebrationRecipientDataTypeKeys())
        ->toBe([
            'rrhh_colaboradors',
            'agents',
            'affiliates',
            'affiliate_corporates',
            'tdev_clients',
            'travel_agents',
        ])
        ->and(BirthdayNotificationAudience::recipientOptions())
        ->toHaveKey('travel_agents', 'AGENTES DE VIAJES (TDEV)')
        ->toHaveKey('tdev_clients', 'CLIENTES TU DR. EN VIAJES (TDEV)')
        ->toHaveKey('rrhh_colaboradors', 'COLABORADORES TDG');
});

it('filtra agentes de corretaje por bandera tdec o tdev', function (): void {
    $sql = BirthdayRecipientQuery::agents()->toSql();

    expect($sql)->toContain('tdec')
        ->and($sql)->toContain('tdev');
});

it('excluye PQQ de afiliados individuales y corporativos', function (): void {
    $individualSql = BirthdayRecipientQuery::affiliates()->toSql();
    $corporateSql = BirthdayRecipientQuery::affiliateCorporates()->toSql();

    expect($individualSql)->toContain('specific_business_unit')
        ->and($corporateSql)->toContain('specific_business_unit')
        ->and($individualSql)->toContain('affiliates')
        ->and($corporateSql)->toContain('affiliate_corporates');
});

it('arma consultas de agentes de viajes y clientes tdev', function (): void {
    expect(BirthdayRecipientQuery::travelAgents())->toBeInstanceOf(Builder::class)
        ->and(BirthdayRecipientQuery::tdevClientAffiliates('whatsapp')->toSql())
        ->toContain('business_unit_id')
        ->and(BirthdayRecipientQuery::tdevClientAffiliateCorporates('email')->toSql())
        ->toContain('business_unit_id');
});

it('el servicio de cumpleaños usa BirthdayRecipientQuery y los nuevos padrones', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Services/NotificationMasiveService.php');

    expect($src)->toContain('BirthdayRecipientQuery::agents()')
        ->and($src)->toContain('BirthdayRecipientQuery::affiliates()')
        ->and($src)->toContain('BirthdayRecipientQuery::affiliateCorporates()')
        ->and($src)->toContain("in_array('travel_agents'")
        ->and($src)->toContain("in_array('tdev_clients'")
        ->and($src)->toContain('tdevClientsRows');
});
