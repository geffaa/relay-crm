<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Company-scoped bearer token for the public API (/api/v1). Only a sha256 hash is stored. */
class ApiKey extends Model
{
    protected $fillable = ['name', 'key_hash', 'prefix', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /** @return array{0: ApiKey, 1: string} the model and the plain token (shown once) */
    public static function issue(Company $company, string $name): array
    {
        $token = 'rly_'.Str::random(40);
        $key = new self(['name' => $name, 'key_hash' => hash('sha256', $token), 'prefix' => substr($token, 0, 8)]);
        $key->company_id = $company->id;
        $key->save();

        return [$key, $token];
    }
}
