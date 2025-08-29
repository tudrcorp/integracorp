<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Panel;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Notifications\Notifiable;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel\Concerns\HasAvatars;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasAvatars;

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
        'code_agency',
        'link_agency',
        'link_agent',
        'agency_type',
        'departament',
        
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
        ];
    }

    public function getFilamentAvatarUrl(): ?string
    {
        $this->avatar = 'https://ui-avatars.com/api/?name=' . $this->name . '&color=FFFFFF&background=030712';
        return $this->avatar; // Or any other logic to get the avatar URL
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
            return $this->is_agent;
        }

        if ($panel->getId() === 'master') {
            return $this->is_agency && $this->agency_type === 'MASTER';
        }

        if ($panel->getId() === 'general') {
            return $this->is_agency && $this->agency_type === 'GENERAL';
        }

        if ($panel->getId() === 'subagents') {
            return $this->is_subagent;
        }

        if ($panel->getId() === 'telemedicina') {
            return $this->is_doctor;
        }

        if ($panel->getId() === 'marketing') {
            return $this->is_designer;
        }

        return true;

    }

    // protected static function booted(): void
    // {
    //     static::creating(function (User $user) {
    //         $user->is_admin = 'user';
    //     });
    // }
}