<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Support\HelpdeskUnreadNoteTracker;
use Filament\Navigation\NavigationItem;

use function Filament\Support\original_request;

trait RegistersHelpdeskUnreadNoteNavigation
{
    public static function getNavigationBadge(): ?string
    {
        $count = HelpdeskUnreadNoteTracker::unreadCountForAuthenticatedUser();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'verdeApple';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        $count = HelpdeskUnreadNoteTracker::unreadCountForAuthenticatedUser();

        if ($count < 1) {
            return null;
        }

        return $count === 1
            ? '1 ticket con nota sin revisar'
            : "{$count} tickets con notas sin revisar";
    }

    /**
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        if (! static::hasPage('index')) {
            return [];
        }

        $hasUnreadNotesBadge = static::getNavigationBadge() !== null;

        return [
            NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->parentItem(static::getNavigationParentItem())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(fn (): bool => original_request()->routeIs(static::getNavigationItemActiveRoutePattern()))
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->badgeTooltip(static::getNavigationBadgeTooltip())
                ->sort(static::getNavigationSort())
                ->url(static::getNavigationUrl())
                ->extraAttributes([
                    'class' => $hasUnreadNotesBadge
                        ? 'fi-helpdesk-nav-item fi-helpdesk-nav-item--has-unread-notes'
                        : 'fi-helpdesk-nav-item',
                ]),
        ];
    }
}
