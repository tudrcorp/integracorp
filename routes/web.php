<?php

use App\Http\Controllers\AdministrationAgencyReportsExportController;
use App\Http\Controllers\AdministrationAgentReportsExportController;
use App\Http\Controllers\AffiliationBusinessDocumentsController;
use App\Http\Controllers\AffiliationCorporateBusinessDocumentsController;
use App\Http\Controllers\AffiliationCorporateFichaPdfController;
use App\Http\Controllers\AffiliationFichaPdfController;
use App\Http\Controllers\ApiBcvController;
use App\Http\Controllers\Business\CorporateAgendaInvitationResponseController;
use App\Http\Controllers\Business\MarkHelpdeskTicketInProgressController;
use App\Http\Controllers\BusinessAgencyFichaPdfController;
use App\Http\Controllers\BusinessAgentFichaPdfController;
use App\Http\Controllers\BusinessAppointmentsController;
use App\Http\Controllers\BusinessPlanGeneratorPdfController;
use App\Http\Controllers\BusinessTravelAgencyFichaPdfController;
use App\Http\Controllers\DoctorNurseFichaPdfController;
use App\Http\Controllers\FormularioExternoController;
use App\Http\Controllers\HelpdeskAttachmentDownloadController;
use App\Http\Controllers\HelpdeskFlowProcessFileController;
use App\Http\Controllers\HelpdeskVideoTutorialFileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Operations\DoctorNurseDocumentAuditController;
use App\Http\Controllers\Operations\OperationCoordinationClinicDocumentDownloadController;
use App\Http\Controllers\Operations\SupplierDocumentAuditController;
use App\Http\Controllers\OperationServiceOrderPdfController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\PublicChatController;
use App\Http\Controllers\SupplierFichaPdfController;
use App\Http\Controllers\SupplierReportPdfController;
use App\Http\Controllers\TarjetaAfiliacionController;
use App\Http\Controllers\TelemedicineSchemaDocumentationController;
use App\Http\Controllers\TelemedicineSchemaDocumentationTemporaryLinkController;
use App\Http\Controllers\UtilsController;
use App\Mail\NotificationRenewAffiliationMail;
use App\Models\AgentDocument;
use App\Models\Benefit;
use App\Models\BirthdayNotification;
use App\Models\Collection;
use App\Models\Guest;
use App\Support\SecurityAudit;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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

Route::get('/docs/telemedicina/esquema', TelemedicineSchemaDocumentationController::class)
    ->middleware('signed')
    ->name('telemedicine.schema.documentation');

Route::get('/operations/docs/telemedicina/esquema/enlace-temporal', TelemedicineSchemaDocumentationTemporaryLinkController::class)
    ->middleware(['web', 'auth'])
    ->name('telemedicine.schema.documentation.link');

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
        'id' => $id,
    ]);
})->name('pre-affiliation.create');

Route::get('/plk/c/{id}', function ($id) {
    return view('corporate-pre-affiliation', [
        'id' => $id,
    ]);
})->name('corporate-pre-affiliation.create');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('operations/export-suppliers-csv', App\Http\Controllers\SupplierExportCsvController::class)
    ->middleware(['web', 'auth'])
    ->name('operations.suppliers.export-csv');

Route::get('operations/export-doctor-nurses-csv', App\Http\Controllers\DoctorNurseExportCsvController::class)
    ->middleware(['web', 'auth'])
    ->name('operations.doctor-nurses.export-csv');

Route::get('operations/doctor-nurses/{doctorNurse}/ficha/preview', [DoctorNurseFichaPdfController::class, 'preview'])
    ->middleware(['web', 'auth'])
    ->name('operations.doctor-nurses.ficha.preview');

Route::get('operations/doctor-nurses/{doctorNurse}/ficha/download', [DoctorNurseFichaPdfController::class, 'download'])
    ->middleware(['web', 'auth'])
    ->name('operations.doctor-nurses.ficha.download');

Route::get('operations/doctor-nurses/{doctorNurse}/documents/{index}/download', [DoctorNurseDocumentAuditController::class, 'downloadAffiliationDocument'])
    ->middleware(['web', 'auth'])
    ->whereNumber('index')
    ->name('operations.doctor-nurses.documents.download');

Route::get('operations/doctor-nurses/{doctorNurse}/carta-acceptance/preview', [DoctorNurseDocumentAuditController::class, 'previewCartaAcceptance'])
    ->middleware(['web', 'auth'])
    ->name('operations.doctor-nurses.carta-acceptance.preview');

Route::get('operations/doctor-nurses/{doctorNurse}/carta-acceptance/download', [DoctorNurseDocumentAuditController::class, 'downloadCartaAcceptance'])
    ->middleware(['web', 'auth'])
    ->name('operations.doctor-nurses.carta-acceptance.download');

Route::get('business/export-prospect-agents-csv', App\Http\Controllers\ProspectAgentExportCsvController::class)
    ->middleware(['web', 'auth'])
    ->name('business.prospect-agents.export-csv');

Route::get('business/export-travel-agencies-csv', App\Http\Controllers\TravelAgencyExportCsvController::class)
    ->middleware(['web', 'auth'])
    ->name('business.travel-agencies.export-csv');

Route::get('business/export-corporate-quote-requests-csv', App\Http\Controllers\CorporateQuoteRequestExportCsvController::class)
    ->middleware(['web', 'auth'])
    ->name('business.corporate-quote-requests.export-csv');

Route::get('business/export-cities-csv', App\Http\Controllers\CityExportCsvController::class)
    ->middleware(['web', 'auth'])
    ->name('business.cities.export-csv');

Route::get('business/export-corporate-quotes-csv', App\Http\Controllers\CorporateQuoteExportCsvController::class)
    ->middleware(['web', 'auth'])
    ->name('business.corporate-quotes.export-csv');

Route::get('business/export-individual-quotes-csv', App\Http\Controllers\IndividualQuoteExportCsvController::class)
    ->middleware(['web', 'auth'])
    ->name('business.individual-quotes.export-csv');

Route::get('business/export-renovations-csv', App\Http\Controllers\RenovationExportCsvController::class)
    ->middleware(['web', 'auth'])
    ->name('business.renovations.export-csv');

Route::get('business/export-helpdesks-csv', App\Http\Controllers\HelpdeskExportCsvController::class)
    ->middleware(['web', 'auth'])
    ->name('business.helpdesks.export-csv');

Route::get('administration/export-helpdesks-csv', App\Http\Controllers\HelpdeskExportCsvController::class)
    ->middleware(['web', 'auth'])
    ->name('administration.helpdesks.export-csv');

Route::get('administration/export-agencies-csv', App\Http\Controllers\AgencyExportCsvController::class)
    ->middleware(['web', 'auth'])
    ->name('administration.agencies.export-csv');

Route::get('administration/export-agents-csv', App\Http\Controllers\AgentExportCsvController::class)
    ->middleware(['web', 'auth'])
    ->name('administration.agents.export-csv');

Route::get('operations/export-helpdesks-csv', App\Http\Controllers\HelpdeskExportCsvController::class)
    ->middleware(['web', 'auth'])
    ->name('operations.helpdesks.export-csv');

