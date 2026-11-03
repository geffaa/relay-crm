<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'current_company_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return BelongsToMany<Company, $this> */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    /** @return BelongsTo<Company, $this> */
    public function currentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }

    /**
     * Role of this user inside the given (or current) company: admin|manager|rep.
     */
    public function roleIn(?Company $company = null): ?string
    {
        $company ??= $this->currentCompany;

        if (! $company) {
            return null;
        }

        $membership = DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $this->id)
            ->first();

        return $membership?->role;
    }

    public function isAdmin(?Company $company = null): bool
    {
        return $this->roleIn($company) === 'admin';
    }

    public function isManager(?Company $company = null): bool
    {
        return in_array($this->roleIn($company), ['admin', 'manager'], true);
    }
}
