<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

final class ProjectManagementFilamentSchemas
{
    public const ACTIVITY_INFOLIST_BITACORA_TAB_QUERY = 'bitacora::tab';

    public const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    public const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    public const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    /**
     * @param  array<int, \Filament\Schemas\Components\Tabs\Tab>  $tabs
     */
    public static function tabbed(Schema $schema, string $tabsId, array $tabs): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make($tabsId)
                    ->columnSpanFull()
                    ->persistTab()
                    ->persistTabInQueryString('tab')
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs($tabs),
            ]);
    }

    public static function section(string $title, ?string $description = null, ?string $icon = null): Section
    {
        $section = Section::make($title)
            ->columnSpanFull();

        if ($description !== null) {
            $section->description($description);
        }

        if ($icon !== null) {
            $section->icon($icon);
        }

        return $section->extraAttributes([
            'class' => self::IOS_SECTION_CLASS,
        ]);
    }

    /**
     * @param  array<int, \Filament\Schemas\Components\Component>  $components
     * @param  array<string, int>|int  $columns
     */
    public static function innerGrid(array $components, array|int $columns = 1): Grid
    {
        return Grid::make($columns)
            ->columnSpanFull()
            ->extraAttributes([
                'class' => self::IOS_INNER_CLASS,
            ])
            ->schema($components);
    }
}
