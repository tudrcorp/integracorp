<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Mail\SendMailPropuestaPlanEspecial;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendNotificacionAdministrador implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $title;
    public $body;
    

    /**
     * Create a new job instance.
     */
    public function __construct($title, $body)
    {
        $this->title = $title;
        $this->body = $body;
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $recipient = User::where('is_admin', 1)->get();
        foreach ($recipient as $user) {
            $recipient_for_user = User::find($user->id);
            Notification::make()
                ->title($this->title)
                ->body($this->body)
                ->icon('heroicon-s-user-group')
                ->iconColor('success')
                ->success()
                ->sendToDatabase($recipient_for_user);
        }
    }
}