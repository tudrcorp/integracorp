<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Support\LivePresence\ErrorTracker;
use App\Support\LivePresence\LiveActivitySnapshot;
use App\Support\LivePresence\LiveMonitorTvBoard;
use App\Support\LivePresence\OperationsAdvisor;
use App\Support\LivePresence\SecuritySnapshot;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pantalla grande del monitor en vivo: solo lectura, sin sesión de usuario.
 *
 * Se entra con /monitor/tv/{token}. No vence a las 2 horas, no choca con el
 * control de sesión duplicada y no permite ninguna acción. El token se revisa
 * en cada refresco, así que cambiarlo en el .env corta la pantalla al instante.
 */
#[Layout('components.layouts.live-monitor-tv')]
#[Title('Monitor en vivo')]
class LiveMonitorTv extends Component
{
    #[Locked]
    public string $token = '';

    public function mount(string $token): void
    {
        abort_unless(self::tokenIsValid($token), 404);

        $this->token = $token;
    }

    public function boot(): void
    {
        if ($this->token !== '') {
            abort_unless(self::tokenIsValid($this->token), 404);
        }
    }

    public static function tokenIsValid(string $token): bool
    {
        $expected = trim((string) config('live-presence.tv_token', ''));

        return strlen($expected) >= 32 && hash_equals($expected, $token);
    }

    public function render(): View
    {
        $sessions = LiveActivitySnapshot::sessions();
        $security = SecuritySnapshot::build();
        $health = LiveActivitySnapshot::systemHealth();
        $advice = OperationsAdvisor::advise($security, $health['queue_report'] ?? null, ErrorTracker::groups());

        return view('livewire.live-monitor-tv', [
            'board' => LiveMonitorTvBoard::build($security, LiveActivitySnapshot::kpis($sessions), $health, $advice, $sessions),
            'refreshedAt' => now()->format('H:i:s'),
        ]);
    }
}
