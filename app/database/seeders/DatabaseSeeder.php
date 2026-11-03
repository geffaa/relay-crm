<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Activity;
use App\Models\AiRun;
use App\Models\AiSummary;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadScore;
use App\Models\Message;
use App\Models\MessageTemplate;
use App\Models\Pipeline;
use App\Models\ScoreOverride;
use App\Models\Stage;
use App\Models\Task;
use App\Models\User;
use App\Models\WhatsAppNumber;
use App\Models\WorkingHours;
use App\Support\Phone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo company "Northwind Studio", a fictional Jakarta agency, with curated people, WhatsApp threads,
 * deals with history, notes, tasks and explainable scores. The content lives in data/northwind.php.
 */
class DatabaseSeeder extends Seeder
{
    private Company $company;

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, Contact> */
    private array $contacts = [];

    /** @var array<string, Account> */
    private array $accounts = [];

    /** @var array<string, array<string, int>> message ids per conversation key, in script order */
    /** @var array<string, array<int, int>> message ids per conversation key, in script order */
    private array $messageIds = [];

    /** @var array<string, Conversation> */
    private array $conversations = [];

    /** @var array<string, array{0: Stage, 1: int}> */
    private array $stages = [];

    public function run(): void
    {
        mt_srand(31);
        $data = require __DIR__.'/data/northwind.php';

        $this->company = Company::create(['name' => 'Northwind Studio', 'slug' => 'northwind', 'currency' => 'IDR', 'timezone' => 'Asia/Jakarta']);
        app()->instance('relay.company', $this->company);

        $this->createUsers();
        foreach ([1, 2, 3, 4, 5] as $weekday) {
            WorkingHours::create(['weekday' => $weekday, 'start_time' => '09:00', 'end_time' => '17:00']);
        }
        $this->createAccounts($data['accounts']);
        $pipeline = $this->createPipeline();
        $this->createContacts($data['contacts']);
        $this->createGeneratedContacts(70);
        $number = $this->createWhatsApp($data['templates']);
        $this->createConversations($data, $number);
        $this->createHistory();
        $this->createDeals($pipeline, $data);
        $this->createScores();
        $this->createSummaries();
        $this->createLeads();
        $this->createAiRuns();

        Artisan::call('relay:detect-stale-deals');
        Artisan::call('relay:detect-unreplied');
        app()->forgetInstance('relay.company');
    }

    private function createUsers(): void
    {
        foreach ([
            'ayu' => ['Ayu Kartika', 'admin'], 'bimo' => ['Bimo Setiawan', 'manager'], 'citra' => ['Citra Rahayu', 'rep'],
            'dimas' => ['Dimas Prakoso', 'rep'], 'elena' => ['Elena Wijaya', 'rep'],
        ] as $key => [$name, $role]) {
            $user = User::create(['name' => $name, 'email' => "{$key}@northwind.test", 'password' => Hash::make('password')]);
            $user->companies()->attach($this->company->id, ['role' => $role, 'quarterly_quota' => ['bimo' => 120_000_000, 'citra' => 250_000_000, 'dimas' => 150_000_000, 'elena' => 100_000_000][$key] ?? null]);
            $user->update(['current_company_id' => $this->company->id]);
            $this->users[$key] = $user;
        }
    }

    /** @param array<string, array<int, string>> $accounts */
    private function createAccounts(array $accounts): void
    {
        $n = 0;
        foreach ($accounts as $key => [$name, $domain, $industry, $size, $health]) {
            $this->accounts[$key] = Account::create(['name' => $name, 'domain' => $domain, 'industry' => $industry, 'size_band' => $size, 'health' => $health, 'owner_id' => $this->users[['bimo', 'citra', 'dimas', 'elena'][$n++ % 4]]->id]);
        }
    }

