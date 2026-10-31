<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un cambio del tema de Integracorp: quién, cuándo y de cuál a cuál.
 */
class SystemThemeChange extends Model
{
    protected $fillable = [
        'theme',
        'previous_theme',
        'changed_by_user_id',
        'changed_by_name',
        'ip_address',
    ];
}
