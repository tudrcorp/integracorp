<?php

use Carbon\Carbon;
use App\Models\Benefit;
use Livewire\Volt\Volt;
use App\Models\AgentDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;
use Illuminate\Support\Facades\Storage;
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
    // ->where(['team' => '[a-zA-Z0-9_-]+'])
    // validación del parámetro
    // ->middleware('shared.team') 
    // opcional: middleware para procesarlo
    ->group(function () {
        Volt::route('/w', 'volt.in.home')->name('volt.home');
        Volt::route('/c', 'volt.in.individual_quote')->name('volt.in.individual_quote');
    });

/**
 * RUTAS DE VOLT
 * Cotizaciones individuales
 */
Route::prefix('cor/{quote?}')
    // ->where(['team' => '[a-zA-Z0-9_-]+'])
    // validación del parámetro
    // ->middleware('shared.team') 
    // opcional: middleware para procesarlo
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