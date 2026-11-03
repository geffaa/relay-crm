<?php

namespace Tests\Feature;

use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ContactImportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('contacts.csv', $content);
    }

    public function test_preview_flags_duplicates_and_invalid_phones_without_writing(): void
    {
        Contact::forceCreate([
            'company_id' => $this->company->id,
            'name' => 'Existing Person',
            'phone_e164' => '+6281200000001',
            'source' => 'manual',
        ]);

        $csv = "name,phone,email\n".
            "Dewi,+6281200000001,dewi@x.test\n".          // duplicate phone vs existing
            "Sari,081200000002,sari@x.test\n".            // valid, normalizable
            "Bimo,081200000002,\n".                       // duplicate within file (same phone)
            "Tono,12345,tono@x.test\n".                   // invalid phone
            "Maya,+6281200000099,maya@x.test\n";          // clean

        $this->actingAs($this->rep)
            ->post('/contacts/import', ['file' => $this->csv($csv)])
            ->assertRedirect();

        $rows = session('importRows');
        $this->assertCount(5, $rows);

        $this->assertSame(['phone'], $rows[0]['duplicates']);
        $this->assertSame([], $rows[1]['duplicates']);
        $this->assertSame('+6281200000002', $rows[1]['phone']);
        $this->assertSame(['phone'], $rows[2]['duplicates'], 'Same number inside the file is flagged');
        $this->assertSame(['invalid_phone'], $rows[3]['duplicates']);
        $this->assertSame([], $rows[4]['duplicates']);

        // Nothing written yet.
        $this->assertSame(1, Contact::count());
    }

    public function test_confirm_imports_clean_rows_and_skips_flagged(): void
    {
        Contact::forceCreate([
            'company_id' => $this->company->id,
            'name' => 'Existing Person',
            'phone_e164' => '+6281200000001',
            'source' => 'manual',
        ]);

        $csv = "name,phone,email\n".
            "Dewi,+6281200000001,dewi@x.test\n".
            "Sari,081200000002,sari@x.test\n".
            "Maya,+6281200000099,maya@x.test\n";

        $this->actingAs($this->rep)->post('/contacts/import', ['file' => $this->csv($csv)]);
        $key = session('importKey');

        $this->actingAs($this->rep)
            ->post('/contacts/import/confirm', ['key' => $key, 'skip_duplicates' => true])
            ->assertRedirect(route('contacts.index'));

        // 2 imported (existing one skipped), no duplicates created.
        $this->assertSame(3, Contact::count());
        $this->assertSame(1, Contact::where('phone_e164', '+6281200000001')->count());
        $this->assertSame('+6281200000002', Contact::where('email', 'sari@x.test')->first()?->phone_e164);
        $this->assertNull(Cache::get($key), 'Preview payload is consumed');
    }

    public function test_confirm_requires_valid_preview_key(): void
    {
        $this->actingAs($this->rep)
            ->post('/contacts/import/confirm', ['key' => 'relay:import:bogus'])
            ->assertSessionHasErrors('key');
    }

    public function test_non_csv_files_are_rejected(): void
    {
        $this->actingAs($this->rep)
            ->post('/contacts/import', ['file' => UploadedFile::fake()->create('contacts.pdf', 10)])
            ->assertSessionHasErrors('file');
    }
}
