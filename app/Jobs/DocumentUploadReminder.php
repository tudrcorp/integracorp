<?php

namespace App\Jobs;

use Throwable;
use App\Models\AgentDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Controllers\NotificationController;

class DocumentUploadReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número máximo de intentos.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * Tiempo en segundos para esperar antes de reintentar (opcional).
     *
     * @var int
     */
    public $backoff = 3; // Espera 3 segundos entre intentos

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->reminder();
        //
    }

    private function reminder()
    {
        
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
            if(count($doc) == 5){
                continue;
            }
            foreach ($doc as $key => $value) {
                $array_doc_agent[$key] = $value->title;
            }
            $result = array_diff($array_doc, $array_doc_agent);
            $string = implode(', ', $result);

            if ($agents[$i]->phone == null) {
                continue;
            }
            
            //Send Notificacion via Whatsapp
            NotificationController::documentUploadReminder($agents[$i]->phone, $agents[$i]->name, $string);
            
        }
    }

    /**
     * Handle a job failure.
     * Trabajo Fallido
     */
    public function failed(?Throwable $exception): void
    {
        Log::info("DocumentUploadReminder: FAILED");
        Log::error($exception->getMessage());
    }
}