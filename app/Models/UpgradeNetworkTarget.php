<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agencia o agente elegido para cotizar un upgrade con visibilidad «Red seleccionada». La fila lleva
 * uno de los dos: `agency_id` o `agent_id`.
 *
 * @see \App\Support\Upgrades\UpgradeNetworkAudience
 */
class UpgradeNetworkTarget extends Model
{
    protected $table = 'upgrade_network_targets';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'upgrade_id',
        'agency_id',
        'agent_id',
        'created_by',
    ];

    /**
     * @return BelongsTo<Upgrade, $this>
     */
    public function upgrade(): BelongsTo
    {
        return $this->belongsTo(Upgrade::class);
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
