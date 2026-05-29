<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

use App\Filament\Projects\Resources\ProjectManagement\Projects\ProjectResource;
use App\Models\ProjectManagement\Project;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class RecentProjectsNavigation
{
    /**
     * @return array<int, NavigationItem>
     */
    public static function items(): array
    {
        if (! Schema::hasTable('projects')) {
            return [];
        }

        $columns = ['id', 'name', 'color', 'icon'];
        if (! Schema::hasColumn('projects', 'color')) {
            $columns = ['id', 'name'];
        }

        return Project::query()
            ->latest('id')
            ->limit(5)
            ->get($columns)
            ->values()
            ->map(function (Project $project, int $index): NavigationItem {
                $icon = filled($project->icon ?? null)
                    ? (string) $project->icon
                    : ProjectManagementProjectAppearance::DEFAULT_ICON;

                return NavigationItem::make(Str::limit($project->name, 34))
                    ->group('PROYECTOS RECIENTES')
                    ->icon($icon)
                    ->sort($index + 1)
                    ->url(fn (): string => ProjectResource::getUrl('edit', ['record' => $project], panel: 'projects'));
            })
            ->all();
    }
}
