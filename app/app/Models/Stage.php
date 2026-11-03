<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $pipeline_id
 * @property string $name
 * @property int $position
 * @property int|null $stale_after_days
 * @property string $kind
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Stage extends Model
{
    protected $fillable = ['pipeline_id', 'name', 'position', 'stale_after_days', 'kind'];

    /** @return BelongsTo<Pipeline, $this> */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /** @return HasMany<Deal, $this> */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /** Fixed win probability by position, used for weighted pipeline value (closed stages are not forecast). */
    public function probability(): float
    {
        return match (true) {
            $this->kind !== 'open' => 0.0,
            $this->position <= 1 => 0.10,
            $this->position === 2 => 0.25,
            $this->position === 3 => 0.45,
            default => 0.65,
        };
    }

    public function isClosed(): bool
    {
        return $this->kind !== 'open';
    }
}
