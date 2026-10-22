<?php

declare(strict_types=1);

namespace App\Filament\Shared\OrgStructure\Pages;

use App\Enums\OrgStructureVersionStatus;
use App\Filament\Business\Pages\EstructuraOrganizacional;
use App\Jobs\GenerateOrgChartExportJob;
use App\Models\OrgSavedView;
use App\Models\OrgStructureVersion;
use App\Models\User;
use App\Support\Filament\SharedNavigationGroups;
use App\Support\OrgStructure\OrgChartPayload;
use App\Support\OrgStructure\OrgChartPdfExporter;
use App\Support\OrgStructure\OrgStructureAccess;
use App\Support\OrgStructure\OrgStructureCache;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Renderless;
use Throwable;
use UnitEnum;

/**
 * Organigrama de la empresa. En Negocios se consulta completo (historial,
 * exportación y enlace al estudio). En Administración, Operaciones, Marketing,
 * Proyectos y Métricas solo se ve la versión publicada, y el menú no aparece
 * si todavía no hay una. Telemedicina y el panel de administración no lo tienen.
 *
 * El árbol viaja una sola vez en el HTML y se dibuja en el navegador; las demás
 * llamadas son `Renderless` y devuelven datos, así el estado de Livewire no carga
 * el organigrama en cada petición.
 */
class OrgChartPage extends Page
{
    protected static ?string $navigationLabel = 'Organigrama';

    protected static ?string $title = 'Organigrama';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = SharedNavigationGroups::USER;

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'organigrama';

    protected string $view = 'filament.shared.org-structure.org-chart-page';

    private const MAX_SAVED_VIEWS = 50;

    private const MAX_HISTORY = 40;

