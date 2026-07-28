<?php

declare(strict_types=1);

namespace App\Support\ProjectManagement;

use App\Models\HelpDesk;
use App\Support\HelpdeskTaskStatusOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class InProgressHelpDeskOptions
{
    public const SEARCH_LIMIT = 40;

    /**
     * @return array<int, string>
     */
    public static function search(string $search = '', int $limit = self::SEARCH_LIMIT): array
    {
        $query = self::baseQuery()
            ->select(['id', 'description'])
            ->orderByDesc('id')
            ->limit($limit);

        $term = trim($search);

        if ($term !== '') {
            $query->where(function (Builder $builder) use ($term): void {
                if (preg_match('/^#?(\d+)$/', $term, $matches) === 1) {
                    $builder->where('id', (int) $matches[1]);

                    return;
                }

                $builder
                    ->where('description', 'like', '%'.$term.'%')
                    ->orWhere('id', 'like', $term.'%');
            });
        }

        return $query
            ->get()
            ->mapWithKeys(fn (HelpDesk $ticket): array => [
                $ticket->id => self::label($ticket),
            ])
            ->all();
    }

    public static function labelForId(int $id): ?string
    {
        $ticket = self::baseQuery()
            ->select(['id', 'description'])
            ->find($id);

        return $ticket instanceof HelpDesk ? self::label($ticket) : null;
    }

    public static function label(HelpDesk $ticket): string
    {
        $description = trim(strip_tags((string) $ticket->description));

        if ($description === '') {
            return '#'.$ticket->id;
        }

        return '#'.$ticket->id.' — '.Str::limit($description, 100);
    }

    /**
     * @return Builder<HelpDesk>
     */
    private static function baseQuery(): Builder
    {
        return HelpDesk::query()
            ->where('status', HelpdeskTaskStatusOptions::STATUS_IN_PROGRESS);
    }
}
