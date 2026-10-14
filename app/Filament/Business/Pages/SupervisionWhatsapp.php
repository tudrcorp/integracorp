<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Models\User;
use App\Support\CrmInbox\CrmInboxAreas;
use App\Support\CrmInbox\CrmInboxDashboard;
use App\Support\CrmInbox\CrmInboxSupervisor;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

/**
 * Tablero del supervisor de Atención WhatsApp: cuánto entra, qué tan rápido se responde,
 * cómo terminan los casos, la carga de cada analista y lo que está en riesgo ahora.
 * Solo lee; los relevos se hacen desde la bandeja.
 */
class SupervisionWhatsapp extends Page
{
    protected static ?string $navigationLabel = 'Supervisión WhatsApp';

    protected static ?string $title = 'Supervisión WhatsApp';

    protected static ?string $slug = 'supervision-whatsapp';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?int $navigationSort = 5;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected string $view = 'filament.crm.pages.supervision-whatsapp';

    #[Url(as: 'rango')]
    public string $range = 'hoy';

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && CrmInboxSupervisor::can($user, static::panelKey());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * Se revalida en cada petición de Livewire, no solo al entrar.
     */
    public function boot(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function mount(): void
    {
        if (! isset(CrmInboxDashboard::RANGES[$this->range])) {
            $this->range = 'hoy';
        }
    }

    public function setRange(string $range): void
    {
        $this->range = isset(CrmInboxDashboard::RANGES[$range]) ? $range : 'hoy';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $panel = static::panelKey();

        return [
            'data' => CrmInboxDashboard::build($panel, CrmInboxAreas::forPanel($panel), $panel === 'business', $this->range),
            'ranges' => CrmInboxDashboard::RANGES,
            'inboxUrl' => static::inboxUrl(),
            'libraryUrl' => static::libraryUrl(),
        ];
    }

    public static function panelKey(): string
    {
        return 'business';
    }

    protected static function inboxUrl(): string
    {
        return AtencionWhatsapp::getUrl(panel: static::panelKey());
    }

    protected static function libraryUrl(): string
    {
        return BibliotecaAtencion::getUrl(panel: static::panelKey());
    }
}
