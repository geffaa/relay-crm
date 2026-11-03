<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property int $weekday
 * @property Carbon $start_time
 * @property Carbon $end_time
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WorkingHours extends Model
{
    use BelongsToCompany;

    protected $fillable = ['weekday', 'start_time', 'end_time'];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];
}
