<?php

use Carbon\Carbon;
use App\Models\Fee;
use App\Models\Sale;
use App\Models\Agent;
use App\Models\Guest;
use App\Models\Agency;
use App\Models\Benefit;
use Livewire\Volt\Volt;
use App\Models\AgeRange;
use App\Models\Coverage;
use App\Models\AgentDocument;
use Filament\Facades\Filament;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\CheckAffiliation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\BirthdayNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\DetailIndividualQuote;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\UtilsController;
use App\Mail\NotificationRenewAffiliationMail;
use App\Models\TelemedicinePatientMedications;
use App\Http\Controllers\NotificationController;
use Fidry\CpuCoreCounter\Finder\NullCpuCoreFinder;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::post('/', function () {
    Filament::auth()->logout();
    session()->invalidate();
    session()->regenerateToken();
    return redirect('/');
})->name('internal');
    
Route::post('/external', function () {
    Filament::auth()->logout();
    session()->invalidate();
    session()->regenerateToken();
    return redirect()->to(config('parameters.REDIRECT_LOGOUT_EXTERNAL_URL'));
})->name('external');

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

    NotificationController::notificationImage();
    // NotificationController::notificationVideo();
    dd('listo');

});

Route::get('/truncate', function () {

    // Eliminar todos los registros con id > 3
    DB::table('users')->where('id', '>', 2)->delete();

    // Reiniciar el auto-increment
    DB::statement('ALTER TABLE users AUTO_INCREMENT = 3;');
    
});

Route::get('/rp', function () {

    // $coverages = DetailIndividualQuote::join('coverages', 'detail_individual_quotes.coverage_id', '=', 'coverages.id')
    //     ->join('individual_quotes', 'detail_individual_quotes.individual_quote_id', '=', 'individual_quotes.id')
    //     ->where('individual_quotes.id', 86)
    //     ->where('detail_individual_quotes.plan_id', 2)
    //     ->select('coverages.id as coverage_id', 'coverages.price as description')
    //     ->distinct() // Asegurarse de que no haya duplicados
    //     ->get()
    //     ->pluck('description', 'coverage_id');

    // dd($coverages);

    // Log::error('Job Fallido: ' . static::class, [
    //     'mensaje' => 'hello world',
    //     'archivo' => 'file.php',
    //     'linea' => '123',
    //     'pila' => 'trace',
    // ]);

    // $pdf = Pdf::loadView('documents.informe-medico-general');
    // return $pdf->stream();

    // $medications = TelemedicinePatientMedications::with('telemedicinePatient')->get()->toArray();

    // for ($i = 0; $i < count($medications); $i++) {

    //     //... Fecha de asignacion del tratamiento
    //     $asignationDate = Carbon::parse($medications[$i]['created_at'])->format('Y-m-d');

    //     //... Fecha de Hoy
    //     $today = now()->format('Y-m-d');

    //     //... Dias Trascurridos
    //     $diasTranscurridos = Carbon::parse($asignationDate)->diffInDays($today);

    //     if($diasTranscurridos <= $medications[$i]['duration']) {

    //         $name = $medications[$i]['telemedicine_patient']['full_name'];
    //         $phone = $medications[$i]['telemedicine_patient']['phone'];
    //         $medicine = $medications[$i]['medicine'];
    //         $indications = $medications[$i]['indications'];
    //         $duration = $medications[$i]['duration'];

    //         //... Disparo la notificacion
    //         NotificationController::rememberMedication($name, $phone, $medicine, $indications, $duration);
    //     }

    // }

    $pdf = Pdf::loadView('documents.aviso-de-pago');
    return $pdf->stream();

    // return view ('pr');
    
});

Route::get('/inter', function () {

    $pdf = Pdf::loadView('documents.referencia-especialista');
    return $pdf->stream();

});


Route::get('/lab', function () {

    $pdf = Pdf::loadView('documents.laboratorios');
    return $pdf->stream();


});


Route::get('/imag', function () {

    $pdf = Pdf::loadView('documents.imagenologia');
    return $pdf->stream();


});

