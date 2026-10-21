<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un cambio de unidad de negocio específica hecho a mano sobre un afiliado
 * (individual o corporativo). Solo se inserta.
 *
 * @see \App\Support\Affiliations\AffiliateSpecificBusinessUnitChanger
 */
class AffiliateBusinessUnitChange extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'affiliate_business_unit_changes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'affiliate_type',
        'affiliate_id',
        'affiliation_id',
        'batch_id',
        'before_value',
        'after_value',
        'inherited',
        'note',
        'telemedicine_patient_ids',
        'actor_id',
        'actor_name',
        'ip',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inherited' => 'boolean',
            'telemedicine_patient_ids' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
