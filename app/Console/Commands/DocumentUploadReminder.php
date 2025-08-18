<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AgentDocument;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DocumentUploadReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminder:uploaddoc';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $array_doc = [
            'DOCUMENTO DE IDENTIDAD',
            'FIRMA DIGITAL AGENTE',
            'W8/W9',
            'CUENTA USD',
            'CUENTA VES'
        ];

        $agents = DB::table('agents')
            ->select('id', 'email', 'phone', 'status')
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

            if (!empty($string)) {
                continue;
            }

            if (empty($agents[$i]->phone)) {
                break;
            }
            
            //Send Notificacion via Whatsapp
            NotificationController::documentUploadReminder($agents[$i]->phone, $agents[$i]->name, $string);
        }
    }
}
