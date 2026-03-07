<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Filament\Panel\Concerns\HasAvatars;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasAvatars, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'is_admin',
        'is_agent',
        'is_agency',
        'is_doctor',
        'is_subagent',
        'is_patient',
        'is_designer',
        'is_business_admin',
        'is_superAdmin',
        'is_accountManagers',
        'code_agency',
        'link_agency',
        'link_agent',
        'agency_type',
        'departament',
        'birthday_date',
        'status',
        'updated_by',
        'created_by',
        'doctor_id',
        'phone',

    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'departament' => 'array',
        ];
    }

    public function getFilamentAvatarUrl(): ?string
    {
        $colaborador = RrhhColaborador::query()
            ->where('user_id', $this->getAuthIdentifier())
            ->first();

        if ($colaborador && filled($colaborador->avatar)) {
            $path = ltrim($colaborador->avatar, '/');

            if (! str_starts_with($path, 'http://') && ! str_starts_with($path, 'https://')) {
                if (Storage::disk('public')->exists($path)) {
                    return url('storage/'.$path);
                }
            } else {
                return $path;
            }
        }

        return 'https://ui-avatars.com/api/?name='.urlencode($this->name ?? 'U').'&color=FFFFFF&background=030712';
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    /**
     * Restriccion para acceso al panel administrativo
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return str_ends_with($this->email, '@tudrencasa.com') && $this->is_admin;
        }

        if ($panel->getId() === 'agents') {
            return $this->is_agent &&
                $this->status = 'ACTIVO';
        }

        if ($panel->getId() === 'master') {
            return $this->is_agency && $this->agency_type === 'MASTER' &&
                $this->status = 'ACTIVO';
        }

        if ($panel->getId() === 'general') {
            return $this->is_agency && $this->agency_type === 'GENERAL' &&
                $this->status = 'ACTIVO';
        }

        if ($panel->getId() === 'subagents') {
            return $this->is_subagent &&
                $this->status = 'ACTIVO';
        }

        if ($panel->getId() === 'telemedicina') {
            return in_array('TELEMEDICINA', $this->departament) == 1 &&
                    $this->status = 'ACTIVO';
        }

        if ($panel->getId() === 'marketing') {
            return in_array('MARKETING', $this->departament) == 1 &&
                    $this->status = 'ACTIVO';
        }

        if ($panel->getId() === 'business') {
            return str_ends_with($this->email, '@tudrencasa.com') &&
                     in_array('NEGOCIOS', $this->departament) == 1 &&
                     $this->status = 'ACTIVO';
        }

        if ($panel->getId() === 'administration') {
            return str_ends_with($this->email, '@tudrencasa.com') &&
                    in_array('ADMINISTRACION', $this->departament) == 1 &&
                    $this->status = 'ACTIVO';
        }

        if ($panel->getId() === 'operations') {
            return str_ends_with($this->email, '@tudrencasa.com') &&
                in_array('OPERACIONES', $this->departament) == 1 &&
                $this->status = 'ACTIVO';
        }

        return true;

    }

    /**
     * Permisos asignados directamente al usuario (tabla user_permissions).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Permission, $this>
     */
    public function permissions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')
            ->withPivot(['created_by', 'updated_by'])
            ->withTimestamps();
    }
}