Route::get('operations/export-indicadores-de-desempeno-csv', App\Http\Controllers\IndicadoresDeDesempenoExportCsvController::class)
    ->middleware(['web', 'auth'])
    ->name('operations.indicadores-de-desempeno.export-csv');

Route::get('marketing/export-helpdesks-csv', App\Http\Controllers\HelpdeskExportCsvController::class)
    ->middleware(['web', 'auth'])
    ->name('marketing.helpdesks.export-csv');

Route::get('operations/suppliers/report/preview', [SupplierReportPdfController::class, 'preview'])
    ->middleware(['web', 'auth'])
    ->name('operations.suppliers.report.preview');

Route::get('operations/suppliers/report/download', [SupplierReportPdfController::class, 'download'])
    ->middleware(['web', 'auth'])
    ->name('operations.suppliers.report.download');

Route::get('operations/suppliers/{supplier}/ficha/preview', [SupplierFichaPdfController::class, 'preview'])
    ->middleware(['web', 'auth'])
    ->name('operations.suppliers.ficha.preview');

Route::get('operations/suppliers/{supplier}/ficha/download', [SupplierFichaPdfController::class, 'download'])
    ->middleware(['web', 'auth'])
    ->name('operations.suppliers.ficha.download');

Route::get('operations/suppliers/{supplier}/documents/{index}/download', [SupplierDocumentAuditController::class, 'downloadAffiliationDocument'])
    ->middleware(['web', 'auth'])
    ->whereNumber('index')
    ->name('operations.suppliers.documents.download');

Route::get('operations/suppliers/{supplier}/carta-acceptance/preview', [SupplierDocumentAuditController::class, 'previewCartaAcceptance'])
    ->middleware(['web', 'auth'])
    ->name('operations.suppliers.carta-acceptance.preview');

Route::get('operations/suppliers/{supplier}/carta-acceptance/download', [SupplierDocumentAuditController::class, 'downloadCartaAcceptance'])
    ->middleware(['web', 'auth'])
    ->name('operations.suppliers.carta-acceptance.download');

Route::get('operations/operation-service-orders/{operationServiceOrder}/pdf/preview', [OperationServiceOrderPdfController::class, 'preview'])
    ->middleware(['web', 'auth'])
    ->name('operations.operation-service-orders.pdf.preview');

Route::get('operations/operation-service-orders/{operationServiceOrder}/pdf/download', [OperationServiceOrderPdfController::class, 'download'])
    ->middleware(['web', 'auth'])
    ->name('operations.operation-service-orders.pdf');

Route::get('operations/coordination/clinic-documents/{document}/download', [OperationCoordinationClinicDocumentDownloadController::class, 'download'])
    ->middleware(['web', 'auth'])
    ->name('operations.coordination.clinic-documents.download');

Route::get('business/agents/{agent}/ficha-pdf/preview', [BusinessAgentFichaPdfController::class, 'preview'])
    ->middleware(['web', 'auth'])
    ->name('business.agents.ficha-pdf.preview');

Route::get('business/agents/{agent}/ficha-pdf/download', [BusinessAgentFichaPdfController::class, 'download'])
    ->middleware(['web', 'auth'])
    ->name('business.agents.ficha-pdf.download');

Route::get('business/agencies/{agency}/ficha-pdf/preview', [BusinessAgencyFichaPdfController::class, 'preview'])
    ->middleware(['web', 'auth'])
    ->name('business.agencies.ficha-pdf.preview');

Route::get('business/agencies/{agency}/ficha-pdf/download', [BusinessAgencyFichaPdfController::class, 'download'])
    ->middleware(['web', 'auth'])
    ->name('business.agencies.ficha-pdf.download');

Route::get('business/travel-agencies/{travelAgency}/ficha-pdf/preview', [BusinessTravelAgencyFichaPdfController::class, 'preview'])
    ->middleware(['web', 'auth'])
    ->name('business.travel-agencies.ficha-pdf.preview');

Route::get('business/travel-agencies/{travelAgency}/ficha-pdf/download', [BusinessTravelAgencyFichaPdfController::class, 'download'])
    ->middleware(['web', 'auth'])
    ->name('business.travel-agencies.ficha-pdf.download');

Route::get('business/plan-generators/{planGenerator}/pdf/preview', [BusinessPlanGeneratorPdfController::class, 'preview'])
    ->middleware(['web', 'auth'])
    ->name('business.plan-generators.pdf.preview');

Route::get('business/plan-generators/{planGenerator}/pdf/download', [BusinessPlanGeneratorPdfController::class, 'download'])
    ->middleware(['web', 'auth'])
    ->name('business.plan-generators.pdf.download');

Route::get('administration/affiliation-corporates/{affiliationCorporate}/ficha/preview', [AffiliationCorporateFichaPdfController::class, 'preview'])
    ->middleware(['web', 'auth'])
    ->name('administration.affiliation-corporates.ficha.preview');

Route::get('administration/affiliation-corporates/{affiliationCorporate}/ficha/download', [AffiliationCorporateFichaPdfController::class, 'download'])
    ->middleware(['web', 'auth'])
    ->name('administration.affiliation-corporates.ficha.download');

Route::get('administration/affiliations/{affiliation}/ficha/preview', [AffiliationFichaPdfController::class, 'preview'])
    ->middleware(['web', 'auth'])
    ->name('administration.affiliations.ficha.preview');

Route::get('administration/affiliations/{affiliation}/ficha/download', [AffiliationFichaPdfController::class, 'download'])
    ->middleware(['web', 'auth'])
    ->name('administration.affiliations.ficha.download');

Route::get('administration/agencies/reports/export', AdministrationAgencyReportsExportController::class)
    ->middleware(['web', 'auth'])
    ->name('administration.agencies.reports.export');

Route::get('administration/agents/reports/export', AdministrationAgentReportsExportController::class)
    ->middleware(['web', 'auth'])
    ->name('administration.agents.reports.export');

Route::get('business/dress-tylor-quotes/{record}/pdf', function (string $record) {
    $isPreview = request()->boolean('preview');
    $auditRoute = $isPreview
        ? 'business.dress-tylor-quotes.pdf.preview'
        : 'business.dress-tylor-quotes.pdf.download';

    try {
        $quote = \App\Models\DressTylorQuote::findOrFail($record);
        $structure = $quote->quote_structure;
        if (empty($structure)) {
            abort(404, 'Esta cotización no tiene estructura guardada para generar el PDF.');
        }

        SecurityAudit::log(
            $isPreview
                ? 'AUDIT_BUSINESS_DRESS_TYLOR_QUOTE_PDF_VIEWED'
                : 'AUDIT_BUSINESS_DRESS_TYLOR_QUOTE_PDF_DOWNLOADED',
            $auditRoute,
            [
                'panel' => 'business',
                'dress_tylor_quote_id' => $quote->id,
                'status' => $quote->status,
                'email' => $quote->email,
                'requested_record' => $record,
            ]
        );

        if ($isPreview) {
            return \App\Filament\Business\Resources\DressTylorQuotes\Schemas\DressTylorQuoteForm::generateInlinePdfFromQuoteStructure($structure);
        }

        return \App\Filament\Business\Resources\DressTylorQuotes\Schemas\DressTylorQuoteForm::generatePdfFromQuoteStructure($structure);
    } catch (\Throwable $exception) {
        SecurityAudit::log(
            'AUDIT_BUSINESS_DRESS_TYLOR_QUOTE_PDF_FAILED',
            $auditRoute,
            [
                'panel' => 'business',
                'requested_record' => $record,
                'is_preview' => $isPreview,
                'reason' => $exception->getMessage(),
            ]
        );

        throw $exception;
    }
})
    ->middleware(['web', 'auth'])
    ->name('business.dress-tylor-quotes.pdf');