    /**
     * Los paneles de consulta redefinen esto: solo la versión publicada, sin
     * estudio, historial ni exportación.
     */
    protected static function showsPublishedChartOnly(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        if (! OrgStructureAccess::canView(self::currentUser())) {
            return false;
        }

        if (static::showsPublishedChartOnly() && OrgStructureCache::currentVersionRef() === null) {
            return false;
        }

        return true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * Todo lo que la pantalla necesita para dibujar sin otra petición.
     *
     * @return array<string, mixed>
     */
    public function chartConfig(): array
    {
        $user = self::currentUser();
        $current = OrgStructureCache::currentVersion();
        $publishedOnly = static::showsPublishedChartOnly();

        return [
            'mode' => 'view',
            'payload' => $current !== null ? OrgChartPayload::forViewer(OrgChartPayload::build($current)) : null,
            'currentVersionId' => $current?->id,
            'versions' => $publishedOnly ? $this->publishedVersionOption($current) : $this->historyOptions(),
            'views' => $publishedOnly ? [] : $this->savedViews(),
            'canExport' => $publishedOnly ? false : OrgStructureAccess::canExport($user),
            'canManageViews' => $publishedOnly ? false : OrgStructureAccess::canEdit($user),
            'studioUrl' => $publishedOnly || ! OrgStructureAccess::canOpenStudio($user) ? null : $this->studioUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[Renderless]
    public function loadVersion(int $versionId): array
    {
        abort_unless(static::canAccess(), 403);

        if (static::showsPublishedChartOnly()) {
            $current = OrgStructureCache::currentVersion();

            if ($current === null || (int) $current->id !== $versionId) {
                return ['ok' => false, 'message' => 'Solo puedes consultar el organigrama publicado.'];
            }
        }

        $version = OrgStructureVersion::query()->find($versionId);

        if ($version === null || ! $version->status->isHistorical()) {
            return ['ok' => false, 'message' => 'Esa versión no se puede consultar.'];
        }

        return ['ok' => true, 'payload' => OrgChartPayload::forViewer(OrgChartPayload::build($version))];
    }

    /**
     * @param  array{root_key?: string|null, depth?: int|string|null, show_contact?: bool, show_vacancies?: bool}  $options
     * @return array{ok: bool, message: string}
     */
    #[Renderless]
    public function requestPdfExport(int $versionId, array $options): array
    {
        $user = self::currentUser();

        if (static::showsPublishedChartOnly() || ! OrgStructureAccess::canExport($user)) {
            return ['ok' => false, 'message' => 'No tienes permiso para exportar el organigrama.'];
        }

        $version = OrgStructureVersion::query()->find($versionId);

        if ($version === null || ! ($version->status->isHistorical() || $this->canSeeDraftExports($user, $version))) {
            return ['ok' => false, 'message' => 'Esa versión no se puede exportar.'];
        }

        $limiterKey = 'org-chart-export:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($limiterKey, 6)) {
            return ['ok' => false, 'message' => 'Hiciste muchas exportaciones seguidas. Intenta de nuevo en '.max(1, (int) ceil(RateLimiter::availableIn($limiterKey) / 60)).' minuto(s).'];
        }

        if (! GenerateOrgChartExportJob::claimSlot((int) $user->getKey())) {
            return ['ok' => false, 'message' => 'Ya tienes un organigrama generándose. Te avisaremos en la campana cuando esté listo.'];
        }

        RateLimiter::hit($limiterKey, 600);

        $depth = filled($options['depth'] ?? null) ? max(1, min(20, (int) $options['depth'])) : null;
        $rootKey = isset($options['root_key']) && is_string($options['root_key']) && preg_match('/^[0-9a-f-]{36}$/i', $options['root_key']) === 1
            ? $options['root_key']
            : null;

        try {
            GenerateOrgChartExportJob::dispatch($version->id, [
                'root_key' => $rootKey,
                'depth' => $depth,
                'show_contact' => (bool) ($options['show_contact'] ?? false),
                'show_vacancies' => (bool) ($options['show_vacancies'] ?? true),
            ], (int) $user->getKey(), OrgChartPdfExporter::newFilename());
        } catch (Throwable $exception) {
            GenerateOrgChartExportJob::releaseSlot((int) $user->getKey());
            report($exception);

            return ['ok' => false, 'message' => 'No se pudo poner en cola la exportación. Intenta de nuevo.'];
        }

        return ['ok' => true, 'message' => 'Estamos generando el PDF. Te avisaremos en la campana cuando esté listo.'];
    }

    /**
     * @param  array{name?: string, root_key?: string|null, depth?: int|string|null, card_fields?: list<string>, show_vacancies?: bool}  $data
     * @return array{ok: bool, message: string, views?: list<array<string, mixed>>}
     */
    #[Renderless]
    public function saveView(array $data): array
    {
        $user = self::currentUser();

        if (static::showsPublishedChartOnly() || ! OrgStructureAccess::canEdit($user)) {
            return ['ok' => false, 'message' => 'No tienes permiso para guardar vistas.'];
        }

        $name = trim((string) ($data['name'] ?? ''));

        if (mb_strlen($name) < 3 || mb_strlen($name) > 80) {
            return ['ok' => false, 'message' => 'El nombre de la vista debe tener entre 3 y 80 caracteres.'];
        }

        $existing = OrgSavedView::query()->where('name', $name)->first();

        if ($existing === null && OrgSavedView::query()->count() >= self::MAX_SAVED_VIEWS) {
            return ['ok' => false, 'message' => 'Ya hay '.self::MAX_SAVED_VIEWS.' vistas guardadas. Elimina alguna antes de crear otra.'];
        }

        $rootKey = isset($data['root_key']) && is_string($data['root_key']) && preg_match('/^[0-9a-f-]{36}$/i', $data['root_key']) === 1 ? $data['root_key'] : null;
        $fields = array_values(array_intersect(['photo', 'department', 'contact'], (array) ($data['card_fields'] ?? [])));

        $view = $existing ?? new OrgSavedView(['created_by' => $user->getKey()]);
        $view->fill([
            'name' => $name,
            'root_position_key' => $rootKey,
            'depth' => filled($data['depth'] ?? null) ? max(1, min(20, (int) $data['depth'])) : null,
            'card_fields' => $fields,
            'show_vacancies' => (bool) ($data['show_vacancies'] ?? true),
            'updated_by' => $user->getKey(),
        ]);
        $view->save();

        return ['ok' => true, 'message' => 'Vista «'.$name.'» guardada.', 'views' => $this->savedViews()];
    }

    /**
     * @return array{ok: bool, message: string, views?: list<array<string, mixed>>}
     */
    #[Renderless]
    public function deleteView(int $viewId): array
    {
        if (static::showsPublishedChartOnly() || ! OrgStructureAccess::canEdit(self::currentUser())) {
            return ['ok' => false, 'message' => 'No tienes permiso para eliminar vistas.'];
        }

        OrgSavedView::query()->whereKey($viewId)->delete();

        return ['ok' => true, 'message' => 'Vista eliminada.', 'views' => $this->savedViews()];
    }

    /**
     * @return list<array{id: int, label: string, current: bool}>
     */
    protected function publishedVersionOption(?OrgStructureVersion $current): array
    {
        if ($current === null) {
            return [];
        }

        return [[
            'id' => (int) $current->id,
            'label' => $current->name.' · vigente',
            'current' => true,
        ]];
    }

    /**
     * @return list<array{id: int, label: string, current: bool}>
     */
    protected function historyOptions(): array
    {
        return OrgStructureVersion::query()
            ->whereIn('status', [OrgStructureVersionStatus::Current->value, OrgStructureVersionStatus::Archived->value])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(self::MAX_HISTORY)
            ->get(['id', 'name', 'status', 'published_at', 'archived_at'])
            ->map(fn (OrgStructureVersion $version): array => [
                'id' => (int) $version->id,
                'label' => $version->name
                    .' · '.($version->status === OrgStructureVersionStatus::Current ? 'vigente' : 'archivada')
                    .($version->published_at !== null ? ' desde '.$version->published_at->format('d/m/Y') : ''),
                'current' => $version->status === OrgStructureVersionStatus::Current,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function savedViews(): array
    {
        return OrgSavedView::query()
            ->orderBy('name')
            ->limit(self::MAX_SAVED_VIEWS)
            ->get()
            ->map(fn (OrgSavedView $view): array => [
                'id' => (int) $view->id,
                'name' => $view->name,
                'root_key' => $view->root_position_key,
                'depth' => $view->depth,
                'card_fields' => $view->card_fields ?? ['photo', 'department'],
                'show_vacancies' => $view->show_vacancies,
            ])
            ->values()
            ->all();
    }

    protected function canSeeDraftExports(User $user, OrgStructureVersion $version): bool
    {
        return false;
    }

    protected function studioUrl(): ?string
    {
        try {
            $user = self::currentUser();

            if ($user === null || ! $user->canAccessPanel(Filament::getPanel('business'))) {
                return null;
            }

            return EstructuraOrganizacional::getUrl(panel: 'business');
        } catch (Throwable) {
            return null;
        }
    }

    protected static function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
