<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Diseño de la propuesta económica de uno o varios planes: tema de la página de cálculos
 * (colores, tipografía, logo, textos) e imágenes de las páginas fijas.
 *
 * Nunca se borra: se inactiva, porque cada propuesta emitida apunta a una de sus versiones.
 * La escritura pasa siempre por {@see \App\Support\Quotes\Design\QuoteDesignPersistence}.
 */
class QuoteDesign extends Model
{
    public const STATUS_ACTIVE = 'ACTIVO';

    public const STATUS_INACTIVE = 'INACTIVO';

    /** Carpeta del disco `public` donde viven las imágenes y fuentes de los diseños. */
    public const DIRECTORY = 'quote-designs';

    protected $table = 'quote_designs';

    protected $fillable = [
        'name',
        'description',
        'status',
        'theme',
        'pages_before',
        'pages_after',
        'logo_path',
        'font_regular_path',
        'font_bold_path',
        'font_mono_path',
        'source_html_path',
        'version',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'theme' => 'array',
            'pages_before' => 'array',
            'pages_after' => 'array',
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Plan, $this>
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'quote_design_plan')->withTimestamps();
    }

    /**
     * @return HasMany<QuoteDesignVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(QuoteDesignVersion::class);
    }

    /**
     * @param  Builder<QuoteDesign>  $query
     * @return Builder<QuoteDesign>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
