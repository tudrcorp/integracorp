<?php

use Carbon\Carbon;
use App\Models\Fee;
use App\Models\Agent;
use App\Models\Guest;
use App\Models\Agency;
use App\Models\Benefit;
use Livewire\Volt\Volt;
use App\Models\AgeRange;
use App\Models\Coverage;
use App\Models\AgentDocument;
use App\Models\CheckAffiliation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\BirthdayNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\UtilsController;
use App\Http\Controllers\NotificationController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Route::redirect('/', '/admin');

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


Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Volt::route('/agent/c/{code?}', 'agentformcreate')->name('volt.agent.create');
Volt::route('/agency/c/{code?}', 'agencyformcreate')->name('volt.agency.create');
Volt::route('/m/o/c/{code?}', 'agencymasterform')->name('master.organization.create');
Volt::route('/d/c', 'doctorFormCreate')->name('volt.doctor.create');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

/**
 * RUTAS DE VOLT
 * Cotizaciones individuales
 */
Route::prefix('in/{quote?}')
    ->group(function () {
        Volt::route('/w', 'volt.in.home')->name('volt.home');
        Volt::route('/c', 'volt.in.individual_quote')->name('volt.in.individual_quote');
    });

/**
 * RUTAS DE VOLT
 * Cotizaciones individuales
 */
Route::prefix('cor/{quote?}')
    ->group(function () {
        Volt::route('/w', 'volt.cor.home')->name('volt.cor.home');
        Volt::route('/c', 'volt.cor.corporate_quote')->name('volt.cor.corporate_quote');
    });

require __DIR__.'/auth.php';

/**
 * RUTA PARA PRUEBAS
 */
Route::get('/pp', function () {

    $array_doc = [
        'DOCUMENTO DE IDENTIDAD',
        'FIRMA DIGITAL AGENTE',
        'W8/W9',
        'CUENTA USD',
        'CUENTA VES'
    ];


    $agents = DB::table('agents')
        ->select('id', 'email', 'phone', 'status', 'name')
        ->where('status', 'ACTIVO')
        ->get()
        ->toArray();

    for ($i = 0; $i < count($agents); $i++) {
        $array_doc_agent = [];
        $doc = AgentDocument::where('agent_id', $agents[$i]->id)->get();
        foreach ($doc as $key => $value) {
            $array_doc_agent[$key] = $value->title;
        }
        $result = array_diff($array_doc, $array_doc_agent);
        $string = implode(', ', $result);

        dd($agents[$i]);
        
        //Send Notificacion via Whatsapp
        NotificationController::documentUploadReminder($agents[$i]->phone, $agents[$i]->name, $string);
    }


});

Route::get('/pdf', [PdfController::class, 'generatePdf_aviso_de_pago']);

Route::get('/d', function () {

    dd(Benefit::where('plan_id', 1)->get());

    dd(Crypt::encryptString(41));

    $path = public_path('storage/COT-IND-00040.pdf');
    dd($path);
    return response()->download($path);
    
})->name('panel.notification.download.file');

Route::get('/flux/{name}', function ($name) {
    return view('prueba-flux', [
        'name' => $name
    ]);
})->name('flux');

Route::get('/notify', function () {

    $array = Guest::all()->toArray();


    for ($i = 0; $i < count($array); $i++) {

        $body = <<<HTML

            Hola!👋

            Apreciado/a: *{$array[0]['firstName']} {$array[0]['lastName']}*

            Usted ha sido seleccionado para esta misión con Tu Dr. Group.
            Donde la innovación será parte de nuestras lineas de negocios de salud y viajes.

            ¿ACEPTAS LA MISIÓN?🕵🏼 Ingresa nuestro sitio web https://tudrgroup.com
            Y llena el formulario

            Más información sobre este encuentro aquí 👇🏼
            https://wa.me/+584142510805
 
            HTML;

        $params = array(
            'token' => 'yuvh9eq5kn8bt666',
            'to' => $array[0]['phone'],
            'video' => 'https://tudrgroup.com/images/videoEvento1.mp4',
            'caption' => $body
        );
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.ultramsg.com/instance117518/messages/video",
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

        Log::info($response);
        Log::error($err);
        
    }

    curl_close($curl);

    dd($response);
    
});

Route::get('/notification', function () {

    // NotificationController::notificationImage();
    NotificationController::notificationVideo();
    dd('listo');

});

Route::get('/truncate', function () {

    // Eliminar todos los registros con id > 3
    DB::table('users')->where('id', '>', 2)->delete();

    // Reiniciar el auto-increment
    DB::statement('ALTER TABLE users AUTO_INCREMENT = 3;');
    
});

Route::get('/convertir', function () {

    // $fechaNac = CheckAffiliation::all();
    // foreach ($fechaNac as $value) {
    //     $fechaNacStr = Carbon::createFromFormat('d/m/Y', $value->fecha_nacimiento);
    //     $hoy = Carbon::now();
    //     $diferencia = $hoy->diff($fechaNacStr);
    //     $value->edad = $diferencia->y;
    //     $value->save();
    // }
    // dd('listo');

    // $c = Coverage::where('plan_id', 3)->get()->pluck('price', 'id');
    // $e = AgeRange::where('plan_id', 3)->where('id', 5)->with('fees')->get()->toArray();
    // $f = Fee::where('age_range_id', 5)->where('coverage_id', 12)->get()->pluck('price', 'price');
    // $r = AgeRange::where('plan_id', 3)
    //     ->where('id', 5)
    //     ->with('fees')
    //     ->get()
    //     ->toArray();
    // dd($c, $e, $f, $r);

    //---------------------------------------------------------------------------------------------------------

    // $tables = BirthdayNotification::where('status', 'INACTIVA')->get()->toArray();
    // if (count($tables) == 0) {
    //     dd('No hay notificaciones');
    //     return;
    // }
    // $now = now()->format('d/m/Y');

    // // dd($tables);
    // for ($i = 0; $i < count($tables); $i++) {
    //     /**
    //      * Preparamos la data para el envio de la notificacion
    //      * 
    //      * @param $tables
    //      * @param $now
    //      * 
    //      */
    //     $data = DB::table($tables[$i]['data_type'])
    //             ->select('name', 'email', 'phone', 'birthday_date')
    //             ->where('birthday_date', $now)
    //             ->get()
    //             ->toArray();
    //             // dd($data[0]->name);
    //     /**
    //      * Envio de notificacion de cumpleaños
    //      * 
    //      * @param $data
    //      * 
    //      */
    //     for ($j = 0; $j < count($data); $j++) {
    //         NotificationController::notificationBirthday($data[$j], $tables[$i]);
    //     }
    //     dd('listo');
    // }

    //---------------------------------------------------------------------------------------------------------

    // $phones = Agent::all();
    // $phones_agency = Agency::all();

    // foreach ($phones as $value) {
    //     $phone = UtilsController::normalizeVenezuelanPhone($value->phone);
    //     $value->phone = $phone;
    //     $value->save();
    // }

    // foreach ($phones_agency as $value) {
    //     $phone = UtilsController::normalizeVenezuelanPhone($value->phone);
    //     $value->phone = $phone;
    //     $value->save();
    // }
    // dd('listo');
    phpinfo();
    
});