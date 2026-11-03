<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $company_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $type
 * @property int|null $actor_id
 * @property array|null $body
 * @property Carbon $occurred_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Activity extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'subject_type', 'subject_id', 'type', 'actor_id', 'body', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'body' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Stamp company_id explicitly (never mass-assignable from user input).
     */
    public static function record(
        int $companyId,
        Model $subject,
        string $type,
        ?int $actorId,
        array $body,
        ?CarbonInterface $occurredAt = null,
    ): self {
        $activity = new self([
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'type' => $type,
            'actor_id' => $actorId,
            'body' => $body,
            'occurred_at' => $occurredAt ?? now(),
        ]);
        $activity->company_id = $companyId;
        $activity->save();

        return $activity;
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
