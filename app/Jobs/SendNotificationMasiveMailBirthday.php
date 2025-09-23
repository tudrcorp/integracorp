<?php

namespace App\Jobs;

use Exception;
use Throwable;
use App\Models\User;
use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Barryvdh\Debugbar\Facades\Debugbar;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Mail\SendMailPropuestaPlanEspecial;
use App\Services\NotificationMasiveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Mail\NotificationMasiveMailBirthday;
use App\Http\Controllers\NotificationController;

class SendNotificationMasiveMailBirthday implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $email;
    public $name;
    public $content;
    public $file;
    

    /**
     * Create a new job instance.
     */
    public function __construct($email, $name, $content, $file)
    {
        $this->email = $email;
        $this->name = $name;
        $this->content = $content;
        $this->file = $file;
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::to($this->email)->send(new NotificationMasiveMailBirthday($this->name, $this->content, $this->file));
        //
    }

    public function failed(?Throwable $exception)
    {
        Log::error('Job SendNotificationMasiveMailBirthday falló: ' . $exception->getMessage());

    }
}