    private function createPipeline(): Pipeline
    {
        $pipeline = Pipeline::create(['name' => 'Agency retainers', 'is_default' => true]);
        foreach ([['New', 1, 7, 'open'], ['Qualified', 2, 10, 'open'], ['Proposal', 3, 7, 'open'], ['Negotiation', 4, 5, 'open'], ['Won', 5, null, 'won'], ['Lost', 6, null, 'lost']] as [$name, $pos, $stale, $kind]) {
            $this->stages[$name] = [Stage::create(['pipeline_id' => $pipeline->id, 'name' => $name, 'position' => $pos, 'stale_after_days' => $stale, 'kind' => $kind]), $pos];
        }

        return $pipeline;
    }

    /** @param array<string, array<int, mixed>> $rows */
    private function createContacts(array $rows): void
    {
        $phone = new Phone;
        foreach ($rows as $key => [$name, $number, $email, $account, $source, $tags, $owner]) {
            $this->contacts[$key] = Contact::create([
                'name' => $name, 'phone_e164' => $phone->toE164($number), 'email' => $email, 'source' => $source, 'tags' => $tags,
                'account_id' => $account ? $this->accounts[$account]->id : null, 'owner_id' => $this->users[$owner]->id,
            ]);
        }
    }

    private function createGeneratedContacts(int $count): void
    {
        $phone = new Phone;
        $first = ['Agung', 'Bambang', 'Cahyo', 'Dian', 'Eko', 'Farah', 'Gita', 'Hana', 'Indra', 'Jihan', 'Kartika', 'Lukman', 'Mira', 'Nando', 'Oki', 'Pradipta', 'Qori', 'Reza', 'Sekar', 'Tania', 'Umar', 'Vina', 'Wahyu', 'Yuni'];
        $last = ['Wijaya', 'Susanto', 'Kusumo', 'Hartono', 'Purnama', 'Rahmawati', 'Saputra', 'Lestari', 'Setiawan', 'Nugraha', 'Pangestu', 'Hakim', 'Anwar', 'Yulianto', 'Maharani', 'Fitriani', 'Siregar', 'Tampubolon', 'Simanjuntak', 'Pardede'];
        $prefixes = ['812', '813', '821', '852', '857', '878', '896', '881'];
        $sources = ['whatsapp', 'web', 'referral', 'manual'];
        $tags = [['hot'], ['retainer'], ['ecommerce'], ['follow-up'], ['b2b'], [], [], []];
        $owners = array_values($this->users);
        $seen = [];
        $made = 0;
        for ($i = 0; $made < $count && $i < $count * 3; $i++) {
            $name = $first[$i % 24].' '.$last[($i * 7 + 3) % 20];
            $e164 = $phone->toE164('+62'.$prefixes[$i % 8].str_pad((string) ((10000000 + $i * 7919) % 89999999), 8, '0', STR_PAD_LEFT));
            if (isset($seen[$name]) || ($e164 && isset($seen[$e164]))) {
                continue;
            }
            $seen[$name] = true;
            $e164 && $seen[$e164] = true;
            Contact::create([
                'name' => $name, 'phone_e164' => $e164, 'email' => $i % 3 === 0 ? Str::slug($name, '.').'@example.com' : null, 'source' => $sources[$i % 4], 'tags' => $tags[$i % 8],
                'account_id' => $i % 6 === 0 ? array_values($this->accounts)[$i % count($this->accounts)]->id : null, 'owner_id' => $owners[$i % count($owners)]->id,
            ]);
            $made++;
        }
    }

    /** @param list<array{0: string, 1: string, 2: string, 3: string}> $templates */
    private function createWhatsApp(array $templates): WhatsAppNumber
    {
        $number = WhatsAppNumber::create(['phone_number_id' => '109255000000001', 'display_phone' => '+62811000001', 'waba_id' => '2210000000', 'mode' => 'simulated']);
        foreach ($templates as [$name, $body, $category, $language]) {
            MessageTemplate::create(['whatsapp_number_id' => $number->id, 'name' => $name, 'language' => $language, 'category' => $category, 'body' => $body]);
        }

        return $number;
    }

