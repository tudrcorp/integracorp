<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tasa oficial BCV de un día publicado (Bs. por US$). Ver {@see \App\Support\BcvRateHistory}.
 */
class BcvHistoricalRate extends Model
{
    protected $table = 'bcv_official_rates';

    protected $fillable = ['rate_date', 'rate', 'source'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate_date' => 'date',
            'rate' => 'decimal:8',
        ];
    }
}