Route::get('administration/aviso-cobro/download/{collection}', function (Collection $collection) {
    return \App\Http\Controllers\CollectionController::generateAndDownloadAvisoDeCobro($collection);
})
    ->middleware(['web', 'auth'])
    ->name('aviso-cobro.download');

Route::get('administration/aviso-cobro/regenerate/{collection}', function (Collection $collection) {
    $ok = \App\Filament\Administration\Resources\AnnualCollections\Tables\AnnualCollectionsTable::runRegeneratePdf($collection);

    return redirect()->back()->with($ok ? 'success' : 'error', $ok ? 'Aviso de cobro regenerado.' : 'Error al regenerar.');
})
    ->middleware(['web', 'auth'])
    ->name('aviso-cobro.regenerate');

Route::post('administration/aviso-cobro/regenerate-async/{collection}', [
    \App\Http\Controllers\AvisoCobroController::class,
    'regenerateAsync',
])
    ->middleware(['web', 'auth'])
    ->name('aviso-cobro.regenerate-async');

Route::post('administration/aviso-cobro/send-email/{collection}', [
    \App\Http\Controllers\AvisoCobroController::class,
    'sendEmail',
])
    ->middleware(['web', 'auth'])
    ->name('aviso-cobro.send-email');

Route::post('administration/sales/{sale}/recibo-pago/regenerate-async', [
    \App\Http\Controllers\ReciboPagoController::class,
    'regenerateAsync',
])
    ->middleware(['web', 'auth'])
    ->name('administration.sales.recibo-pago.regenerate-async');

Route::post('business/affiliations/documents/regenerate-async/{affiliation}', [
    AffiliationBusinessDocumentsController::class,
    'regenerateAsync',
])
    ->middleware(['web', 'auth'])
    ->name('business.affiliation-documents.regenerate-async');

Route::post('business/affiliations/documents/send-email/{affiliation}', [
    AffiliationBusinessDocumentsController::class,
    'sendEmail',
])
    ->middleware(['web', 'auth'])
    ->name('business.affiliation-documents.send-email');

Route::post('business/affiliations/tarjeta-qr/associate-plan', [
    TarjetaAfiliacionController::class,
    'associatePlanQr',
])
    ->middleware(['web', 'auth'])
    ->name('business.affiliation-tarjeta-qr.associate-plan');

Route::post('business/affiliation-corporates/tarjeta-qr/associate-plan', [
    TarjetaAfiliacionController::class,
    'associateCorporatePlanQr',
])
    ->middleware(['web', 'auth'])
    ->name('business.affiliation-corporate-tarjeta-qr.associate-plan');

Route::post('business/affiliation-corporates/documents/regenerate-async/{affiliationCorporate}', [
    AffiliationCorporateBusinessDocumentsController::class,
    'regenerateAsync',
])
    ->middleware(['web', 'auth'])
    ->name('business.affiliation-corporate-documents.regenerate-async');

Route::get('business/affiliation-corporates/documents/status/{affiliationCorporate}/{taskId}', [
    AffiliationCorporateBusinessDocumentsController::class,
    'status',
])
    ->middleware(['web', 'auth'])
    ->name('business.affiliation-corporate-documents.status');

Route::post('business/affiliation-corporates/documents/send-email/{affiliationCorporate}', [
    AffiliationCorporateBusinessDocumentsController::class,
    'sendEmail',
])
    ->middleware(['web', 'auth'])
    ->name('business.affiliation-corporate-documents.send-email');

Route::post('business/helpdesk-tickets/{helpDesk}/mark-in-progress', MarkHelpdeskTicketInProgressController::class)
    ->middleware(['web', 'auth'])
    ->name('business.helpdesk-ticket.mark-in-progress');

Route::get('helpdesks/{helpDesk}/attachments/{index}/download', HelpdeskAttachmentDownloadController::class)
    ->middleware(['web', 'auth'])
    ->whereNumber('index')
    ->name('helpdesks.attachments.download');

Route::get('helpdesks/flow-process-files/{helpdeskFlowProcessFile}/download', [HelpdeskFlowProcessFileController::class, 'download'])
    ->middleware(['web', 'auth'])
    ->name('helpdesks.flow-process-files.download');

Route::get('helpdesks/video-tutorial-files/{helpdeskVideoTutorialFile}/download', [HelpdeskVideoTutorialFileController::class, 'download'])
    ->middleware(['web', 'auth'])
    ->name('helpdesks.video-tutorial-files.download');

Route::get('agenda/invitacion/{participant}', [CorporateAgendaInvitationResponseController::class, 'show'])
    ->middleware(['web', 'signed'])
    ->name('agenda.invitation.show');

Route::post('agenda/invitacion/{participant}/responder', [CorporateAgendaInvitationResponseController::class, 'respond'])
    ->middleware(['web', 'signed'])
    ->name('agenda.invitation.respond');

Volt::route('/agent/c/{code?}', 'agentformcreate')->name('volt.agent.create');
Volt::route('/agency/c/{code?}/{type?}', 'agencyformcreate')->name('volt.agency.create');
Volt::route('/m/o/c/{code?}', 'agencymasterform')->name('master.organization.create');
Volt::route('/d/c', 'doctorFormCreate')->name('volt.doctor.create');

// Chat público guiado (UI): página Volt/Livewire en /chat/publico.
// La interfaz usa AgentOrchestrator directamente; no consume la API HTTP /api/public-chat/*.
Volt::route('/chat/publico', 'volt.public.ai_chat')->name('volt.public.ai_chat');

Route::redirect('/guia-chat', '/chat/publico', 301)->name('guia-chat');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

/**
 * RUTAS DE VOLT
 * Cotizaciones Interactivas Individuales
 */
Route::prefix('in/{quote?}')
    ->group(function () {
        Volt::route('/w', 'volt.in.home')->name('volt.home');
        Volt::route('/c', 'volt.in.individual_quote')->name('volt.in.individual_quote');
    });

