<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Models\QrDestination;
use App\Models\QrDestinationVersion;
use App\Models\QrHostedPage;
use App\Models\User;
use App\Support\Companies\CompanyAssociateInclusionQrCatalog;
use App\Support\QrCenter\QrCenterAccess;
use App\Support\QrCenter\QrCenterStats;
use App\Support\QrCenter\QrDestinationManager;
use App\Support\QrCenter\QrHostedPageManager;
use App\Support\QrCenter\QrHostedPageReview;
use App\Support\TarjetaAfiliacionQrPlanCatalog;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;
use UnitEnum;

/**
 * Centro de QR de tarjetas: a dónde lleva el QR impreso en cada tarjeta, sus
 * versiones, las páginas HTML alojadas y las lecturas. Solo SUPERADMIN.
 */
class GeneradorQrPersonalizado extends Page
{
    use WithFileUploads;

    protected static ?string $navigationLabel = 'Centro de QR';

    protected static ?string $title = 'Centro de QR de tarjetas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.business.pages.generador-qr-personalizado';

    #[Url(as: 'seccion')]
    public string $tab = 'destinos';

    /** @var TemporaryUploadedFile|null */
    public $pageFile = null;

    public string $pageName = '';

    public string $pageSlug = '';

    /** Id de la página a la que se sube una versión nueva; null = página nueva. */
    public ?int $pageVersionOf = null;

    /** @var list<array{level: string, title: string, detail: string}> */
    public array $pageReview = [];

    public function mount(): void
    {
        if (! in_array($this->tab, ['destinos', 'paginas', 'libre'], true)) {
            $this->tab = 'destinos';
        }
    }

    /**
     * @return array<int, string>
     */
    public function getIndividualQrPlanOptions(): array
    {
        return TarjetaAfiliacionQrPlanCatalog::individualSelectOptions();
    }

    /**
     * @return array<int, string>
     */
    public function getCorporateQrPlanOptions(): array
    {
        return TarjetaAfiliacionQrPlanCatalog::corporateSelectOptions();
    }

