<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\HelpdeskBusinessTicketCreationGate;
use Illuminate\Database\Eloquent\Model;

class HelpdeskGroup extends Model
{
    protected $table = 'helpdesk_groups';

    protected $fillable = [
        'name',
        'status',
        'total_tickets_assigned',
        'team_members',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'team_members' => 'array',
        'total_tickets_assigned' => 'integer',
    ];

    public function isActive(): bool
    {
        return strtoupper((string) $this->status) === 'ACTIVO';
    }

    /**
     * @return list<int>
     */
    public function memberColaboradorIds(): array
    {
        $members = is_array($this->team_members) ? $this->team_members : [];

        return array_values(array_unique(array_filter(array_map(
            static function (mixed $member): int {
                if (! is_array($member)) {
                    return 0;
                }

                return (int) ($member['id'] ?? 0);
            },
            $members
        ), static fn (int $id): bool => $id > 0)));
    }

    public function ticketsCreatedCount(): int
    {
        $creatorNames = HelpdeskBusinessTicketCreationGate::creatorNamesForGroup($this);

        if ($creatorNames === []) {
            return 0;
        }

        return HelpDesk::query()
            ->whereIn('created_by', $creatorNames)
            ->count();
    }
}
