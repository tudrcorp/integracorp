<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\AgentDocument;
use App\Models\DataNotification;
use PhpParser\Node\Stmt\TryCatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Mail\NotificationMasiveMail;
use App\Models\BirthdayNotification;
use App\Models\TelemedicineDocument;
use Illuminate\Support\Facades\Mail;
use Barryvdh\Debugbar\Facades\Debugbar;
use App\Http\Controllers\UtilsController;
use App\Mail\NotificationMasiveMailBirthday;
use App\Models\TelemedicinePatientMedications;
use App\Http\Controllers\NotificationController;
use App\Jobs\SendNotificationMasiveMailBirthday;

class NotificationTelemedicinaService
{
    static function sendPreviewNotification($phone)
    {
        try {

            set_time_limit(0);

            NotificationController::previewMessage($phone);
            
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    static function sendDocuments($patient_id, $case_id, $phone, $type_document)
    {
        Log::info("Enviando documentos de telemedicina al paciente ID: $patient_id, caso ID: $case_id, telefono: $phone, tipo de documento: $type_document");
        try {

            set_time_limit(0);

            $doc = TelemedicineDocument::where('telemedicine_patient_id', $patient_id)->where('telemedicine_case_id', $case_id)->get()->toArray();
            Log::info($doc);

            for ($i = 0; $i < count($doc); $i++) {
                NotificationController::sendDocumentsToPatient($phone, $type_document, $doc[$i]['name']);
            }
            

        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }
}