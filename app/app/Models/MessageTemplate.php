<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $whatsapp_number_id
 * @property string $name
 * @property string $language
 * @property string $category
 * @property string $body
 * @property array|null $variables
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MessageTemplate extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'whatsapp_number_id', 'name', 'language', 'category', 'body', 'variables', 'status',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
        ];
    }

    /** @return BelongsTo<WhatsAppNumber, $this> */
    public function whatsappNumber(): BelongsTo
    {
        return $this->belongsTo(WhatsAppNumber::class);
    }
}