/**
 * RUTAS DE VOLT
 * Cotizaciones Interactivas Corporativas
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
        'CUENTA VES',
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

        // Send Notificacion via Whatsapp
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
        'name' => $name,
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

        $params = [
            'token' => 'yuvh9eq5kn8bt666',
            'to' => $array[0]['phone'],
            'video' => 'https://tudrgroup.com/images/videoEvento1.mp4',
            'caption' => $body,
        ];
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.ultramsg.com/instance117518/messages/video',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => [
                'content-type: application/x-www-form-urlencoded',
            ],
        ]);

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

    $pdf = Pdf::loadView('pr');

    // return view('pr');
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

Route::get('/tarjeta', function () {
    $data = TarjetaAfiliacionController::prepareDataForTarjetaPdfView([
        'name' => 'NOMBRE EJEMPLO APELLIDO',
        'ci' => 'V-12345678',
        'code' => 'DEMO-1',
        'plan' => 'PLAN INICIAL',
        'frecuencia' => 'ANUAL',
        'cobertura' => '15304.07',
        'desde' => '01/01/2025',
        'hasta' => '01/01/2026',
    ]);
    $pdf = Pdf::loadView('documents.tarjeta-afiliado', compact('data'));

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
        if ($effectiveDate == null) {
            continue;
        }

        if ($effectiveDate > $today) {
            // 1. Calculo los dias faltantes para lleguar al vencimiento
            $diasFaltantes = Carbon::parse($today)->diffInDays($effectiveDate);
            // dd($diasFaltantes);
            // Faltan 30 dias?
            if ($diasFaltantes == 30) {

                // Si la afiliacion pertenece a un agente
                if ($dates[$i]->agent_id != null) {
                    // Si pertenece a un agente
                    $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                    Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 30))->onQueue('renew'));
                }

                // si la afiliacion pertenece a una agencia
                if ($dates[$i]->agent_id == null) {
                    // Si pertenece a un agente
                    $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                    Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 30))->onQueue('renew'));
                }
            }

            // Faltan 20 dias?
            if ($diasFaltantes == 20) {
                // Si la afiliacion pertenece a un agente
                if ($dates[$i]->agent_id != null) {
                    // Si pertenece a un agente
                    $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                    Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 20))->onQueue('renew'));
                }

                // si la afiliacion pertenece a una agencia
                if ($dates[$i]->agent_id == null) {
                    // Si pertenece a un agente
                    $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                    Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 20))->onQueue('renew'));
                }
            }

            // Faltan 15 dias?
            if ($diasFaltantes == 15) {
                // Si la afiliacion pertenece a un agente
                if ($dates[$i]->agent_id != null) {
                    // Si pertenece a un agente
                    $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                    Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 15))->onQueue('renew'));
                }

                // si la afiliacion pertenece a una agencia
                if ($dates[$i]->agent_id == null) {
                    // Si pertenece a un agente
                    $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                    Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 15))->onQueue('renew'));
                }
            }

            // Faltan 10 dias?
            if ($diasFaltantes == 10) {
                // Si la afiliacion pertenece a un agente
                if ($dates[$i]->agent_id != null) {
                    // Si pertenece a un agente
                    $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                    Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 10))->onQueue('renew'));
                }

                // si la afiliacion pertenece a una agencia
                if ($dates[$i]->agent_id == null) {
                    // Si pertenece a un agente
                    $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                    Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 10))->onQueue('renew'));
                }
            }

            // Faltan 7 dias?
            if ($diasFaltantes == 7) {
                // Si la afiliacion pertenece a un agente
                if ($dates[$i]->agent_id != null) {
                    // Si pertenece a un agente
                    $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                    Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 7))->onQueue('renew'));
                }

                // si la afiliacion pertenece a una agencia
                if ($dates[$i]->agent_id == null) {
                    // Si pertenece a un agente
                    $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                    Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 7))->onQueue('renew'));
                }
            }

            // Faltan 5 dias?
            if ($diasFaltantes == 5) {
                // Si la afiliacion pertenece a un agente
                if ($dates[$i]->agent_id != null) {
                    // Si pertenece a un agente
                    $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                    Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 5))->onQueue('renew'));
                }

                // si la afiliacion pertenece a una agencia
                if ($dates[$i]->agent_id == null) {
                    // Si pertenece a un agente
                    $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                    Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 5))->onQueue('renew'));
                }
            }

            // Faltan 4 dias?
            if ($diasFaltantes == 4) {
                // Si la afiliacion pertenece a un agente
                if ($dates[$i]->agent_id != null) {
                    // Si pertenece a un agente
                    $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                    Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 4))->onQueue('renew'));
                }

                // si la afiliacion pertenece a una agencia
                if ($dates[$i]->agent_id == null) {
                    // Si pertenece a un agente
                    $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                    Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 4))->onQueue('renew'));
                }
            }

            // Faltan 3 dias?
            if ($diasFaltantes == 3) {
                // Si la afiliacion pertenece a un agente
                if ($dates[$i]->agent_id != null) {
                    // Si pertenece a un agente
                    $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                    Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 3))->onQueue('renew'));
                }

                // si la afiliacion pertenece a una agencia
                if ($dates[$i]->agent_id == null) {
                    // Si pertenece a un agente
                    $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                    Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 3))->onQueue('renew'));
                }
            }

            // Faltan 2 dias?
            if ($diasFaltantes == 2) {
                // Si la afiliacion pertenece a un agente
                if ($dates[$i]->agent_id != null) {
                    // Si pertenece a un agente
                    $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                    Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 2))->onQueue('renew'));
                }

                // si la afiliacion pertenece a una agencia
                if ($dates[$i]->agent_id == null) {
                    // Si pertenece a un agente
                    $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                    Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 2))->onQueue('renew'));
                }
            }

            // Faltan 1 dias?
            if ($diasFaltantes == 1) {
                // Si la afiliacion pertenece a un agente
                if ($dates[$i]->agent_id != null) {
                    // Si pertenece a un agente
                    $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                    Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 1))->onQueue('renew'));
                }

                // si la afiliacion pertenece a una agencia
                if ($dates[$i]->agent_id == null) {
                    // Si pertenece a un agente
                    $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                    Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 1))->onQueue('renew'));
                }
            }

            dd($effectiveDate, $today, $diasFaltantes);
        }

        if ($effectiveDate < $today) {
            // dd('es menor');
            // Actualizo el estatus
            DB::table('affiliations')->where('code', $dates[$i]->code)->update([
                'status' => 'VENCIDA-POR-RENOVAR',
            ]);
        }
    }
});

Route::get('/generar-qr', function () {
    // 1. URL que queremos codificar en el QR
    $url = 'https://tudrgroup.com';

    // 2. Generar la imagen QR en formato SVG
    // Usamos el método 'size' para definir el tamaño de la imagen (ej: 300px)
    // El método 'generate' crea la imagen SVG del código QR
    $qrCode = QrCode::size(300)->generate($url);

    // 3. Pasar el código QR (formato SVG) a la vista
    return view('qr_display', compact('qrCode', 'url'));
});

Route::view('/crear-qr', 'qr_creator')
    ->name('qr.creator');

Route::get('/r4/banesco', function () {

    $cuenta = '01340338463381064391';
    $commerceToken = '0952d954b485debb4df0f2e9e70f03382d2c849e01bc9aab29ab61c9ff3f70b3';
    $url = 'https://r4conecta.mibanco.com.ve/TransferenciaOnline/DomiciliacionCNTA';
    $tokenAuthorization = hash_hmac('sha256', $cuenta, $commerceToken);

    $headers = [
        'Content-Type: application/json',
        'Authorization: '.$tokenAuthorization,
        'Commerce: '.$commerceToken,
    ];

    $postData = [
        'docId' => 'V25798531',
        'nombre' => 'Humberto Sanchez',
        'cuenta' => '01340338463381064391',
        'monto' => '100.00',
        'concepto' => 'Pago',
    ];

    $curl = curl_init($url);

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_SSL_VERIFYPEER => true, // Verificar el certificado del servidor
        CURLOPT_SSL_VERIFYHOST => 2,    // Verificar el hostname del certificado
    ]);

    $response = curl_exec($curl);

    if (curl_errno($curl)) {
        throw new \Exception('Error en cURL: '.curl_error($curl));
    }

    // Convierto el JSON to Array
    $result = json_decode($response, true);

    if ($result === null) {
        throw new \Exception('Respuesta de la API inválida');
    }

    curl_close($curl);

    // escribo el response en la tabla de log
    // LogTransactionalR4Controller::response($result['code'], $result['message'], isset($result['uuid']) ? $result['uuid'] : null);

    // Logging de la respuesta de la API
    Log::info($cuenta);
    Log::info($commerceToken);
    Log::info($url);
    Log::info($tokenAuthorization);
    Log::info($headers);
    Log::info(json_encode($postData));

    Log::info($result);

    Log::info($result['codigo']);

    if ($result['codigo'] == '202') {

        Log::info($result['codigo']);

        $uuid = $result['uuid'];
        $url = 'https://r4conecta.mibanco.com.ve/ConsultarOperaciones';

        $tokenAuthorization = hash_hmac('sha256', $uuid, $commerceToken);

        $headers = [
            'Content-Type: application/json',
            'Authorization: '.$tokenAuthorization,
            'Commerce: '.$commerceToken,
        ];

        $id = [
            'id' => $uuid,
        ];

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($id),
            CURLOPT_SSL_VERIFYPEER => true, // Verificar el certificado del servidor
            CURLOPT_SSL_VERIFYHOST => 2,    // Verificar el hostname del certificado
        ]);

        $responseOperacion = curl_exec($curl);

        if (curl_errno($curl)) {
            throw new \Exception('Error en cURL: '.curl_error($curl));
        }

        $resultOperacion = json_decode($responseOperacion, true);

        if ($result === null) {
            throw new \Exception('Respuesta de la API inválida');
        }

        curl_close($curl);

        Log::info($resultOperacion);
    }
});

Route::get('/r4/vzla', function () {

    $cuenta = '01020234530000310965';
    $commerceToken = '0952d954b485debb4df0f2e9e70f03382d2c849e01bc9aab29ab61c9ff3f70b3';
    $url = 'https://r4conecta.mibanco.com.ve/TransferenciaOnline/DomiciliacionCNTA';
    $tokenAuthorization = hash_hmac('sha256', $cuenta, $commerceToken);

    $headers = [
        'Content-Type: application/json',
        'Authorization: '.$tokenAuthorization,
        'Commerce: '.$commerceToken,
    ];

    $postData = [
        'docId' => 'V25798531',
        'nombre' => 'Humberto Sanchez',
        'cuenta' => '01020234530000310965',
        'monto' => '100.00',
        'concepto' => 'Pago',
    ];

    $curl = curl_init($url);

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_SSL_VERIFYPEER => true, // Verificar el certificado del servidor
        CURLOPT_SSL_VERIFYHOST => 2,    // Verificar el hostname del certificado
    ]);

    $response = curl_exec($curl);

    if (curl_errno($curl)) {
        throw new \Exception('Error en cURL: '.curl_error($curl));
    }

    // Convierto el JSON to Array
    $result = json_decode($response, true);

    if ($result === null) {
        throw new \Exception('Respuesta de la API inválida');
    }

    curl_close($curl);

    // escribo el response en la tabla de log
    // LogTransactionalR4Controller::response($result['code'], $result['message'], isset($result['uuid']) ? $result['uuid'] : null);

    // Logging de la respuesta de la API
    Log::info($cuenta);
    Log::info($commerceToken);
    Log::info($url);
    Log::info($tokenAuthorization);
    Log::info($headers);
    Log::info(json_encode($postData));

    Log::info($result);

    Log::info($result['codigo']);

    if ($result['codigo'] == '202') {

        Log::info($result['codigo']);

        $uuid = $result['uuid'];
        $url = 'https://r4conecta.mibanco.com.ve/ConsultarOperaciones';

        $tokenAuthorization = hash_hmac('sha256', $uuid, $commerceToken);

        $headers = [
            'Content-Type: application/json',
            'Authorization: '.$tokenAuthorization,
            'Commerce: '.$commerceToken,
        ];

        $id = [
            'id' => $uuid,
        ];

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($id),
            CURLOPT_SSL_VERIFYPEER => true, // Verificar el certificado del servidor
            CURLOPT_SSL_VERIFYHOST => 2,    // Verificar el hostname del certificado
        ]);

        $responseOperacion = curl_exec($curl);

        if (curl_errno($curl)) {
            throw new \Exception('Error en cURL: '.curl_error($curl));
        }

        $resultOperacion = json_decode($responseOperacion, true);

        if ($result === null) {
            throw new \Exception('Respuesta de la API inválida');
        }

        curl_close($curl);

        Log::info($resultOperacion);
    }
});

Route::get('/r4/bnc', function () {

    $cuenta = '01910241672100021488';
    $commerceToken = '0952d954b485debb4df0f2e9e70f03382d2c849e01bc9aab29ab61c9ff3f70b3';
    $url = 'https://r4conecta.mibanco.com.ve/TransferenciaOnline/DomiciliacionCNTA';
    $tokenAuthorization = hash_hmac('sha256', $cuenta, $commerceToken);

    $headers = [
        'Content-Type: application/json',
        'Authorization: '.$tokenAuthorization,
        'Commerce: '.$commerceToken,
    ];

    $postData = [
        'docId' => 'V25798531',
        'nombre' => 'Humberto Sanchez',
        'cuenta' => '01910241672100021488',
        'monto' => '100.00',
        'concepto' => 'Pago',
    ];

    $curl = curl_init($url);

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_SSL_VERIFYPEER => true, // Verificar el certificado del servidor
        CURLOPT_SSL_VERIFYHOST => 2,    // Verificar el hostname del certificado
    ]);

    $response = curl_exec($curl);

    if (curl_errno($curl)) {
        throw new \Exception('Error en cURL: '.curl_error($curl));
    }

    // Convierto el JSON to Array
    $result = json_decode($response, true);

    if ($result === null) {
        throw new \Exception('Respuesta de la API inválida');
    }

    curl_close($curl);

    // escribo el response en la tabla de log
    // LogTransactionalR4Controller::response($result['code'], $result['message'], isset($result['uuid']) ? $result['uuid'] : null);

    // Logging de la respuesta de la API
    Log::info($cuenta);
    Log::info($commerceToken);
    Log::info($url);
    Log::info($tokenAuthorization);
    Log::info($headers);
    Log::info(json_encode($postData));

    Log::info($result);

    Log::info($result['codigo']);

    if ($result['codigo'] == '202') {

        Log::info($result['codigo']);

        $uuid = $result['uuid'];
        $url = 'https://r4conecta.mibanco.com.ve/ConsultarOperaciones';

        $tokenAuthorization = hash_hmac('sha256', $uuid, $commerceToken);

        $headers = [
            'Content-Type: application/json',
            'Authorization: '.$tokenAuthorization,
            'Commerce: '.$commerceToken,
        ];

        $id = [
            'id' => $uuid,
        ];

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($id),
            CURLOPT_SSL_VERIFYPEER => true, // Verificar el certificado del servidor
            CURLOPT_SSL_VERIFYHOST => 2,    // Verificar el hostname del certificado
        ]);

        $responseOperacion = curl_exec($curl);

        if (curl_errno($curl)) {
            throw new \Exception('Error en cURL: '.curl_error($curl));
        }

        $resultOperacion = json_decode($responseOperacion, true);

        if ($result === null) {
            throw new \Exception('Respuesta de la API inválida');
        }

        curl_close($curl);

        Log::info($resultOperacion);
    }
});

Route::get('/r4/mercantil', function () {

    $cuenta = '01050049451049444078';
    $commerceToken = '0952d954b485debb4df0f2e9e70f03382d2c849e01bc9aab29ab61c9ff3f70b3';
    $url = 'https://r4conecta.mibanco.com.ve/TransferenciaOnline/DomiciliacionCNTA';
    $tokenAuthorization = hash_hmac('sha256', $cuenta, $commerceToken);

    $headers = [
        'Content-Type: application/json',
        'Authorization: '.$tokenAuthorization,
        'Commerce: '.$commerceToken,
    ];

    $postData = [
        'docId' => 'V25798531',
        'nombre' => 'Humberto Sanchez',
        'cuenta' => '01050049451049444078',
        'monto' => '100.00',
        'concepto' => 'Pago',
    ];

    $curl = curl_init($url);

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_SSL_VERIFYPEER => true, // Verificar el certificado del servidor
        CURLOPT_SSL_VERIFYHOST => 2,    // Verificar el hostname del certificado
    ]);

    $response = curl_exec($curl);

    if (curl_errno($curl)) {
        throw new \Exception('Error en cURL: '.curl_error($curl));
    }

    // Convierto el JSON to Array
    $result = json_decode($response, true);

    if ($result === null) {
        throw new \Exception('Respuesta de la API inválida');
    }

    curl_close($curl);

    // escribo el response en la tabla de log
    // LogTransactionalR4Controller::response($result['code'], $result['message'], isset($result['uuid']) ? $result['uuid'] : null);

    // Logging de la respuesta de la API
    Log::info($cuenta);
    Log::info($commerceToken);
    Log::info($url);
    Log::info($tokenAuthorization);
    Log::info($headers);
    Log::info(json_encode($postData));

    Log::info($result);

    Log::info($result['codigo']);

    if ($result['codigo'] == '202') {

        Log::info($result['codigo']);

        $uuid = $result['uuid'];
        $url = 'https://r4conecta.mibanco.com.ve/ConsultarOperaciones';

        $tokenAuthorization = hash_hmac('sha256', $uuid, $commerceToken);

        $headers = [
            'Content-Type: application/json',
            'Authorization: '.$tokenAuthorization,
            'Commerce: '.$commerceToken,
        ];

        $id = [
            'id' => $uuid,
        ];

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($id),
            CURLOPT_SSL_VERIFYPEER => true, // Verificar el certificado del servidor
            CURLOPT_SSL_VERIFYHOST => 2,    // Verificar el hostname del certificado
        ]);

        $responseOperacion = curl_exec($curl);

        if (curl_errno($curl)) {
            throw new \Exception('Error en cURL: '.curl_error($curl));
        }

        $resultOperacion = json_decode($responseOperacion, true);

        if ($result === null) {
            throw new \Exception('Respuesta de la API inválida');
        }

        curl_close($curl);

        Log::info($resultOperacion);
    }
});

Route::get('/r4/bbva', function () {

    $cuenta = '01080989410100051948';
    $commerceToken = '0952d954b485debb4df0f2e9e70f03382d2c849e01bc9aab29ab61c9ff3f70b3';
    $url = 'https://r4conecta.mibanco.com.ve/TransferenciaOnline/DomiciliacionCNTA';
    $tokenAuthorization = hash_hmac('sha256', $cuenta, $commerceToken);

    $headers = [
        'Content-Type: application/json',
        'Authorization: '.$tokenAuthorization,
        'Commerce: '.$commerceToken,
    ];

    $postData = [
        'docId' => 'V15872584',
        'nombre' => 'Humberto Sanchez',
        'cuenta' => '01080989410100051948',
        'monto' => '100.00',
        'concepto' => 'Pago',
    ];

    $curl = curl_init($url);

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_SSL_VERIFYPEER => true, // Verificar el certificado del servidor
        CURLOPT_SSL_VERIFYHOST => 2,    // Verificar el hostname del certificado
    ]);

    $response = curl_exec($curl);

    if (curl_errno($curl)) {
        throw new \Exception('Error en cURL: '.curl_error($curl));
    }

    // Convierto el JSON to Array
    $result = json_decode($response, true);

    if ($result === null) {
        throw new \Exception('Respuesta de la API inválida');
    }

    curl_close($curl);

    // escribo el response en la tabla de log
    // LogTransactionalR4Controller::response($result['code'], $result['message'], isset($result['uuid']) ? $result['uuid'] : null);

    // Logging de la respuesta de la API
    Log::info($cuenta);
    Log::info($commerceToken);
    Log::info($url);
    Log::info($tokenAuthorization);
    Log::info($headers);
    Log::info(json_encode($postData));

    Log::info($result);

    Log::info($result['codigo']);

    if ($result['codigo'] == '202') {

        Log::info($result['codigo']);

        $uuid = $result['uuid'];
        $url = 'https://r4conecta.mibanco.com.ve/ConsultarOperaciones';

        $tokenAuthorization = hash_hmac('sha256', $uuid, $commerceToken);

        $headers = [
            'Content-Type: application/json',
            'Authorization: '.$tokenAuthorization,
            'Commerce: '.$commerceToken,
        ];

        $id = [
            'id' => $uuid,
        ];

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($id),
            CURLOPT_SSL_VERIFYPEER => true, // Verificar el certificado del servidor
            CURLOPT_SSL_VERIFYHOST => 2,    // Verificar el hostname del certificado
        ]);

        $responseOperacion = curl_exec($curl);

        if (curl_errno($curl)) {
            throw new \Exception('Error en cURL: '.curl_error($curl));
        }

        $resultOperacion = json_decode($responseOperacion, true);

        if ($result === null) {
            throw new \Exception('Respuesta de la API inválida');
        }

        curl_close($curl);

        Log::info($resultOperacion);
    }
});

Route::get('/tel/r4', function () {

    $telefono = '04127018390';
    $commerceToken = '0952d954b485debb4df0f2e9e70f03382d2c849e01bc9aab29ab61c9ff3f70b3';
    $url = 'https://r4conecta.mibanco.com.ve/TransferenciaOnline/DomiciliacionCNTA';
    $tokenAuthorization = hash_hmac('sha256', $telefono, $commerceToken);

    $headers = [
        'Content-Type: application/json',
        'Authorization: '.$tokenAuthorization,
        'Commerce: '.$commerceToken,
    ];

    $postData = [
        'docId' => 'V16007868',
        'telefono' => '04127018390',
        'nombre' => 'Gustavo Camacho',
        'banco' => '0108',
        'monto' => '1.20',
        'concepto' => 'Pago',
    ];

    $curl = curl_init($url);

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_SSL_VERIFYPEER => true, // Verificar el certificado del servidor
        CURLOPT_SSL_VERIFYHOST => 2,    // Verificar el hostname del certificado
    ]);

    $response = curl_exec($curl);

    if (curl_errno($curl)) {
        throw new \Exception('Error en cURL: '.curl_error($curl));
    }

    $result = json_decode($response, true);

    if ($result === null) {
        throw new \Exception('Respuesta de la API inválida');
    }

    curl_close($curl);

    Log::info($result);

    if ($result['codigo'] == '202') {

        Log::info($result['codigo']);

        $uuid = $result['uuid'];
        $url = 'https://r4conecta.mibanco.com.ve/ConsultarOperaciones';

        $tokenAuthorization = hash_hmac('sha256', $uuid, $commerceToken);

        $headers = [
            'Content-Type: application/json',
            'Authorization: '.$tokenAuthorization,
            'Commerce: '.$commerceToken,
        ];

        $id = [
            'id' => $uuid,
        ];

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($id),
            CURLOPT_SSL_VERIFYPEER => true, // Verificar el certificado del servidor
            CURLOPT_SSL_VERIFYHOST => 2,    // Verificar el hostname del certificado
        ]);

        $responseOperacion = curl_exec($curl);

        if (curl_errno($curl)) {
            throw new \Exception('Error en cURL: '.curl_error($curl));
        }

        $resultOperacion = json_decode($responseOperacion, true);

        if ($result === null) {
            throw new \Exception('Respuesta de la API inválida');
        }

        curl_close($curl);

        Log::info($resultOperacion);
    }
});

Route::get('/maps', function () {

    // dd(Carbon::parse('10/12/2025')->addMonth(4)->format('d/m/Y'));
    return view('maps-tres');
});

Route::get('/update', function () {

    // DB::connection('mysql2')->beginTransaction();
    // try {

    set_time_limit(120);

    $data = Collection::all();
    for ($i = 0; $i < count($data); $i++) {
        $data[$i]->update([
            'filter_next_payment_date' => Carbon::createFromFormat('d/m/Y', $data[$i]->next_payment_date)->format('Y-m-d'),
        ]);
    }

    // DB::connection('mysql2')->commit();
    // } catch (\Exception $e) {
    //     DB::connection('mysql2')->rollBack();
    //     throw $e;
    // }
});

Route::get('/pr/cumple', function () {

    try {

        set_time_limit(0);

        $rowsNotifications = BirthdayNotification::where('status', 'APROBADA')->get()->toArray();
        // dump($rowsNotifications);

        if (count($rowsNotifications) == 0) {
            return;
        }
        // Fecha actual con el formato para comparar dia y mes
        $now = now()->format('d/m');

        // dump($now);

        // dd($tables);
        for ($i = 0; $i < count($rowsNotifications); $i++) {

            // For para recorrer los canales de envio
            for ($j = 0; $j < count($rowsNotifications[$i]['channels']); $j++) {
                // dump($rowsNotifications[$i]['channels'][$j]);
                // Canal Whatsapp
                if ($rowsNotifications[$i]['channels'][$j] == 'whatsapp') {
                    // dump('whatsapp');

                    // AGENTS, USERS, SUPPLIERS
                    if ($rowsNotifications[$i]['data_type'] == 'agents' || $rowsNotifications[$i]['data_type'] == 'users' || $rowsNotifications[$i]['data_type'] == 'suppliers') {

                        // Selecciono la data que voy a utilizar segun la notificacion
                        $data = DB::table($rowsNotifications[$i]['data_type'])
                            ->select('name', 'email', 'phone', 'birth_date')
                            ->get()
                            ->toArray();

                        // for para recorrer la data, tomar la fecha y enviar la notificacion
                        for ($k = 0; $k < count($data); $k++) {

                            /**
                             * En caso de que la data venga NULL
                             */
                            if ($data[$k]->phone != null && $data[$k]->birth_date != null) {
                                // Tomamos la fecha de nacimiento de la data principal y la convertimos en el formato dd/mm
                                $conversionDate = UtilsController::converterDate($data[$k]->birth_date);

                                // comparamos la fecha de nacimiento con la fecha actual
                                if ($conversionDate == $now) {
                                    // Ejecuto el envio de la notificacion
                                    NotificationController::notificationBirthday($data[$k]->name, $data[$k]->phone, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file'], $rowsNotifications[$i]['type']);
                                }
                            } else {
                                continue;
                            }
                        }
                    }

                    // AFFILIATIONS
                    if ($rowsNotifications[$i]['data_type'] == 'affiliations') {
                        $data = DB::table($rowsNotifications[$i]['data_type'])
                            ->select('full_name_ti', 'email_ti', 'phone_ti', 'birth_date_ti')
                            ->where('birth_date_ti', $now)
                            ->get()
                            ->toArray();

                        // for para recorrer la data, tomar la fecha y enviar la notificacion
                        for ($k = 0; $k < count($data); $k++) {

                            /**
                             * En caso de que la data venga NULL
                             */
                            if ($data[$k]->phone_ti != null && $data[$k]->birth_date_ti != null) {
                                // Tomamos la fecha de nacimiento de la data principal y la convertimos en el formato dd/mm
                                $conversionDate = UtilsController::converterDate($data[$k]->birth_date_ti);

                                // comparamos la fecha de nacimiento con la fecha actual
                                if ($conversionDate == $now) {
                                    // Ejecuto el envio de la notificacion
                                    NotificationController::notificationBirthday($data[$k]->full_name_ti, $data[$k]->phone_ti, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file'], $rowsNotifications[$i]['type']);
                                }
                            } else {
                                continue;
                            }
                        }
                    }
                }

                // Canal Email
                // sendEmailBirthday($email, $name, $content, $file)
                if ($rowsNotifications[$i]['channels'][$j] == 'email') {
                    // dump('email');

                    // AGENTS, USERS, SUPPLIERS
                    if ($rowsNotifications[$i]['data_type'] == 'agents' || $rowsNotifications[$i]['data_type'] == 'users' || $rowsNotifications[$i]['data_type'] == 'suppliers') {
                        // dd($rowsNotifications[$i]['data_type']);
                        // Selecciono la data que voy a utilizar segun la notificacion
                        $data = DB::table($rowsNotifications[$i]['data_type'])
                            ->select('name', 'email', 'phone', 'birth_date')
                            ->get()
                            ->toArray();

                        // for para recorrer la data, tomar la fecha y enviar la notificacion
                        for ($k = 0; $k < count($data); $k++) {

                            /**
                             * En caso de que la data venga NULL
                             */
                            if ($data[$k]->email != null) {

                                // Ejecuto el envio de la notificacion
                                self::sendEmailBirthday($data[$k]->email, $data[$k]->name, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file']);
                            } else {
                                continue;
                            }
                        }
                    }

                    // AFFILIATIONS
                    if ($rowsNotifications[$i]['data_type'] == 'affiliations') {
                        // dump($rowsNotifications[$i]['data_type'], $rowsNotifications[$i]['channels']);
                        $data = DB::table($rowsNotifications[$i]['data_type'])
                            ->select('full_name_ti', 'email_ti', 'phone_ti', 'birth_date_ti')
                            ->get()
                            ->toArray();
                        // dump($data);

                        // for para recorrer la data, tomar la fecha y enviar la notificacion
                        for ($k = 0; $k < count($data); $k++) {

                            // Validamos si esta cumpliendo años
                            $isBirthdayToday = UtilsController::isBirthdayToday($data[$k]->birth_date_ti);
                            dump($isBirthdayToday);
                            if ($isBirthdayToday) {
                                // dd('cumple');
                                /**
                                 * En caso de que la data venga NULL
                                 */
                                if ($data[$k]->email_ti != null) {

                                    // Ejecuto el envio de la notificacion
                                    self::sendEmailBirthday($data[$k]->email_ti, $data[$k]->full_name_ti, $rowsNotifications[$i]['content'], $rowsNotifications[$i]['file']);
                                } else {
                                    continue;
                                }
                            } else {
                                continue;
                            }
                        }
                    }
                }

                // End...
            }

            // End...
        }

        return true;
    } catch (\Throwable $th) {
        Log::error($th->getMessage().' Linea: '.$th->getLine().' Archivo: '.$th->getFile());
    }
});

