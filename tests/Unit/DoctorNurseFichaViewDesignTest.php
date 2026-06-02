<?php

declare(strict_types=1);

use App\Models\DoctorNurse;
use App\Support\PdfCertifiedCheckBadge;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;

uses(Tests\TestCase::class);

it('la plantilla PDF de proveedor natural usa el diseño estandar con cabecera y secciones', function (): void {
    $doctorNurse = new DoctorNurse;
    $doctorNurse->name = 'Proveedor Natural Demo';
    $doctorNurse->razon_social = 'Razon Social Demo';
    $doctorNurse->rif = 'J-12345678-9';
    $doctorNurse->status_convenio = 'ACTIVO';

    $html = View::make('documents.doctor-nurse-ficha', [
        'doctorNurse' => $doctorNurse,
        'logoDataUri' => '',
        'infraCheckBadgeDataUri' => PdfCertifiedCheckBadge::dataUri(),
        'generatedAt' => Carbon::create(2026, 5, 10, 23, 30, 0),
    ])->render();

    expect($html)
        ->toContain('Ficha del proveedor natural')
        ->toContain('Identificación y estructura')
        ->toContain('Contacto y ubicación')
        ->toContain('Condiciones comerciales')
        ->toContain('Notas internas (más recientes primero)');
});