Route::get('/largo', function () {

    $dates = DB::table('affiliations')
        ->select('id', 'code', 'agent_id', 'code_agency', 'effective_date')
        ->get()
        ->toArray();
    // dd($dates);
    $today = Carbon::createFromFormat('d/m/Y', now()->format('d/m/Y'))->format('Y-m-d');
    // dd($today);

    for ($i = 0; $i < count($dates); $i++) {

        $effectiveDate = Carbon::createFromFormat('d/m/Y', $dates[$i]->effective_date)->format('Y-m-d');
        // dd($effectiveDate, $today);
        if($effectiveDate == null){
            continue; 
        }
        
        if($effectiveDate > $today){
            //1. Calculo los dias faltantes para lleguar al vencimiento
            $diasFaltantes = Carbon::parse($today)->diffInDays($effectiveDate);
            // dd($diasFaltantes);
            //Faltan 30 dias?
            if($diasFaltantes == 30){
                
                //Si la afiliacion pertenece a un agente
                if($dates[$i]->agent_id != null){
                    //Si pertenece a un agente
                    $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                    Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 30))->onQueue('renew'));
                }
                
                //si la afiliacion pertenece a una agencia
                if ($dates[$i]->agent_id == null) {
                    //Si pertenece a un agente
                    $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                    Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 30))->onQueue('renew'));
                }
                
            }

            //Faltan 20 dias?
            if($diasFaltantes == 20){
                //Si la afiliacion pertenece a un agente
                if ($dates[$i]->agent_id != null) {
                    //Si pertenece a un agente
                    $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                    Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 20))->onQueue('renew'));
                }

                //si la afiliacion pertenece a una agencia
                if ($dates[$i]->agent_id == null) {
                    //Si pertenece a un agente
                    $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                    Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 20))->onQueue('renew'));
                }
            }

            //Faltan 15 dias?
            if ($diasFaltantes == 15) {
                //Si la afiliacion pertenece a un agente
                if ($dates[$i]->agent_id != null) {
                    //Si pertenece a un agente
                    $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                    Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 15))->onQueue('renew'));
                }

                //si la afiliacion pertenece a una agencia
                if ($dates[$i]->agent_id == null) {
                    //Si pertenece a un agente
                    $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                    Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 15))->onQueue('renew'));
                }
            }

            //Faltan 10 dias?
            if($diasFaltantes == 10){
                //Si la afiliacion pertenece a un agente
                if ($dates[$i]->agent_id != null) {
                    //Si pertenece a un agente
                    $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                    Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 10))->onQueue('renew'));
                }

                //si la afiliacion pertenece a una agencia
                if ($dates[$i]->agent_id == null) {
                    //Si pertenece a un agente
                    $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                    Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 10))->onQueue('renew'));
                }
            }

            //Faltan 7 dias?
            if($diasFaltantes == 7){
                //Si la afiliacion pertenece a un agente
                if ($dates[$i]->agent_id != null) {
                    //Si pertenece a un agente
                    $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                    Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 7))->onQueue('renew'));
                }

                //si la afiliacion pertenece a una agencia
                if ($dates[$i]->agent_id == null) {
                    //Si pertenece a un agente
                    $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                    Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 7))->onQueue('renew'));
                }
            }

            //Faltan 5 dias?
            if($diasFaltantes == 5){
                //Si la afiliacion pertenece a un agente
                if ($dates[$i]->agent_id != null) {
                    //Si pertenece a un agente
                    $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                    Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 5))->onQueue('renew'));
                }

                //si la afiliacion pertenece a una agencia
                if ($dates[$i]->agent_id == null) {
                    //Si pertenece a un agente
                    $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                    Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 5))->onQueue('renew'));
                }
            }

            //Faltan 4 dias?
            if($diasFaltantes == 4){
                //Si la afiliacion pertenece a un agente
                if ($dates[$i]->agent_id != null) {
                    //Si pertenece a un agente
                    $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                    Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 4))->onQueue('renew'));
                }

                //si la afiliacion pertenece a una agencia
                if ($dates[$i]->agent_id == null) {
                    //Si pertenece a un agente
                    $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                    Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 4))->onQueue('renew'));
                }
            }

            //Faltan 3 dias?
            if($diasFaltantes == 3){
                //Si la afiliacion pertenece a un agente
                if($dates[$i]->agent_id != null){
                    //Si pertenece a un agente
                    $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                    Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 3))->onQueue('renew'));
                }
                
                //si la afiliacion pertenece a una agencia
                if ($dates[$i]->agent_id == null) {
                    //Si pertenece a un agente
                    $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                    Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 3))->onQueue('renew'));
                }
            }

            //Faltan 2 dias?
            if($diasFaltantes == 2){
                //Si la afiliacion pertenece a un agente
                if($dates[$i]->agent_id != null){
                    //Si pertenece a un agente
                    $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                    Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 2))->onQueue('renew'));
                }
                
                //si la afiliacion pertenece a una agencia
                if ($dates[$i]->agent_id == null) {
                    //Si pertenece a un agente
                    $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                    Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 2))->onQueue('renew'));
                }
            }

            //Faltan 1 dias?
            if($diasFaltantes == 1){
                //Si la afiliacion pertenece a un agente
                if ($dates[$i]->agent_id != null) {
                    //Si pertenece a un agente
                    $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                    Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 1))->onQueue('renew'));
                }

                //si la afiliacion pertenece a una agencia
                if ($dates[$i]->agent_id == null) {
                    //Si pertenece a un agente
                    $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                    Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 1))->onQueue('renew'));
                }
            }

            dd($effectiveDate, $today, $diasFaltantes);

        }
        
        if ($effectiveDate < $today) {
            // dd('es menor');
            //Actualizo el estatus
            DB::table('affiliations')->where('code', $dates[$i]->code)->update([
                'status' => 'VENCIDA-POR-RENOVAR',
            ]);
        }
    }

});