    /** @param array<string, mixed> $data */
    private function createConversations(array $data, WhatsAppNumber $number): void
    {
        foreach ($data['conversations'] as [$key, $script, $endedAgo, $status, $assignee]) {
            $contact = $this->contacts[$key];
            $steps = $data['scripts'][$script];
            $times = [];
            $t = now()->subMinutes($endedAgo);
            for ($i = count($steps) - 1; $i >= 0; $i--) {
                $times[$i] = $t->copy();
                $t = $t->subMinutes($steps[$i][2]);
            }
            $assigneeId = $assignee ? $this->users[$assignee]->id : $contact->owner_id;

            $conversation = new Conversation(['contact_id' => $contact->id, 'whatsapp_number_id' => $number->id, 'status' => $status, 'assignee_id' => $assigneeId]);
            $conversation->company_id = $this->company->id;
            $conversation->save();

            $lastInbound = null;
            $trailing = 0;
            foreach ($steps as $i => $step) {
                [$direction, $text] = $step;
                $failed = ($step[3] ?? null) === 'failed';
                $in = $direction === 'in';
                $message = Message::create([
                    'conversation_id' => $conversation->id, 'wamid' => $in ? 'sim.seed.'.bin2hex(random_bytes(8)) : null, 'direction' => $direction, 'type' => 'text', 'body' => $text,
                    'status' => $in ? 'delivered' : ($failed ? 'failed' : 'read'), 'sent_by' => $in ? null : $assigneeId, 'created_at' => $times[$i],
                    'error' => $failed ? ['code' => 131047, 'message' => 'More than 24 hours have passed since the customer last replied. Send an approved template instead.'] : null,
                ]);
                $this->messageIds[$key][$i] = $message->id;
                Activity::record($this->company->id, $contact, 'message', $in ? null : $assigneeId, ['direction' => $direction, 'text' => Str::limit($text, 140), 'conversation_id' => $conversation->id], $times[$i]);
                $in && $lastInbound = $times[$i];
                $trailing = $in ? $trailing + 1 : 0;
            }
            $conversation->update(['last_inbound_at' => $lastInbound, 'last_message_at' => $times[array_key_last($steps)], 'unread_count' => $status === 'open' ? $trailing : 0]);
            $this->conversations[$key] = $conversation;
        }
    }

    /** @param array<string, mixed> $data */
    /** Older, finished chats so response-time trends have history to draw from. */
    private function createHistory(): void
    {
        $number = WhatsAppNumber::first();
        $contacts = Contact::query()->whereDoesntHave('conversations')->orderBy('id')->limit(16)->get();
        foreach ($contacts as $i => $contact) {
            $start = now()->subDays(3 + $i * 3)->setTime(10 + ($i % 6), 5 + ($i * 7) % 50);
            $minutes = [8, 14, 22, 11, 35, 9, 18, 26, 12, 7, 30, 16, 10, 20, 13, 24][$i];
            $assignee = $this->users[['bimo', 'citra', 'dimas', 'elena'][$i % 4]];
            $conversation = new Conversation(['contact_id' => $contact->id, 'whatsapp_number_id' => $number->id, 'status' => 'closed', 'assignee_id' => $assignee->id, 'last_inbound_at' => $start, 'last_message_at' => $start->copy()->addMinutes($minutes), 'unread_count' => 0]);
            $conversation->company_id = $this->company->id;
            $conversation->save();
            Message::create(['conversation_id' => $conversation->id, 'wamid' => 'sim.hist.'.bin2hex(random_bytes(6)), 'direction' => 'in', 'type' => 'text', 'body' => 'Halo, mau tanya layanan website dan harganya ya.', 'status' => 'delivered', 'created_at' => $start]);
            Message::create(['conversation_id' => $conversation->id, 'direction' => 'out', 'type' => 'text', 'body' => 'Halo Kak, terima kasih sudah menghubungi. Boleh ceritakan kebutuhannya?', 'status' => 'read', 'sent_by' => $assignee->id, 'created_at' => $start->copy()->addMinutes($minutes)]);
        }
    }