Route::get('/formulario-externo', function () {
    return view('formulario-externo');
})->name('formulario-externo');

/*
|--------------------------------------------------------------------------
| API Routes - Ubicación y Geografía
|--------------------------------------------------------------------------
|
| Estas rutas han sido optimizadas para permitir el almacenamiento en caché
| y mejorar la legibilidad del sistema de rutas de Laravel.
|
*/

Route::prefix('api')->name('api.')->group(function () {

    // Listado global de países
    Route::get('/countries', [FormularioExternoController::class, 'countries'])
        ->name('countries.index');

    // Listado de estados filtrados por país
    Route::get('/countries/{countryId}/states', [FormularioExternoController::class, 'statesByCountry'])
        ->name('countries.states');

    // Listado de ciudades filtradas por estado
    Route::get('/states/{stateId}/cities', [FormularioExternoController::class, 'citiesByState'])
        ->name('states.cities');

    /**
     * Ruta para cargar la informacion en la tabla
     *
     * @version 1.0.0
     *
     * @author Gustavo Camacho
     *
     * @return void
     */
    Route::post('/info/store', [BusinessAppointmentsController::class, 'store'])
        ->name('info.store');

    /*
    |--------------------------------------------------------------------------
    | API — Chat público guiado (guía-chat)
    |--------------------------------------------------------------------------
    |
    | Endpoints JSON para integrar el agente conversacional desde clientes
    | externos (app móvil, widget, etc.). La página /chat/publico usa Livewire
    | y no depende de estas rutas; comparten la misma lógica vía AgentOrchestrator.
    |
    */

    // POST /api/public-chat/session
    // Inicia una sesión nueva de chat público. No requiere body.
    // Respuesta: session_token, state, intent, handoff_requested.
    Route::post('/public-chat/session', [PublicChatController::class, 'session'])
        ->name('public-chat.session');

    // POST /api/public-chat/message
    // Envía un mensaje del usuario y obtiene la respuesta del agente.
    // Body: message (requerido), session_token (opcional), action_key (opcional, ej. registro_agente).
    // Respuesta: reply, state, intent, handoff_requested, tool_runs, external_redirect_url.
    Route::post('/public-chat/message', [PublicChatController::class, 'message'])
        ->name('public-chat.message');

    // GET /api/public-chat/history?session_token=...
    // Devuelve el historial de mensajes y el estado actual de una sesión existente.
    // Query: session_token (requerido).
    Route::get('/public-chat/history', [PublicChatController::class, 'history'])
        ->name('public-chat.history');

    /*
    |--------------------------------------------------------------------------
    | API — Acciones internas del chat (persistencia)
    |--------------------------------------------------------------------------
    |
    | Invocadas por el orquestador del agente al completar un flujo guiado.
    | Validan el payload con Form Request y delegan en los servicios de registro.
    |
    */

    // POST /api/internal/chat/agent-registration
    // Registra un agente o subagente capturado en el flujo del chat guiado.
    // Body validado por RegisterChatAgentRequest (datos personales, contacto, owner_code, etc.).
    Route::post('/internal/chat/agent-registration', [\App\Http\Controllers\Internal\ChatAgentRegistrationController::class, 'store'])
        ->name('internal.chat.agent-registration');

    // POST /api/internal/chat/agency-master-registration
    // Registra una agencia master desde el flujo del chat guiado.
    // Body validado por RegisterChatAgencyMasterRequest.
    Route::post('/internal/chat/agency-master-registration', [\App\Http\Controllers\Internal\ChatAgencyMasterRegistrationController::class, 'store'])
        ->name('internal.chat.agency-master-registration');

    // POST /api/internal/chat/agency-general-registration
    // Registra una agencia general desde el flujo del chat guiado.
    // Body validado por RegisterChatAgencyGeneralRequest.
    Route::post('/internal/chat/agency-general-registration', [\App\Http\Controllers\Internal\ChatAgencyGeneralRegistrationController::class, 'store'])
        ->name('internal.chat.agency-general-registration');

    // POST /api/internal/chat/individual-quote
    // Crea una cotización de plan individual recopilada en el chat guiado.
    // Body validado por RegisterChatIndividualQuoteRequest.
    Route::post('/internal/chat/individual-quote', [\App\Http\Controllers\Internal\ChatIndividualQuoteController::class, 'store'])
        ->name('internal.chat.individual-quote');
});

