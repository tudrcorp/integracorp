<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $table = 'permissions';

    protected $fillable = [
        'name',
        'slug',
        'module',
        'created_by',
        'updated_by',
    ];

    /**
     * Usuarios que tienen este permiso asignado.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<User, $this>
     */
    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_permissions')
            ->withPivot(['created_by', 'updated_by'])
            ->withTimestamps();
    }
}
