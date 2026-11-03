<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property string $phone_number_id
 * @property string $display_phone
 * @property string|null $waba_id
 * @property string|null $access_token
 * @property string $mode
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WhatsAppNumber extends Model
{
    use BelongsToCompany;

    protected $table = 'whatsapp_numbers';

    protected $fillable = ['phone_number_id', 'display_phone', 'waba_id', 'access_token', 'mode'];

    protected $casts = [
        'access_token' => 'encrypted',
    ];

    /** @return HasMany<Conversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /** @return HasMany<MessageTemplate, $this> */
    public function templates(): HasMany
    {
        return $this->hasMany(MessageTemplate::class);
    }

    public function isLive(): bool
    {
        return $this->mode === 'live';
    }
}
