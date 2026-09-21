<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

/**
 * Lo implementa la página Livewire que hospeda el formulario de consulta.
 *
 * Es el reemplazo de la sesión como transporte: el esquema del formulario le
 * pregunta a la página —una por pestaña— en vez de leer claves globales de la
 * sesión del usuario.
 */
interface ProvidesConsultationFormContext
{
    public function consultationFormContext(): ConsultationFormContext;
}
