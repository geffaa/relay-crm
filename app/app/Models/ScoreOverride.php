<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $contact_id
 * @property int $user_id
 * @property int|null $from
 * @property int $to
 * @property string $reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ScoreOverride extends Model
{
    protected $fillable = ['contact_id', 'user_id', 'from', 'to', 'reason'];

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
