<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public $connection = 'central';

    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'role_id',
        'admin_id',
        'is_active',
        'tenant_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    // Relaciones
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(User::class, 'admin_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function createdSubscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'created_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function hasTenant(): bool
    {
        return ! is_null($this->tenant_id);
    }

    // Helpers de rol
    public function isSuperAdmin(): bool
    {
        return $this->role->slug === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return $this->role->slug === 'admin';
    }

    public function isEmployee(): bool
    {
        return $this->role->slug === 'employee';
    }

    // Suscripción activa y vigente
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->where('is_active', true)
            ->whereDate('end_date', '>=', today())
            ->latest()
            ->first();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription() !== null;
    }

    public function activeSubscriptionRelation(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('is_active', true)
            ->whereDate('end_date', '>=', today())
            ->latest('id');
    }

    // Verificar permiso
    public function hasPermission(string $permissionSlug, string $modelSlug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return ModelPermission::where('role_id', $this->role_id)
            ->whereHas('permission', fn ($q) => $q->where('slug', $permissionSlug))
            ->whereHas('modelEntity', fn ($q) => $q->where('slug', $modelSlug))
            ->exists();
    }
}