    private function createDeals(Pipeline $pipeline, array $data): void
    {
        $openOrder = ['New', 'Qualified', 'Proposal', 'Negotiation'];
        $byTitle = [];
        foreach ($data['deals'] as $row) {
            [$title, $account, $contactKey, $stageName, $value, $currency, $closeIn, $ownerKey, $days] = $row;
            $extra = $row[9] ?? null;
            [$stage, $pos] = $this->stages[$stageName];
            $contact = $this->contacts[$contactKey];
            $closed = $stage->isClosed();
            $entered = now()->subDays($days)->subHours(mt_rand(0, 6));

            $deal = new Deal([
                'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'contact_id' => $contact->id, 'account_id' => $account ? $this->accounts[$account]->id : $contact->account_id,
                'owner_id' => $this->users[$ownerKey]->id, 'title' => $title, 'value' => $value, 'currency' => $currency,
                'expected_close' => $closed ? $entered->toDateString() : now()->addDays($closeIn)->toDateString(),
                'stage_entered_at' => $entered, 'lost_reason' => $stageName === 'Lost' ? $extra : null, 'closed_at' => $closed ? $entered : null,
            ]);
            $deal->company_id = $this->company->id;
            $deal->save();
            $byTitle[$title] = $deal;

            // History: created, then one stage change per step up to today's stage.
            $path = $closed ? [...array_slice($openOrder, 1, 2), $stageName] : array_slice($openOrder, 1, $pos - 1);
            $steps = count($path);
            $start = $entered->copy()->subDays(max(2, $steps * 3));
            Deal::whereKey($deal->id)->update(['created_at' => $start]);
            Activity::record($this->company->id, $deal, 'note', $this->users[$ownerKey]->id, ['event' => 'deal_created'], $start);
            $from = 'New';
            foreach ($path as $i => $to) {
                $at = $i === $steps - 1 ? $entered : $start->copy()->addDays(($i + 1) * 3 - 1);
                Activity::record($this->company->id, $deal, 'stage_change', $this->users[$ownerKey]->id, [
                    'from' => $from, 'to' => $to, 'kind' => $this->stages[$to][0]->kind, 'lost_reason' => $to === 'Lost' ? $extra : null,
                ], $at);
                $from = $to;
            }
        }

        foreach ($data['notes'] as $title => $notes) {
            foreach ($notes as $i => $text) {
                Activity::record($this->company->id, $byTitle[$title], 'note', $this->users['bimo']->id, ['text' => $text], now()->subHours(6 + $i * 20));
            }
        }

        foreach ([
            ['Shopify migration', 'bimo', 'Send timeline and proposal', 6], ['Booking app discovery', 'bimo', 'Prepare the discovery agenda', 30],
            ['Brand refresh 2027', 'elena', 'Email Joko instead of WhatsApp', -20], ['Law firm site and blog', 'citra', 'Call Agus after 14:30', 20],
            ['SEO 12 outlets, 6 months', 'dimas', 'Draft the outlet page structure', 48], ['Performance ads retainer', 'dimas', 'Send the single-invoice proposal', -3],
            ['Headless commerce build', 'bimo', 'Scope workshop with Sukha', 72], ['Conversion audit', 'dimas', 'Chase the decision from Finwise', 5],
        ] as [$title, $who, $task, $hours]) {
            Task::create(['company_id' => $this->company->id, 'assignee_id' => $this->users[$who]->id, 'subject_type' => Deal::class, 'subject_id' => $byTitle[$title]->id, 'title' => $task, 'kind' => ['Send timeline and proposal' => 'send', 'Call Agus after 14:30' => 'call', 'Chase the decision from Finwise' => 'call', 'Scope workshop with Sukha' => 'meeting', 'Draft the outlet page structure' => 'send'][$task] ?? 'manual', 'due_at' => now()->addHours($hours)]);
        }
    }

