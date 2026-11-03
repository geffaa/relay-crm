<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $currency
 * @property string $timezone
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Company extends Model
{
    protected $fillable = ['name', 'slug', 'currency', 'timezone'];

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    /** @return HasMany<Account, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /** @return HasMany<Contact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /** @return HasMany<Pipeline, $this> */
    public function pipelines(): HasMany
    {
        return $this->hasMany(Pipeline::class);
    }

    /** @return HasMany<WorkingHours, $this> */
    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHours::class);
    }

    public function defaultPipeline(): ?Pipeline
    {
        return $this->pipelines()->orderByDesc('is_default')->first();
    }
}
