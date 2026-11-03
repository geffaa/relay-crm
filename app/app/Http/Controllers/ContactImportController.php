<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Services\ContactService;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ContactImportController extends Controller
{
    private const CACHE_PREFIX = 'relay:import:';

    public function show(): Response
    {
        return Inertia::render('Contacts/Import');
    }

    /**
     * Step 1 — parse the CSV and preview every row with duplicate flags.
     * Nothing is written yet.
     */
    public function preview(Request $request, Phone $phone)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:2048', 'mimes:csv,txt'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if ($handle === false) {
            return back()->withErrors(['file' => 'Could not read the file.']);
        }

        // Header row: detect column positions (name/phone/email are required-ish).
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), fgetcsv($handle) ?: []);
        $idx = function (array $candidates) use ($header): ?int {
            foreach ($candidates as $candidate) {
                $found = array_search($candidate, $header, true);
                if ($found !== false) {
                    return $found;
                }
            }

            return null;
        };

        $nameIdx = $idx(['name', 'nama', 'full name']);
        $phoneIdx = $idx(['phone', 'phone number', 'telepon', 'mobile', 'wa']);
        $emailIdx = $idx(['email', 'e-mail', 'surel']);

        if ($nameIdx === null || $phoneIdx === null) {
            fclose($handle);

            return back()->withErrors([
                'file' => 'CSV must have "name" and "phone" columns (email optional).',
            ]);
        }

        $company = app('relay.company');
        $contactService = app(ContactService::class);

        // Existing identifiers for duplicate detection.
        $existingPhones = Contact::forCompany($company)->whereNotNull('phone_e164')->pluck('phone_e164')->all();
        $existingEmails = Contact::forCompany($company)->whereNotNull('email')->pluck('email')->all();

        $seenPhones = [];
        $seenEmails = [];
        $rows = [];
        $line = 1;

        while (($raw = fgetcsv($handle)) !== false) {
            $line++;
            $name = trim((string) ($raw[$nameIdx] ?? ''));
            $rawPhone = trim((string) ($raw[$phoneIdx] ?? ''));
            $email = strtolower(trim((string) ($raw[$emailIdx] ?? '')));

            if ($name === '' && $rawPhone === '' && $email === '') {
                continue;
            }

            $e164 = $phone->toE164($rawPhone);

            $duplicates = [];
            if ($e164 !== null) {
                if (in_array($e164, $existingPhones, true) || isset($seenPhones[$e164])) {
                    $duplicates[] = 'phone';
                }
                $seenPhones[$e164] = true;
            }
            if ($email !== '') {
                if (in_array($email, $existingEmails, true) || isset($seenEmails[$email])) {
                    $duplicates[] = 'email';
                }
                $seenEmails[$email] = true;
            }
            if ($rawPhone !== '' && $e164 === null) {
                $duplicates[] = 'invalid_phone';
            }

            $rows[] = [
                'line' => $line,
                'name' => $name ?: 'WhatsApp lead',
                'phone' => $e164,
                'email' => $email ?: null,
                'duplicates' => $duplicates,
            ];

            if (count($rows) >= 500) {
                break; // MVP cap.
            }
        }
        fclose($handle);

        $key = self::CACHE_PREFIX.uniqid().'_'.auth()->id();
        Cache::put($key, [
            'company_id' => $company->id,
            'user_id' => auth()->id(),
            'rows' => $rows,
        ], now()->addMinutes(15));

        return back()->with([
            'importKey' => $key,
            'importRows' => $rows,
        ]);
    }

    /**
     * Step 2 — import. Rows flagged duplicate are skipped unless forced.
     */
    public function confirm(Request $request, ContactService $contactService)
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'skip_duplicates' => ['nullable', 'boolean'],
        ]);

        $payload = Cache::pull($data['key']);

        if (! $payload || (int) $payload['user_id'] !== auth()->id()) {
            return back()->withErrors(['key' => 'Import preview expired — upload the file again.']);
        }

        // Tenant guard: preview must belong to the current company.
        if ((int) $payload['company_id'] !== app('relay.company')->id) {
            abort(403);
        }

        $company = app('relay.company');
        $skipDuplicates = $data['skip_duplicates'] ?? true;

        $imported = 0;
        $skipped = 0;

        foreach ($payload['rows'] as $row) {
            if ($skipDuplicates && $row['duplicates'] !== []) {
                $skipped++;

                continue;
            }

            $contactService->findOrCreate(
                $company,
                $row['name'],
                $row['phone'],
                $row['email'],
                'manual',
                $request->user(),
            );
            $imported++;
        }

        return redirect()->route('contacts.index')
            ->with('success', "Imported {$imported} contact(s), skipped {$skipped} duplicate(s).");
    }
}