    private function createScores(): void
    {
        $reasons = [
            'dewi' => [['Source: WhatsApp', 'source', 8], ['Open deal Shopify migration (Rp 60.000.000)', 'deal_value', 14], ['Mentioned a budget of about Rp 60 juta', 'ai', 18], ['Fixed deadline before 12.12', 'ai', 12], ['Replied within 8 minutes, three times', 'recency', 10], ['Shared their email', 'email', 10], ['Account size 11-50', 'account_size', 6]],
            'tom' => [['Source: Referral', 'source', 12], ['Open deal Website and booking rebuild (£8,000)', 'deal_value', 12], ['Stated a budget of about £8,000', 'ai', 14], ['Wants to decide before the end of the month', 'ai', 8], ['Shared their email', 'email', 10], ['Account size 11-50', 'account_size', 6]],
            'putri' => [['Source: WhatsApp', 'source', 8], ['Open deal Clinic website refresh', 'deal_value', 12], ['Confirmed a meeting time', 'ai', 14], ['Active in the last 7 days', 'recency', 10], ['Shared their email', 'email', 10], ['Account size 11-50', 'account_size', 6]],
            'arif' => [['Source: WhatsApp', 'source', 8], ['Open deal SEO 12 outlets, 6 months', 'deal_value', 15], ['Clear scope: 12 new outlets', 'ai', 10], ['Active in the last 7 days', 'recency', 10], ['Shared their email', 'email', 10], ['Account size 51-200', 'account_size', 8]],
            'hendra' => [['Source: Manual', 'source', 4], ['Open deal Performance ads retainer', 'deal_value', 15], ['Raised a price concern, asked for a two-stage offer', 'ai', -6], ['Active in the last 7 days', 'recency', 10], ['Shared their email', 'email', 10], ['Account size 51-200', 'account_size', 8]],
            'rina' => [['Source: WhatsApp', 'source', 8], ['Open deal Booking app discovery', 'deal_value', 10], ['Asked about the discovery fee', 'ai', 8], ['2 inbound messages', 'engagement', 6], ['Shared their email', 'email', 10]],
            'agus' => [['Source: Referral', 'source', 12], ['Open deal Law firm site and blog', 'deal_value', 14], ['Agreed to a call tomorrow', 'ai', 10], ['Shared their email', 'email', 10], ['Account size 11-50', 'account_size', 6]],
            'bayu' => [['Source: Web', 'source', 6], ['Open deal Conversion audit', 'deal_value', 10], ['Asked for a timeline revision', 'ai', 6], ['Account size 51-200', 'account_size', 8], ['Shared their email', 'email', 10]],
            'sarah' => [['Source: Referral', 'source', 12], ['Open deal Design partner retainer', 'deal_value', 12], ['Proposed an intro call', 'ai', 8], ['Shared their email', 'email', 10], ['Account size 11-50', 'account_size', 6]],
            'joko' => [['Source: Web', 'source', 6], ['Open deal Brand refresh 2027', 'deal_value', 10], ['No reply to two follow-ups', 'ai', -10], ['Account size 1-10', 'account_size', 2]],
            'lina' => [['Source: WhatsApp', 'source', 8], ['Small budget, landing page only', 'ai', -4], ['Account size 1-10', 'account_size', 2], ['1 inbound message', 'engagement', 3]],
            'fajar' => [['Source: WhatsApp', 'source', 8], ['Open deal Content engine setup', 'deal_value', 8], ['Asked about hosting and monthly cost', 'ai', 4], ['Shared their email', 'email', 10]],
            'nadia' => [['Source: Web', 'source', 6], ['Chose an in-house team', 'ai', -12]],
        ];
        foreach ($reasons as $key => $list) {
            $contact = $this->contacts[$key];
            $structured = array_sum(array_map(fn ($r) => $r[1] === 'ai' ? 0 : $r[2], $list));
            $ai = array_sum(array_map(fn ($r) => $r[1] === 'ai' ? $r[2] : 0, $list));
            $score = max(0, min(100, $structured + $ai));
            LeadScore::create(['contact_id' => $contact->id, 'score' => $score, 'reasons' => array_map(fn ($r) => ['label' => $r[0], 'signal' => $r[1], 'weight' => $r[2]], $list), 'structured_part' => $structured, 'ai_adjustment' => $ai, 'model' => 'simulated-demo']);
            $contact->update(['score' => $score, 'score_updated_at' => now()->subHours(mt_rand(1, 30))]);
        }

        $tom = $this->contacts['tom']->refresh();
        ScoreOverride::create(['contact_id' => $tom->id, 'user_id' => $this->users['bimo']->id, 'from' => $tom->score, 'to' => $tom->score + 8, 'reason' => 'Budget confirmed on a call; the group has already chosen a booking vendor.']);
        $tom->update(['score' => $tom->score + 8]);
    }

