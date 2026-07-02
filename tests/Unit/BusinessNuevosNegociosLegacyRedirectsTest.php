<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

it('redirige la ruta legacy de empresas al cluster nuevos negocios', function (): void {
    $this->get('/business/companies')
        ->assertRedirect('/business/nuevos-negocios/companies');
});

it('redirige subrutas legacy de empresas preservando el path', function (): void {
    $this->get('/business/companies/42/edit')
        ->assertRedirect('/business/nuevos-negocios/companies/42/edit');
});

it('redirige la ruta legacy de asociados al cluster nuevos negocios', function (): void {
    $this->get('/business/company-associates?grouping=company_responsible_id')
        ->assertRedirect('/business/nuevos-negocios/company-associates?grouping=company_responsible_id');
});
