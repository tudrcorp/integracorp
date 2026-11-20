<?php

declare(strict_types=1);

use App\Enums\ApiPartnerAbility;
use App\Http\Controllers\Api\V1\Partners\NonCoveredMedicationController;
use App\Http\Controllers\Api\V1\Partners\PartnerProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API de aliados (v1)
|--------------------------------------------------------------------------
|
| Fuera del grupo `web`: sin sesión, cookies ni CSRF. Cada ruta pasa por
| `api.partner` (token Bearer, permisos, límite y bitácora), incluida la
| respuesta a rutas inexistentes, así un cliente sin token no puede explorar.
|
*/

/*
| Documentación pública para los aliados: solo texto, sin datos de pacientes ni
| credenciales. Fuera del prefijo del API para no pasar por la autenticación.
*/
Route::view('aliados/api/documentacion', 'api-partners.documentation')
    ->name('api.partners.documentation');

Route::prefix('api/v1/aliados')
    ->name('api.partners.v1.')
    ->group(function (): void {
        Route::get('perfil', [PartnerProfileController::class, 'show'])
            ->middleware('api.partner')
            ->name('profile');

        Route::get('medicamentos-no-cubiertos', [NonCoveredMedicationController::class, 'index'])
            ->middleware('api.partner:'.ApiPartnerAbility::NonCoveredMedicationsRead->value)
            ->name('non-covered-medications.index');

        Route::get('medicamentos-no-cubiertos/{medication}', [NonCoveredMedicationController::class, 'show'])
            ->whereNumber('medication')
            ->middleware('api.partner:'.ApiPartnerAbility::NonCoveredMedicationsRead->value)
            ->name('non-covered-medications.show');

        Route::any('{path}', [PartnerProfileController::class, 'notFound'])
            ->where('path', '.*')
            ->middleware('api.partner')
            ->name('not-found');
    });
