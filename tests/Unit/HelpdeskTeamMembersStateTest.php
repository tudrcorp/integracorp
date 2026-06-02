<?php

declare(strict_types=1);

use App\Casts\HelpdeskTeamMembersCast;
use App\Models\HelpDesk;
use App\Support\HelpdeskTeamMembersState;

it('normaliza team_members desde json string para repeatable entry', function (): void {
    $json = json_encode([
        ['id' => 1, 'name' => 'Ana', 'telefono_corporativo' => '555'],
    ], JSON_THROW_ON_ERROR);

    $members = HelpdeskTeamMembersState::normalize($json);

    expect($members)->toHaveCount(1)
        ->and($members[0]['name'])->toBe('Ana');
});

it('devuelve array vacio cuando team_members no es iterable', function (): void {
    expect(HelpdeskTeamMembersState::normalize('texto plano'))->toBe([])
        ->and(HelpdeskTeamMembersState::normalize(null))->toBe([]);
});

it('HelpDesk model castea team_members como array', function (): void {
    $cast = new HelpdeskTeamMembersCast;
    $model = new HelpDesk;

    $decoded = $cast->get($model, 'team_members', json_encode([
        ['id' => 2, 'name' => 'Luis', 'telefono_corporativo' => null],
    ]), []);

    expect($decoded)->toHaveCount(1)
        ->and($decoded[0]['name'])->toBe('Luis');
});