    private function createSummaries(): void
    {
        $sets = [
            'dewi' => [[['Migrating about 340 products with variants from WooCommerce to Shopify.', [0]], ['Must go live before 12.12; budget about Rp 60 juta including the product page redesign.', [2]], ['Asked whether starting next week still fits the 12.12 deadline.', [4]]], 'Confirm the kick-off date and send the timeline with the proposal today.', 0.86],
            'tom' => [[['Dental group with three clinics in Manchester, currently on Dentally, wants a new site with online booking.', [0, 2]], ['Open to switching booking systems if the integration is painful.', [2]], ['Wants to decide before the end of the month; budget about £8,000.', [4]]], 'Send two options with the budget range, and confirm the Dentally integration approach.', 0.9],
            'law' => [[['Law firm needs a new website and a blog for legal articles.', [0]], ['Agreed to a call after 14:00; Citra confirmed 14:30.', [2, 3]]], 'Make the call and ask about bilingual (ID/EN) pages.', 0.8],
        ];
        foreach ($sets as $key => [$points, $next, $confidence]) {
            $ckey = $key === 'law' ? 'agus' : $key;
            $ids = $this->messageIds[$ckey];
            $summary = AiSummary::create([
                'conversation_id' => $this->conversations[$ckey]->id,
                'points' => array_map(fn ($p) => ['text' => $p[0], 'message_ids' => array_map(fn ($i) => $ids[$i], $p[1])], $points),
                'next_step' => $next, 'confidence' => $confidence, 'model' => 'simulated-demo', 'prompt_version' => 'v1', 'covers_until_message_id' => end($ids),
            ]);
            Activity::record($this->company->id, $this->contacts[$ckey], 'ai_summary', null, ['conversation_id' => $this->conversations[$ckey]->id, 'summary_id' => $summary->id, 'points' => count($points)], now()->subMinutes(mt_rand(20, 400)));
        }
    }

    private function createLeads(): void
    {
        $reps = array_values($this->users);
        foreach ([
            ['Galang Pratama', '+6281770001111', 'galang@newco.test', 'web', 'new', 'Asked about a Shopify and SEO bundle.'],
            ['Melati Handayani', '+6281770002222', 'melati@bloom.test', 'referral', 'contacted', 'Referred by Kopi Tuku; wants a brand refresh.'],
            ['Peter Collins', '+447700900789', 'peter@collins.test', 'web', 'qualified', 'Budget approved, comparing vendors.'],
            ['Ni Luh Sari', '+6281770003333', null, 'whatsapp', 'new', 'WhatsApp inquiry about retainer pricing.'],
            ['Ahmad Fauzi', '+6281770004444', 'fauzi@sentra.test', 'manual', 'new', 'Met at an event; needs a conversion audit.'],
            ['Grace Tan', '+6591110001', 'grace@sgco.test', 'referral', 'contacted', 'Singapore agency owner.'],
            ['Bagas Wicaksono', '+6281770005555', null, 'web', 'lost', 'Budget was cut this quarter.'],
        ] as $i => [$name, $phone, $email, $source, $status, $notes]) {
            Lead::create(['name' => $name, 'phone_e164' => $phone, 'email' => $email, 'source' => $source, 'status' => $status, 'owner_id' => $reps[($i + 1) % 5]->id, 'notes' => $notes]);
        }
    }

    private function createAiRuns(): void
    {
        foreach (range(1, 14) as $i) {
            $kind = ['summarize', 'draft_reply', 'score'][$i % 3];
            $in = mt_rand(700, 2400);
            $out = mt_rand(60, 380);
            $run = AiRun::create(['kind' => $kind, 'model' => 'simulated-demo', 'input_tokens' => $in, 'output_tokens' => $out, 'cost_usd' => 0, 'status' => $i === 9 ? 'error' : 'ok', 'meta' => ['simulated' => true]]);
            $run->forceFill(['created_at' => now()->subHours($i * 7)])->save();
        }
    }
}
