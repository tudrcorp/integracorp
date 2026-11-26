<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Models\User;
use App\Support\CrmInbox\CrmBotMetrics;
use App\Support\CrmInbox\CrmChannelHealth;
use App\Support\CrmInbox\CrmChannelHeartbeat;
use App\Support\CrmInbox\CrmInboxAreas;
use App\Support\CrmInbox\CrmInboxDashboard;
use App\Support\CrmInbox\CrmInboxSupervisor;
use App\Support\CrmInbox\CrmSupervisionExport;
use App\Support\CrmInbox\CrmSupervisionInsights;
use App\Support\Filament\UserNavigationAccess;
use App\Support\SecurityAudit;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Centro de control de Atención WhatsApp: la bandeja (casos, tiempos, analistas), el bot Sol
 * (conversaciones, embudo, derivaciones, errores), hallazgos para decidir y, solo para
 * SUPERADMIN, la salud en vivo de WhatsApp (Evolution), n8n, el bot y la bandeja.
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

    /**
     * @var array<string, string>
     */
    public const TABS = [
        'resumen' => 'Resumen',
        'bot' => 'Bot Sol',
        'analistas' => 'Analistas',
        'hallazgos' => 'Hallazgos',
        'salud' => 'Salud en vivo',
    ];

    #[Url(as: 'rango')]
    public string $range = 'hoy';

    #[Url(as: 'vista')]
    public string $tab = 'resumen';

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
     * La salud técnica (Evolution, n8n, colas) es solo para SUPERADMIN.
     */
    public static function canSeeHealth(): bool
    {
        $user = Auth::user();

        return $user instanceof User && UserNavigationAccess::isSuperAdmin($user);
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
        $this->range = isset(CrmInboxDashboard::RANGES[$this->range]) ? $this->range : 'hoy';
        $this->tab = $this->allowedTab($this->tab);
    }

    public function setRange(string $range): void
    {
        $this->range = isset(CrmInboxDashboard::RANGES[$range]) ? $range : 'hoy';
    }

    public function setTab(string $tab): void
    {
        $this->tab = $this->allowedTab($tab);
    }

    /**
     * @return array<string, string>
     */
    public static function tabs(): array
    {
        return static::canSeeHealth() ? self::TABS : array_diff_key(self::TABS, ['salud' => true]);
    }

    private function allowedTab(string $tab): string
    {
        return isset(static::tabs()[$tab]) ? $tab : 'resumen';
    }

    public function exportCsv(): ?StreamedResponse
    {
        return $this->export('csv');
    }

    public function exportPdf(): ?StreamedResponse
    {
        return $this->export('pdf');
    }

    private function export(string $format): ?StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        try {
            $report = $this->report();
            $filename = CrmSupervisionExport::filename(static::panelKey(), $this->range, $format);

            SecurityAudit::log('AUDIT_CRM_SUPERVISION_EXPORTED', static::panelKey().'.supervision-whatsapp.export', [
                'format' => $format,
                'range' => $this->range,
            ]);

            return $format === 'pdf'
                ? response()->streamDownload(fn () => print (CrmSupervisionExport::pdf($report)), $filename, ['Content-Type' => 'application/pdf'])
                : response()->streamDownload(fn () => print (CrmSupervisionExport::csv($report)), $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('No se pudo exportar')->body('Intenta de nuevo en unos segundos.')->danger()->send();

            return null;
        }
    }

    /**
     * @return array{panel: string, range: string, range_label: string, data: array<string, mixed>, bot: array<string, mixed>|null, insights: list<array<string, string>>}
     */
    private function report(): array
    {
        $panel = static::panelKey();
        $data = CrmInboxDashboard::build($panel, CrmInboxAreas::forPanel($panel), $panel === 'business', $this->range);
        $bot = CrmBotMetrics::forRange($this->range);

        return [
            'panel' => $panel,
            'range' => $this->range,
            'range_label' => CrmInboxDashboard::RANGES[$this->range] ?? 'Hoy',
            'data' => $data,
            'bot' => $bot,
            'insights' => CrmSupervisionInsights::build($data, $bot),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $report = $this->report();
        $canSeeHealth = static::canSeeHealth();

        return [
            ...$report,
            'ranges' => CrmInboxDashboard::RANGES,
            'tabs' => static::tabs(),
            'canSeeHealth' => $canSeeHealth,
            'health' => $canSeeHealth ? CrmChannelHealth::current() : null,
            'history' => $canSeeHealth ? CrmChannelHeartbeat::history(60) : [],
            'incidents' => $canSeeHealth ? CrmChannelHeartbeat::incidents() : [],
            'uptime' => $canSeeHealth && $this->tab === 'salud' ? CrmChannelHeartbeat::uptime() : [],
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
