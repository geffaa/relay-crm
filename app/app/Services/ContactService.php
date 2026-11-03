<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\LeadScore;
use App\Models\Task;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ContactService
{
    public function __construct(private readonly Phone $phone) {}

    /**
     * Find a contact by E.164 phone or email, or create a new one. Used by the
     * WhatsApp ingest pipeline so the same number never creates duplicates.
     */
    public function findOrCreate(
        Company $company,
        ?string $name,
        ?string $phone,
        ?string $email = null,
        string $source = 'whatsapp',
        ?User $owner = null,
    ): Contact {
        $phoneE164 = $this->phone->toE164($phone);
        $email = $email ? strtolower(trim($email)) : null;

        $query = Contact::forCompany($company)->withTrashed();

        $contact = null;
        if ($phoneE164) {
            $contact = (clone $query)->where('phone_e164', $phoneE164)->first();
        }
        if (! $contact && $email) {
            $contact = (clone $query)->where('email', $email)->first();
        }

        if ($contact) {
            $contact->restore();
            // Backfill the name when we only knew the number before.
            if ($name && $contact->name === 'WhatsApp lead') {
                $contact->update(['name' => $name]);
            }

            return $contact;
        }

        // company_id is never mass-assignable; stamp it explicitly.
        return Contact::forceCreate([
            'company_id' => $company->id,
            'name' => $name ?: 'WhatsApp lead',
            'phone_e164' => $phoneE164,
            'email' => $email,
            'source' => $source,
            'owner_id' => $owner?->id,
        ]);
    }

    /**
     * Merge $duplicate into $primary: deals, conversations, timeline, tasks and
     * scores move over; empty fields on the primary are filled from it. Audited.
     */
    public function merge(Contact $primary, Contact $duplicate): Contact
    {
        Gate::authorize('manage-contacts');

        return DB::transaction(function () use ($primary, $duplicate) {
            $mergedName = $duplicate->name;

            // Bulk moves bypass Eloquent events/timestamps (lead_scores is
            // append-only and has no updated_at column).
            Deal::withoutGlobalScopes()
                ->getQuery()
                ->where('company_id', $primary->company_id)
                ->where('contact_id', $duplicate->id)
                ->update(['contact_id' => $primary->id]);

            Conversation::withoutGlobalScopes()
                ->getQuery()
                ->where('company_id', $primary->company_id)
                ->where('contact_id', $duplicate->id)
                ->update(['contact_id' => $primary->id]);

            foreach ([LeadScore::class => 'contact_id', Task::class => 'subject_id', Activity::class => 'subject_id'] as $model => $column) {
                $query = $model::withoutGlobalScopes()->getQuery();

                if ($model !== LeadScore::class) {
                    $query->where('company_id', $primary->company_id);
                }
                if ($model !== LeadScore::class && $column === 'subject_id') {
                    $query->where('subject_type', Contact::class);
                }

                $query->where($column, $duplicate->id)->update([$column => $primary->id]);
            }

            // Remove the duplicate first so copying its unique fields (email,
            // phone) onto the primary cannot violate the tenant-unique keys.
            $duplicate->forceDelete();

            foreach (['account_id', 'phone_e164', 'email', 'tags', 'custom'] as $field) {
                if (blank($primary->{$field}) && ! blank($duplicate->{$field})) {
                    $primary->{$field} = $duplicate->{$field};
                }
            }

            if (is_null($primary->score) && ! is_null($duplicate->score)) {
                $primary->score = $duplicate->score;
                $primary->score_updated_at = $duplicate->score_updated_at;
            }

            $primary->save();

            Activity::record($primary->company_id, $primary, 'contact_merged', auth()->id(), [
                'merged_contact_id' => $duplicate->id,
                'merged_name' => $mergedName,
            ]);

            return $primary->refresh();
        });
    }
}
