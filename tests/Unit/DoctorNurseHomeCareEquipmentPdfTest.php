<?php

declare(strict_types=1);

use App\Models\DoctorNurse;
use App\Support\PdfCertifiedCheckBadge;
use Illuminate\Support\Facades\View;

uses(Tests\TestCase::class);

it('muestra la certificacion de infraestructura domiciliaria en la ficha PDF', function (): void {
    $doctorNurse = new DoctorNurse;
    $doctorNurse->equip_diag_oximeter = true;
    $doctorNurse->equip_desc_diag_oximeter = 'Equipo calibrado y operativo.';
    $doctorNurse->equip_adv_emergency_bag = true;

    $html = View::make('documents.doctor-nurse-ficha', [
        'doctorNurse' => $doctorNurse,
        'logoDataUri' => '',
        'infraCheckBadgeDataUri' => PdfCertifiedCheckBadge::dataUri(),
        'generatedAt' => now(),
    ])->render();

    expect($html)
        ->toContain('Certificación de infraestructura domiciliaria')
        ->toContain('Instrumental de diagnóstico')
        ->toContain('Oxímetro de pulso')
        ->toContain('Maletín de urgencias')
        ->toContain('Equipo calibrado y operativo.')
        ->not->toContain('Sin descripción registrada.');

    if (extension_loaded('gd') && PdfCertifiedCheckBadge::dataUri() !== '') {
        expect($html)
            ->toContain('infra-cert-badge-img')
            ->toContain('data:image/png;base64,');
    } else {
        expect($html)->toContain('infra-cert-badge-fallback');
    }
});

it('no muestra bloque de descripcion en la ficha PDF cuando el detalle esta vacio', function (): void {
    $doctorNurse = new DoctorNurse;
    $doctorNurse->equip_diag_oximeter = true;
    $doctorNurse->equip_desc_diag_oximeter = '';

    $html = View::make('documents.doctor-nurse-ficha', [
        'doctorNurse' => $doctorNurse,
        'logoDataUri' => '',
        'infraCheckBadgeDataUri' => PdfCertifiedCheckBadge::dataUri(),
        'generatedAt' => now(),
    ])->render();

    expect($html)
        ->toContain('Oxímetro de pulso')
        ->not->toMatch('/<div class="infra-equip-desc">/');
});
