<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zona de cobertura de un prospecto de captación. Sin ciudad, la zona abarca todo el estado.
 */
class ProspectAgentCoverageZone extends Model
{
    /** @use HasFactory<\Database\Factories\ProspectAgentCoverageZoneFactory> */
    use HasFactory;

    protected $table = 'prospect_agent_coverage_zones';

    protected $fillable = [
        'prospect_agent_id',
        'classification',
        'service_type',
        'state_id',
        'city_id',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state_id' => 'integer',
            'city_id' => 'integer',
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

    /**
     * @return BelongsTo<State, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
