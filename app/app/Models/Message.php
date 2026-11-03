<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $conversation_id
 * @property string|null $wamid
 * @property string $direction
 * @property string $type
 * @property string|null $body
 * @property array|null $media
 * @property string $status
 * @property array|null $error
 * @property int|null $sent_by
 * @property Carbon $created_at
 */
class Message extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'conversation_id', 'wamid', 'direction', 'type', 'body',
        'media', 'status', 'error', 'sent_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'media' => 'array',
            'error' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function isInbound(): bool
    {
        return $this->direction === 'in';
    }
}
