<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $contact_id
 * @property int $score
 * @property array $reasons
 * @property int $structured_part
 * @property int $ai_adjustment
 * @property string|null $model
 * @property Carbon $created_at
 */
class LeadScore extends Model
{
    // Append-only scoring history: no updated_at column.
    public const UPDATED_AT = null;

    protected $fillable = [
        'contact_id', 'score', 'reasons', 'structured_part', 'ai_adjustment', 'model',
    ];

    protected function casts(): array
    {
        return [
            'reasons' => 'array',
        ];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Top reasons sorted by weight, as shown in the UI (always >= 3 when present).
     */
    public function topReasons(int $limit = 3): array
    {
        $reasons = collect($this->reasons ?? [])
            ->sortByDesc('weight')
            ->take($limit)
            ->values()
            ->all();

        return $reasons;
    }
}
