<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Phone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $account_id
 * @property string $name
 * @property string|null $phone_e164
 * @property string|null $email
 * @property int|null $owner_id
 * @property string $source
 * @property array|null $tags
 * @property array|null $custom
 * @property int|null $score
 * @property Carbon|null $score_updated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class Contact extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'account_id', 'name', 'phone_e164', 'email', 'owner_id',
        'source', 'tags', 'custom', 'score', 'score_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'custom' => 'array',
            'score_updated_at' => 'datetime',
        ];
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

    /** @return HasMany<Deal, $this> */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /** @return HasMany<Conversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /** @return HasMany<LeadScore, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(LeadScore::class);
    }

    public function latestScore(): ?LeadScore
    {
        return $this->scores()->orderByDesc('created_at')->first();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $normalized = app(Phone::class)->toE164($term);

        return $query->where(function (Builder $q) use ($term, $normalized) {
            $q->where('name', 'ilike', "%{$term}%")
                ->orWhere('email', 'ilike', "%{$term}%");

            if ($normalized) {
                $q->orWhere('phone_e164', $normalized);
            } else {
                $digits = preg_replace('/\D+/', '', $term);

                if ($digits !== '' && $digits !== null) {
                    $q->orWhere('phone_e164', 'like', "%{$digits}%");
                }
            }
        });
    }
}
