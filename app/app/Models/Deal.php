<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $company_id
 * @property int $pipeline_id
 * @property int $stage_id
 * @property int $contact_id
 * @property int|null $account_id
 * @property int|null $owner_id
 * @property string $title
 * @property string|null $value
 * @property string $currency
 * @property Carbon|null $expected_close
 * @property Carbon $stage_entered_at
 * @property string|null $lost_reason
 * @property Carbon|null $closed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Deal extends Model implements Auditable
{
    use AuditableTrait, BelongsToCompany;

    protected $fillable = [
        'pipeline_id', 'stage_id', 'contact_id', 'account_id', 'owner_id',
        'title', 'value', 'currency', 'expected_close', 'lost_reason',
        'stage_entered_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'expected_close' => 'date',
            'stage_entered_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /** @return BelongsTo<Pipeline, $this> */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return MorphMany<Activity, $this> */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /** @return MorphMany<Task, $this> */
    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'subject');
    }

    /**
     * Days the deal has sat in its current stage (fractional).
     */
    public function daysInStage(): float
    {
        return $this->stage_entered_at->diffInHours(now()) / 24;
    }

    public function isStale(): bool
    {
        $limit = $this->stage?->stale_after_days;

        if (! $limit || $this->stage->isClosed()) {
            return false;
        }

        return $this->stage_entered_at->lt(now()->subDays($limit));
    }

    public function isOpen(): bool
    {
        return $this->stage?->kind === 'open';
    }

    public function scopeStale(Builder $query): Builder
    {
        return $query->select('deals.*')
            ->join('stages', 'deals.stage_id', '=', 'stages.id')
            ->where('stages.kind', 'open')
            ->whereNotNull('stages.stale_after_days')
            ->whereRaw("deals.stage_entered_at < now() - (stages.stale_after_days * interval '1 day')");
    }
}
