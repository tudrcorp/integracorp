<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Vista guardada del modo Presentar: desde qué puesto se dibuja, cuántos niveles
 * y qué datos lleva cada tarjeta.
 *
 * @property int $id
 * @property string $name
 * @property string|null $root_position_key
 * @property int|null $depth
 * @property list<string>|null $card_fields
 * @property bool $show_vacancies
 */
class OrgSavedView extends Model
{
    protected $table = 'org_saved_views';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'root_position_key',
        'depth',
        'card_fields',
        'show_vacancies',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'card_fields' => 'array',
            'show_vacancies' => 'boolean',
            'depth' => 'integer',
        ];
    }
}
