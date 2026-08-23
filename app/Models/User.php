<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_CUSTOMER = 'customer';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
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

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function canAccessAdmin(): bool
    {
        return $this->isAdmin();
    }

    /** Kept separate from admin access so a cashier role can be added safely later. */
    public function canAccessPos(): bool
    {
        return $this->isAdmin();
    }

    public function canRefundPosSales(): bool
    {
        return $this->isAdmin();
    }

    public function installmentApplications(): HasMany
    {
        return $this->hasMany(InstallmentApplication::class);
    }

    public function installmentAccounts(): HasMany
    {
        return $this->hasMany(InstallmentAccount::class);
    }

    public function posSales(): HasMany
    {
        return $this->hasMany(Order::class, 'cashier_id');
    }
}
