<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $assignee_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $title
 * @property string $kind
 * @property Carbon|null $due_at
 * @property Carbon|null $done_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Task extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'assignee_id', 'subject_type', 'subject_id', 'title', 'kind', 'due_at', 'done_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'done_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('done_at');
    }
}
