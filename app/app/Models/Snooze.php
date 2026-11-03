<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * "Hide this from my Today queue until later". Per user, so a teammate still sees the item.
 *
 * @property int $id
 * @property int $user_id
 * @property string $subject_type
 * @property int $subject_id
 * @property Carbon $until
 */
class Snooze extends Model
{
    use BelongsToCompany;

    protected $fillable = ['user_id', 'subject_type', 'subject_id', 'until'];

    protected function casts(): array
    {
        return ['until' => 'datetime'];
    }

    /** @return array<int, int> ids of subjects of this type that the user has snoozed right now */
    public static function activeIds(int $userId, string $type): array
    {
        return static::query()
            ->where('user_id', $userId)->where('subject_type', $type)->where('until', '>', now())
            ->pluck('subject_id')->all();
    }

    /** @param Builder<Snooze> $query */
    public function scopeFor(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
