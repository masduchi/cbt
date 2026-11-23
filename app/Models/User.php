<?php

namespace App\Models;

use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements CanResetPasswordContract
{
    use CanResetPassword;
    use Notifiable;

    protected $table = 'users';

    public $timestamps = false;

    protected $fillable = [
        'ip_address',
        'username',
        'password',
        'email',
        'created_on',
        'last_login',
        'active',
        'must_change_password',
        'first_name',
        'last_name',
        'company',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'activation_code',
        'forgotten_password_code',
        'remember_code',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'active' => 'integer',
            'must_change_password' => 'boolean',
            'created_on' => 'integer',
            'last_login' => 'integer',
        ];
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'users_groups', 'user_id', 'group_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class, 'id_user');
    }

    public function guru(): HasOne
    {
        return $this->hasOne(Guru::class, 'id_user');
    }

    public function getRoleAttribute(): string
    {
        return $this->groups->first()?->name ?? 'siswa';
    }

    public function getNamaAttribute(): string
    {
        return $this->profile?->nama_lengkap ?: trim($this->first_name.' '.$this->last_name) ?: $this->username;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        $foto = $this->profile?->foto;

        return $foto ? Storage::disk('public')->url($foto) : null;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isGuru(): bool
    {
        return $this->role === 'guru';
    }

    public function isSiswa(): bool
    {
        return $this->role === 'siswa';
    }

    public function isOrangtua(): bool
    {
        return $this->role === 'orangtua';
    }

    public function waliSiswa(): HasMany
    {
        return $this->hasMany(WaliSiswa::class, 'id_user');
    }
}
