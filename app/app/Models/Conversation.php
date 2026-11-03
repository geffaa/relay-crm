<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property int $contact_id
 * @property int $whatsapp_number_id
 * @property int|null $assignee_id
 * @property string $status
 * @property Carbon|null $last_inbound_at
 * @property Carbon|null $last_message_at
 * @property int $unread_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Conversation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'contact_id', 'whatsapp_number_id', 'assignee_id', 'status',
        'last_inbound_at', 'last_message_at', 'unread_count',
    ];

    protected function casts(): array
    {
        return [
            'last_inbound_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withTrashed();
    }

    /** @return BelongsTo<WhatsAppNumber, $this> */
    public function whatsappNumber(): BelongsTo
    {
        return $this->belongsTo(WhatsAppNumber::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return HasMany<AiSummary, $this> */
    public function summaries(): HasMany
    {
        return $this->hasMany(AiSummary::class);
    }

    public function latestSummary(): ?AiSummary
    {
        return $this->summaries()->orderByDesc('created_at')->first();
    }

    /**
     * Meta service window: true when a free-form reply is allowed because the
     * customer messaged within the configured window (default 24h).
     */
    public function canSendFreeform(): bool
    {
        if (! $this->last_inbound_at) {
            return false;
        }

        $hours = (int) config('services.whatsapp.freeform_window_hours', 24);

        return $this->last_inbound_at->gt(now()->subHours($hours));
    }

    public function windowClosesAt(): ?CarbonInterface
    {
        if (! $this->last_inbound_at) {
            return null;
        }

        $hours = (int) config('services.whatsapp.freeform_window_hours', 24);

        return $this->last_inbound_at->copy()->addHours($hours);
    }

    public function isUnreplied(): bool
    {
        if ($this->status === 'closed' || $this->last_inbound_at === null) {
            return false;
        }

        // Unreplied = no outbound message after the last inbound one.
        $replied = Message::where('conversation_id', $this->id)
            ->where('direction', 'out')
            ->where('created_at', '>', $this->last_inbound_at)
            ->exists();

        return ! $replied;
    }

    public function scopeView(Builder $query, string $view): Builder
    {
        return match ($view) {
            'unreplied' => $query->where('status', '!=', 'closed')
                ->whereNotNull('last_inbound_at')
                ->whereNotExists(function ($q) {
                    $q->selectRaw(1)
                        ->from('messages')
                        ->whereColumn('messages.conversation_id', 'conversations.id')
                        ->where('messages.direction', 'out')
                        ->whereColumn('messages.created_at', '>', 'conversations.last_inbound_at');
                }),
            'mine' => $query->where('assignee_id', auth()->id()),
            default => $query,
        };
    }
}