/**
 * Ruta para el link de pago
 *
 * @version 1.0.0
 *
 * @author Gustavo Camacho
 *
 * @return void
 */
Route::get('/ldi', function () {
    return view('link-debito-inmediato');
});

Route::get('/tasa-bcv', function () {
    $tasaBcv = ApiBcvController::getTasaBcv();
    $statusApiBcv = ApiBcvController::statusApiBcv();

    return response()->json([
        'tasaBcv' => $tasaBcv,
        'statusApiBcv' => $statusApiBcv,
    ]);
})->name('tasa-bcv');

/**
 * OPTIMIZACIÓN DE SEGURIDAD PARA LINK DE PAGO
 * * 1. Middleware 'signed': Garantiza que la URL no haya sido manipulada.
 * 2. Middleware 'throttle': Evita ataques de denegación de servicio o fuerza bruta.
 * 3. Parámetros validados: Se espera un UUID para identificar la transacción.
 */
Route::get('/ldi/{transaction_id}', [UtilsController::class, 'show'])
    ->name('ldi')
    ->middleware([
        'signed',           // Verifica que la firma de la URL sea válida
        'throttle:10,1',    // Máximo 10 intentos por minuto por IP
    ]);

/**
 * Rutas para pruebas de documentos PDF
 * - Carta de bienvenida de la agencia
 * - Carta de bienvenida del ejecutivo
 * - Carta de bienvenida del agente
 *
 * @version 1.0.0
 *
 * @author Gustavo Camacho
 *
 * @return void
 */
Route::get('/carta-bienvenida-agencia', function () {
    // Doc en PDF
    $pdf = Pdf::loadView('documents.carta-bienvenida-agencia');

    return $pdf->download('carta-bienvenida-agencia.pdf');
})->name('carta-bienvenida-agencia');

Route::get('/carta-bienvenida-ejecutivo', function () {
    // Doc en PDF
    $pdf = Pdf::loadView('documents.carta-bienvenida-ejecutivo');

    return $pdf->download('carta-bienvenida-ejecutivo.pdf');
})->name('carta-bienvenida-ejecutivo');

Route::get('/carta-bienvenida-agente', function () {
    // Doc en PDF
    $pdf = Pdf::loadView('documents.carta-bienvenida-agente');

    return $pdf->download('carta-bienvenida-agente.pdf');
})->name('carta-bienvenida-agente');
