<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Access\Models\ActivityLog;
use App\Modules\Company\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Database\Factories\UserFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles {
        assignRole as private assignRoleFromPackage;
        syncRoles as private syncRolesFromPackage;
    }

    use Notifiable;

    public function assignRole(...$roles)
    {
        $this->assertRolesBelongToCurrentCompany($roles);

        return $this->assignRoleFromPackage(...$roles);
    }

    public function syncRoles(...$roles)
    {
        $this->assertRolesBelongToCurrentCompany($roles);

        return $this->syncRolesFromPackage(...$roles);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    private function assertRolesBelongToCurrentCompany(array $roles): void
    {
        $companyId = app(CurrentCompany::class)->id();
        if ((string) $this->getRawOriginal('company_id') !== (string) $companyId) {
            throw new AuthorizationException('Roles cannot be assigned across companies.');
        }

        foreach (collect($roles)->flatten() as $role) {
            if ($role instanceof Model
                && $role->getAttribute('company_id') !== null
                && (string) $role->getRawOriginal('company_id') !== (string) $companyId) {
                throw new AuthorizationException('Roles cannot be assigned across companies.');
            }
        }
    }
}
