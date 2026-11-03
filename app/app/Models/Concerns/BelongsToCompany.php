<?php

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant scoping used by every per-company model. The current company is
 * bound in the container by EnsureCompany middleware; console code, seeders
 * and tests that set the binding explicitly get scoped queries, everything
 * else (migrations, queue workers without context) sees all rows.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $company = app()->bound('relay.company') ? app('relay.company') : null;

            if ($company) {
                $builder->where($builder->getModel()->getTable().'.company_id', $company->id);
            }
        });

        static::creating(function ($model) {
            if (! $model->company_id && app()->bound('relay.company')) {
                $model->company_id = app('relay.company')->id;
            }
        });
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany(Builder $query, int|Company $company): Builder
    {
        return $query->withoutGlobalScope('company')
            ->where($this->getTable().'.company_id', $company instanceof Company ? $company->id : $company);
    }
}
