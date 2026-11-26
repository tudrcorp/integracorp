<?php

declare(strict_types=1);

use App\Enums\ApiPartnerAbility;
use App\Http\Controllers\Api\V1\Partners\FarmadocOrderController;
use App\Http\Controllers\Api\V1\Partners\NonCoveredMedicationController;
use App\Http\Controllers\Api\V1\Partners\PartnerProfileController;
use App\Http\Controllers\Api\V1\Partners\QuoteTariffController;
use App\Support\ApiPartners\FarmadocOrderFeed;
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

        Route::get('pedidos-farmadoc', [FarmadocOrderController::class, 'index'])
            ->middleware('api.partner:'.ApiPartnerAbility::FarmadocOrdersRead->value)
            ->name('farmadoc-orders.index');

        Route::get('pedidos-farmadoc/{orderNumber}', [FarmadocOrderController::class, 'show'])
            ->where('orderNumber', FarmadocOrderFeed::ORDER_NUMBER_PATTERN)
            ->middleware('api.partner:'.ApiPartnerAbility::FarmadocOrdersRead->value)
            ->name('farmadoc-orders.show');

        Route::get('pedidos-farmadoc/{orderNumber}/nota-de-recibido', [FarmadocOrderController::class, 'receiptNote'])
            ->where('orderNumber', FarmadocOrderFeed::ORDER_NUMBER_PATTERN)
            ->middleware('api.partner:'.ApiPartnerAbility::FarmadocOrdersRead->value)
            ->name('farmadoc-orders.receipt-note');

        Route::get('tarifas', [QuoteTariffController::class, 'index'])
            ->middleware('api.partner:'.ApiPartnerAbility::QuoteTariffsRead->value)
            ->name('quote-tariffs.index');

        Route::any('{path}', [PartnerProfileController::class, 'notFound'])
            ->where('path', '.*')
            ->middleware('api.partner')
            ->name('not-found');
    });