    public function getCompanyAssociateInclusionLogoUrl(): string
    {
        return CompanyAssociateInclusionQrCatalog::logoPublicUrl();
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['destinos', 'paginas', 'libre'], true) ? $tab : 'destinos';
    }

    /**
     * Datos para abrir el editor de un destino. Reserva el enlace estable, que es
     * lo que va dibujado en el QR, antes de que el analista lo diseñe.
     *
     * @return array<string, mixed>
     */
    public function openEditor(string $key): array
    {
        $user = $this->authorizedUser();
        [$scope, $planId] = $this->parseKey($key);

        $destination = app(QrDestinationManager::class)->ensure($scope, $planId, $user);
        $destination->load('currentVersion.hostedPage:id,name,slug');
        $version = $destination->currentVersion;

        return [
            'key' => $key,
            'title' => $this->destinationName($scope, $planId),
            'scopeLabel' => QrDestinationManager::scopeLabel($scope),
            'stableUrl' => QrDestinationManager::stableUrl($destination),
            'documentLabel' => QrDestinationManager::documentLabel($scope),
            'documentUrl' => QrDestinationManager::documentUrl($scope, $planId),
            'currentLabel' => $this->targetLabel($destination),
            'targetType' => $version !== null && $version->target_type !== QrDestination::TARGET_AUTOMATIC
                ? $version->target_type
                : QrDestination::TARGET_HOSTED_PAGE,
            'targetUrl' => $version?->target_url ?? '',
            'hostedPageId' => $version?->qr_hosted_page_id,
            'style' => $version?->style,
            'pages' => QrHostedPage::query()
                ->whereNotNull('current_version_id')
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(fn (QrHostedPage $page): array => ['id' => $page->id, 'name' => $page->name, 'url' => route('qr.page', ['slug' => $page->slug])])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function saveDestination(string $key, array $payload, string $pngDataUrl): bool
    {
        $user = $this->authorizedUser();
        [$scope, $planId] = $this->parseKey($key);

        try {
            $destination = app(QrDestinationManager::class)->ensure($scope, $planId, $user);
            $version = app(QrDestinationManager::class)->save(
                $destination,
                (string) ($payload['targetType'] ?? ''),
                isset($payload['targetUrl']) ? (string) $payload['targetUrl'] : null,
                isset($payload['hostedPageId']) && $payload['hostedPageId'] !== '' ? (int) $payload['hostedPageId'] : null,
                is_array($payload['style'] ?? null) ? $payload['style'] : [],
                $this->decodePng($pngDataUrl),
                $user,
            );
        } catch (ValidationException $exception) {
            $this->notifyError('No se aplicó el QR', collect($exception->errors())->flatten()->first() ?? 'Revisa los datos.');

            return false;
        } catch (Throwable $exception) {
            report($exception);
            $this->notifyError('No se aplicó el QR', 'Ocurrió un error al guardarlo. Las tarjetas siguen con el QR anterior.');

            return false;
        }

        Notification::make()
            ->title('QR aplicado · versión '.$version->version)
            ->body('Las tarjetas de '.$this->destinationName($scope, $planId).' ('.mb_strtolower(QrDestinationManager::scopeLabel($scope)).') que se generen desde ahora llevarán este QR.')
            ->success()
            ->send();

        return true;
    }

    public function resetDestination(string $key): void
    {
        $user = $this->authorizedUser();
        [$scope, $planId] = $this->parseKey($key);
        $destination = QrDestinationManager::find($scope, $planId);

        if ($destination === null || ! $destination->isCustomized()) {
            Notification::make()->title('Ya usa el QR automático')->info()->send();

            return;
        }

        try {
            app(QrDestinationManager::class)->resetToAutomatic($destination, $user);
        } catch (Throwable $exception) {
            report($exception);
            $this->notifyError('No se pudo volver al QR automático', 'Inténtalo de nuevo en unos segundos.');

            return;
        }

        Notification::make()
            ->title('Volvió al QR automático')
            ->body('Las tarjetas nuevas imprimirán el QR de siempre. Las que ya tienen el enlace estable llevan al '.mb_strtolower(QrDestinationManager::documentLabel($scope)).'.')
            ->success()
            ->send();
    }

    /**
     * Historial y lecturas por día de un destino.
     *
     * @return array<string, mixed>
     */
    public function history(string $key): array
    {
        $this->authorizedUser();
        [$scope, $planId] = $this->parseKey($key);
        $destination = QrDestinationManager::find($scope, $planId);

        return [
            'key' => $key,
            'title' => $this->destinationName($scope, $planId),
            'scopeLabel' => QrDestinationManager::scopeLabel($scope),
            'stableUrl' => $destination !== null ? QrDestinationManager::stableUrl($destination) : null,
            'daily' => $destination !== null ? QrCenterStats::dailyForDestination($destination->id) : [],
            'versions' => $destination === null ? [] : $destination->versions()
                ->with('hostedPage:id,name')
                ->limit(50)
                ->get()
                ->map(fn (QrDestinationVersion $version): array => [
                    'id' => $version->id,
                    'version' => $version->version,
                    'current' => $version->id === $destination->current_version_id,
                    'target' => $this->versionTargetLabel($version, $scope),
                    'note' => $version->note,
                    'meta' => ($version->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—').' · '.($version->created_by_name ?? 'Sistema'),
                    'image' => $version->image_path !== null ? route('business.qr-center.image', ['version' => $version->id]) : null,
                    'download' => $version->image_path !== null ? route('business.qr-center.image', ['version' => $version->id, 'descargar' => 1]) : null,
                ])
                ->all(),
        ];
    }

    public function restoreVersion(int $versionId): bool
    {
        $user = $this->authorizedUser();
        $version = QrDestinationVersion::query()->find($versionId);

        if ($version === null) {
            $this->notifyError('No se encontró esa versión', 'Recarga la página.');

            return false;
        }

        try {
            $restored = app(QrDestinationManager::class)->restore($version, $user);
        } catch (ValidationException $exception) {
            $this->notifyError('No se pudo restaurar', collect($exception->errors())->flatten()->first() ?? 'Revisa la versión.');

            return false;
        } catch (Throwable $exception) {
            report($exception);
            $this->notifyError('No se pudo restaurar', 'Ocurrió un error. El QR vigente no cambió.');

            return false;
        }

        Notification::make()
            ->title('Versión '.$version->version.' restaurada')
            ->body('Quedó como versión '.$restored->version.' en el historial.')
            ->success()
            ->send();

        return true;
    }

    public function updatedPageFile(): void
    {
        $this->authorizedUser();
        $this->resetErrorBag('pageFile');
        $this->pageReview = [];

        if (! $this->pageFile instanceof TemporaryUploadedFile) {
            return;
        }

        $extension = strtolower($this->pageFile->getClientOriginalExtension());

        if (! in_array($extension, ['html', 'htm'], true)) {
            $this->addError('pageFile', 'Sube un archivo .html.');
            $this->pageFile = null;

            return;
        }

        $this->pageReview = QrHostedPageReview::review((string) $this->pageFile->get());

        if ($this->pageVersionOf === null && trim($this->pageName) === '') {
            $this->pageName = Str::limit(trim(pathinfo($this->pageFile->getClientOriginalName(), PATHINFO_FILENAME)), 120, '');
        }

        if ($this->pageVersionOf === null && trim($this->pageSlug) === '') {
            $this->pageSlug = QrHostedPageManager::suggestSlug($this->pageName);
        }
    }

    public function updatedPageName(): void
    {
        if ($this->pageVersionOf === null) {
            $this->pageSlug = QrHostedPageManager::suggestSlug($this->pageName);
        }
    }

    public function startPageVersion(?int $pageId): void
    {
        $this->authorizedUser();
        $this->resetPageForm();
        $page = $pageId !== null ? QrHostedPage::query()->find($pageId) : null;
        $this->pageVersionOf = $page?->id;
        $this->pageName = $page?->name ?? '';
        $this->pageSlug = $page?->slug ?? '';
    }

    public function publishPage(): void
    {
        $user = $this->authorizedUser();

        if (! $this->pageFile instanceof TemporaryUploadedFile) {
            $this->addError('pageFile', 'Elige el archivo .html que quieres publicar.');

            return;
        }

        if ($this->pageFile->getSize() > QrHostedPageReview::MAX_BYTES) {
            $this->addError('pageFile', 'El archivo supera el máximo de '.QrHostedPageReview::humanSize(QrHostedPageReview::MAX_BYTES).'.');

            return;
        }

        $manager = app(QrHostedPageManager::class);
        $html = (string) $this->pageFile->get();
        $originalName = $this->pageFile->getClientOriginalName();

        try {
            if ($this->pageVersionOf !== null) {
                $page = QrHostedPage::query()->findOrFail($this->pageVersionOf);
                $page = $manager->addVersion($page, $html, $originalName, $user);
            } else {
                $page = $manager->create($this->pageName, $this->pageSlug, $html, $originalName, $user);
            }
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, (string) ($messages[0] ?? 'Revisa este dato.'));
            }

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->notifyError('No se publicó la página', 'Ocurrió un error al guardarla. Inténtalo de nuevo.');

            return;
        }

        $page->load('currentVersion:id,version');

        Notification::make()
            ->title($this->pageVersionOf !== null ? 'Versión '.$page->currentVersion?->version.' publicada' : 'Página publicada')
            ->body('Disponible en '.route('qr.page', ['slug' => $page->slug]))
            ->success()
            ->send();

        $this->resetPageForm();
    }

    public function resetPageForm(): void
    {
        $this->pageFile = null;
        $this->pageName = '';
        $this->pageSlug = '';
        $this->pageVersionOf = null;
        $this->pageReview = [];
        $this->resetErrorBag();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $catalog = QrDestinationManager::catalog();
        $scans = QrCenterStats::byDestination();
        $legacyPath = (string) config('affiliate-card.qr_plans_path');

        $rows = array_map(function (array $row) use ($scans, $legacyPath): array {
            /** @var QrDestination|null $destination */
            $destination = $row['destination'];
            $version = $destination?->currentVersion;
            $customized = $destination !== null && $destination->isCustomized();
            $legacyFile = $this->legacyFilename($row['scope'], $row['plan_id']);
            $hasLegacy = ! $customized && $legacyFile !== null && is_file($legacyPath.'/'.$legacyFile);

            return [
                'key' => $row['key'],
                'name' => $row['name'],
                'scope' => $row['scope_label'],
                'customized' => $customized,
                'kind' => match (true) {
                    $customized && $version?->target_type === QrDestination::TARGET_HOSTED_PAGE => ['Página alojada', 'is-page'],
                    $customized && $version?->target_type === QrDestination::TARGET_URL => ['Enlace externo', 'is-url'],
                    $customized => [QrDestinationManager::documentLabel($row['scope']).' · enlace estable', 'is-doc'],
                    $hasLegacy => ['Imagen subida antes del centro', 'is-legacy'],
                    default => ['Automático', 'is-auto'],
                },
                'target' => $destination !== null && $customized ? $this->targetLabel($destination) : ($hasLegacy ? 'Destino desconocido (imagen anterior)' : QrDestinationManager::documentLabel($row['scope'])),
                'link' => $customized ? QrDestinationManager::stableUrl($destination) : ($hasLegacy ? '' : 'Se genera al imprimir la tarjeta'),
                'updated' => $customized ? ($version?->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—') : '—',
                'by' => $customized ? ($version?->created_by_name ?? 'Sistema') : 'Sin personalizar',
                'thumb' => match (true) {
                    $customized && $version?->image_path !== null => route('business.qr-center.image', ['version' => $version->id]),
                    $hasLegacy => asset('storage/tarjeta-afiliacion/planes/'.$legacyFile),
                    default => null,
                },
                'scans' => $destination !== null ? ($scans[$destination->id]['scans'] ?? 0) : 0,
                'visitors' => $destination !== null ? ($scans[$destination->id]['visitors'] ?? 0) : 0,
                'hasHistory' => $destination !== null && $destination->current_version_id !== null,
            ];
        }, $catalog);

        $pageStats = QrCenterStats::byHostedPage();
        $usage = QrHostedPageManager::usageCounts();
        $pages = QrHostedPage::query()
            ->with('currentVersion:id,version,size_bytes,review,created_at,created_by_name')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (QrHostedPage $page): array => [
                'id' => $page->id,
                'name' => $page->name,
                'url' => route('qr.page', ['slug' => $page->slug]),
                'version' => $page->currentVersion?->version,
                'size' => $page->currentVersion !== null ? QrHostedPageReview::humanSize($page->currentVersion->size_bytes) : '—',
                'warnings' => collect($page->currentVersion?->review ?? [])->where('level', QrHostedPageReview::LEVEL_WARN)->pluck('title')->all(),
                'published' => ($page->currentVersion?->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '—').' · '.($page->currentVersion?->created_by_name ?? 'Sistema'),
                'usage' => $usage[$page->id] ?? 0,
                'views' => $pageStats[$page->id]['scans'] ?? 0,
                'visitors' => $pageStats[$page->id]['visitors'] ?? 0,
            ])
            ->all();

        return [
            'rows' => $rows,
            'pages' => $pages,
            'totals' => [
                'destinations' => count($rows),
                'customized' => count(array_filter($rows, fn (array $row): bool => $row['customized'])),
                'scans' => array_sum(array_map(fn (array $stat): int => $stat['scans'], $scans)),
                'pageViews' => array_sum(array_map(fn (array $stat): int => $stat['scans'], $pageStats)),
            ],
            'statsDays' => QrCenterStats::DAYS,
            'brandLogoUrl' => $this->getCompanyAssociateInclusionLogoUrl(),
            'maxPageSize' => QrHostedPageReview::humanSize(QrHostedPageReview::MAX_BYTES),
            'logoMax' => QrDestinationManager::LOGO_MAX,
            'minContrast' => QrDestinationManager::MIN_CONTRAST,
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return QrCenterAccess::allows();
    }

    public static function canAccess(): bool
    {
        return QrCenterAccess::allows();
    }

    private function authorizedUser(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User && QrCenterAccess::allows($user), 403);

        return $user;
    }

    /**
     * @return array{0: string, 1: ?int}
     */
    private function parseKey(string $key): array
    {
        if (! preg_match('/^(individual|corporativo|inclusion):(\d{1,10})$/', $key, $match)) {
            throw ValidationException::withMessages(['key' => 'Destino no válido.']);
        }

        $planId = (int) $match[2];

        return [$match[1], $planId === 0 ? null : $planId];
    }

    private function destinationName(string $scope, ?int $planId): string
    {
        if ($scope === QrDestination::SCOPE_INCLUSION) {
            return 'Tarjetas de nuevos negocios';
        }

        $description = TarjetaAfiliacionQrPlanCatalog::corporateSelectOptions()[$planId] ?? ('Plan '.$planId);

        return Str::title(mb_strtolower((string) $description));
    }

    private function targetLabel(QrDestination $destination): string
    {
        $version = $destination->currentVersion;

        if ($version === null || $version->target_type === QrDestination::TARGET_AUTOMATIC) {
            return QrDestinationManager::documentLabel($destination->scope).' (automático)';
        }

        return $this->versionTargetLabel($version, $destination->scope);
    }

    private function versionTargetLabel(QrDestinationVersion $version, string $scope): string
    {
        return match ($version->target_type) {
            QrDestination::TARGET_HOSTED_PAGE => 'Página alojada · '.($version->hostedPage?->name ?? 'página eliminada'),
            QrDestination::TARGET_URL => (string) $version->target_url,
            QrDestination::TARGET_DOCUMENT => QrDestinationManager::documentLabel($scope),
            default => 'QR automático',
        };
    }

    private function legacyFilename(string $scope, ?int $planId): ?string
    {
        return match ($scope) {
            QrDestination::SCOPE_INCLUSION => CompanyAssociateInclusionQrCatalog::QR_FILENAME,
            QrDestination::SCOPE_INDIVIDUAL => TarjetaAfiliacionQrPlanCatalog::qrFilenameForPlanId((int) $planId),
            default => null,
        };
    }

    private function decodePng(string $dataUrl): string
    {
        if (! str_starts_with($dataUrl, 'data:image/png;base64,')) {
            throw ValidationException::withMessages(['png' => 'La imagen del QR no llegó completa. Vuelve a intentarlo.']);
        }

        $binary = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);

        if ($binary === false) {
            throw ValidationException::withMessages(['png' => 'La imagen del QR llegó dañada. Vuelve a intentarlo.']);
        }

        return $binary;
    }

    private function notifyError(string $title, string $body): void
    {
        Notification::make()->title($title)->body($body)->danger()->persistent()->send();
    }
}
