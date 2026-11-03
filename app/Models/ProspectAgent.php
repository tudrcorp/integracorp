<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProspectAgent extends Model
{
    use SoftDeletes;

    protected $table = 'prospect_agents';

    protected $fillable = [
        'name',
        'type',
        'phone_1',
        'phone_2',
        'email',
        'address',
        'website',
        'social_networks',
        'state_id',
        'city_id',
        'country_id',
        'status',
        'created_by',
        'updated_by',
        'classification',
        'instagram',
        'initial_observ',
        'reference_by',
        'initial_observ',
        'instagram',
        'classification',

    ];

    public function prospect_agent_observations()
    {
        return $this->hasMany(ProspectAgentObservation::class)->orderBy('created_at', 'desc');
    }

    public function prospect_agent_tasks()
    {
        return $this->hasMany(ProspectAgentTask::class)->orderBy('created_at', 'desc');
    }

    /**
     * @return HasMany<ProspectAgentContact, $this>
     */
    public function prospectAgentContacts(): HasMany
    {
        return $this->hasMany(ProspectAgentContact::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ProspectAgentBranch, $this>
     */
    public function prospectAgentBranches(): HasMany
    {
        return $this->hasMany(ProspectAgentBranch::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ProspectAgentCoverageZone, $this>
     */
    public function prospectAgentCoverageZones(): HasMany
    {
        return $this->hasMany(ProspectAgentCoverageZone::class)->orderBy('sort_order');
    }

    /**
     * Tickets del Helpdesk creados sobre este prospecto («Ticket de trabajo»).
     *
     * @return BelongsToMany<HelpDesk, $this>
     */
    public function helpdeskTickets(): BelongsToMany
    {
        return $this->belongsToMany(HelpDesk::class, 'help_desk_prospect_agent')
            ->withTimestamps()
            ->latest('help_desks.id');
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function created_by()
    {
        return $this->belongsTo(User::class);
    }

    public function updated_by()
    {
        return $this->belongsTo(User::class);
    }

    public function reference_by()
    {
        return $this->belongsTo(User::class);
    }
}
