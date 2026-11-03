<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property string $kind
 * @property string $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property float $cost_usd
 * @property string $status
 * @property array|null $meta
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AiRun extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'kind', 'model', 'input_tokens', 'output_tokens', 'cost_usd', 'status', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
