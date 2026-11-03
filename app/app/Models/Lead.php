<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string|null $phone_e164
 * @property string|null $email
 * @property string $source
 * @property string $status
 * @property int|null $owner_id
 * @property string|null $notes
 * @property int|null $converted_contact_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Lead extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'name', 'phone_e164', 'email', 'source', 'status', 'owner_id', 'notes', 'converted_contact_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<Contact, $this> */
    public function convertedContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'converted_contact_id');
    }
}
