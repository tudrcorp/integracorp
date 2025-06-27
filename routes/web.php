<?php

use Livewire\Volt\Volt;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;
use Illuminate\Support\Facades\Storage;

// Route::get('/', function () {
//     return view('welcome');
// })->name('home');

Route::redirect('/', '/admin');

Route::get('/at/c', function () {
    return view('create-agent');
})->name('agent.create');

Route::get('/w/p', function () {
    return view('welcome-public');
})->name('welcome.public');


Route::get('/ay/c', function () {
    return view('create-agency');
})->name('agency.create');

Route::get('/ay/lk/{code?}', function ($code) {
    return view('create-agent', ['code' => $code]);
})->name('agency.link.create');

Route::get('/at/lk/{code?}', function ($code) {
    return view('create-sub-agent', ['code' => $code]);
})->name('agent.link.create');

/**
 * RUTAS DE PRE-AFILIACION INDIVIDUAL Y CORPORATIVO
 * 
 * @see \App\Http\Livewire\IndividualPreAffiliation
 * @see \App\Http\Livewire\CorporatePreAffiliation
 */
Route::get('/plk/{id}', function ($id) {
    return view('individual-pre-affiliation', [
        'id' => $id
    ]);
})->name('pre-affiliation.create');

Route::get('/plk/c/{id}', function ($id) {
    return view('corporate-pre-affiliation', [
        'id' => $id
    ]);
})->name('corporate-pre-affiliation.create');

// Route::redirect('/', '/admin');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';

/**
 * RUTA PARA PRUEBAS
 */
Route::get('/pp', function () {
    //arraryb de numero telefonicos
    $array = [
        // '+584127018390',
        // '+584129929796',
        // '+584143027250',
        // '+584245718777',
        '+584241764348',
    ];

    $body = <<<HTML

            Queremos darle un sincero agradecimiento por visitar nuestro stand en el *3er Encuentro Internacional de Mujeres de la FUTAC*. 
            Realmente apreciamos la oportunidad de conocerle.

            Estamos atentos a ofrecer *soluciones efectivas* a través de nuestros servicios de Salud paquetizados.

            Si le surge alguna duda acerca de como Tu Dr. En Casa puede beneficiarte, no dudes en contactarnos 📲
            *0424-2220056 / 0424-2271498*

            ¡Estamos a tu disposición!

            Saludos cordiales, 

            HTML;
    
    for ($i = 0; $i < count($array); $i++) {
        $params = array(
            'token' => '9znl3oaurqmxhhbr',
            'to' => $array[$i],
            'image' => 'https://tudrenviajes.com/images/image_masivo.jpg',
            'caption' => $body
        );
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.ultramsg.com/instance117518/messages/image", 
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => array(
                "content-type: application/x-www-form-urlencoded"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            echo $response;
        }
    }

    
    
});

Route::get('/pdf', [PdfController::class, 'generatePdf_aviso_de_pago']);