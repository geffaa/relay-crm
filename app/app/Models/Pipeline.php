<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property bool $is_default
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Pipeline extends Model
{
    use BelongsToCompany;

    protected $fillable = ['name', 'is_default'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /** @return HasMany<Stage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class)->orderBy('position');
    }

    /** @return HasMany<Deal, $this> */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
