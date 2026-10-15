<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Página HTML subida al centro de QR y publicada en `/p/{slug}`, aislada del
 * sistema. Cada subida es una versión; la vigente es la que se sirve.
 */
class QrHostedPage extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'current_version_id',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_version_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<QrHostedPageVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(QrHostedPageVersion::class, 'current_version_id');
    }

    /**
     * @return HasMany<QrHostedPageVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(QrHostedPageVersion::class)->orderByDesc('version');
    }
}
