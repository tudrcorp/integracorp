<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

it('redirige a login si un invitado intenta ver la ficha individual de negocios', function (): void {
    $this->get(route('business.affiliations.ficha.preview', ['affiliation' => 1]))
        ->assertRedirect();
});

it('redirige a login si un invitado intenta descargar la ficha individual de negocios', function (): void {
    $this->get(route('business.affiliations.ficha.download', ['affiliation' => 1]))
        ->assertRedirect();
});

it('redirige a login si un invitado intenta ver la ficha corporativa de negocios', function (): void {
    $this->get(route('business.affiliation-corporates.ficha.preview', ['affiliationCorporate' => 1]))
        ->assertRedirect();
});

it('redirige a login si un invitado intenta descargar la ficha corporativa de negocios', function (): void {
    $this->get(route('business.affiliation-corporates.ficha.download', ['affiliationCorporate' => 1]))
        ->assertRedirect();
});
