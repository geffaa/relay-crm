<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $conversation_id
 * @property array $points
 * @property string|null $next_step
 * @property float|null $confidence
 * @property string $model
 * @property string $prompt_version
 * @property int|null $covers_until_message_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AiSummary extends Model
{
    protected $fillable = [
        'conversation_id', 'points', 'next_step', 'confidence',
        'model', 'prompt_version', 'covers_until_message_id',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'array',
            'confidence' => 'float',
        ];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
