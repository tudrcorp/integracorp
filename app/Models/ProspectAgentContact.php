<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectAgentContact extends Model
{
    /** @use HasFactory<\Database\Factories\ProspectAgentContactFactory> */
    use HasFactory;

    protected $table = 'prospect_agent_contacts';

    protected $fillable = [
        'prospect_agent_id',
        'name',
        'position',
        'phone',
        'email',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ProspectAgent, $this>
     */
    public function prospectAgent(): BelongsTo
    {
        return $this->belongsTo(ProspectAgent::class);
    }
}
