<?php

declare(strict_types=1);

namespace App\Filament\Projects\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Support\ProjectManagement\ProjectManagementHelpGuide;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class Help extends Page
{
    use AuthorizesDepartmentNavigation;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static string|UnitEnum|null $navigationGroup = 'AYUDA';

    protected static ?string $navigationLabel = 'Ayuda';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Ayuda del módulo de proyectos';

    protected static ?string $slug = 'ayuda';

    protected string $view = 'filament.projects.pages.help';

    public string $search = '';

    public string $activeSection = 'inicio';

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSectionsProperty(): array
    {
        $needle = mb_strtolower(trim($this->search));

        if ($needle === '') {
            return ProjectManagementHelpGuide::sections();
        }

        return collect(ProjectManagementHelpGuide::sections())
            ->filter(function (array $section) use ($needle): bool {
                $haystack = mb_strtolower(json_encode($section, JSON_UNESCAPED_UNICODE) ?: '');

                return str_contains($haystack, $needle);
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, title: string}>
     */
    public function getTocProperty(): array
    {
        return collect($this->sections)
            ->map(fn (array $section): array => [
                'id' => $section['id'],
                'title' => $section['title'],
            ])
            ->values()
            ->all();
    }

    public function setActiveSection(string $sectionId): void
    {
        $this->activeSection = $sectionId;
    }
}
