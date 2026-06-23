<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\PublicAiAgent\ChatAgentRegistrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendChatAgentRegistrationWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function __construct(
        public array $credentials,
    ) {}

    public function handle(ChatAgentRegistrationService $chatAgentRegistrationService): void
    {
        $chatAgentRegistrationService->sendRegistrationPackageViaWhatsApp($this->credentials);
    }
}
