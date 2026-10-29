<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Servicio que incluye un upgrade (texto libre del analista).
 */
class UpgradeService extends Model
{
    protected $table = 'upgrade_services';

    protected $fillable = [
        'upgrade_id',
        'name',
        'description',
        'sort',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Upgrade, $this>
     */
    public function upgrade(): BelongsTo
    {
        return $this->belongsTo(Upgrade::class);
    }
}
