<?php

namespace App\Services\AI;

/**
 * Deterministic client used in tests and in the public demo when no API key
 * is configured. Its outputs follow the same JSON contracts as the real
 * model so the parsing/validation logic is exercised end to end.
 */
class FakeAIClient implements AIClient
{
    /** Template of the JSON the fake produces, settable per test. */
    public static ?string $nextResponse = null;

    public function name(): string
    {
        return 'simulated-demo';
    }

    public function complete(string $system, string $user): array
    {
        if (self::$nextResponse !== null) {
            [$text, self::$nextResponse] = [self::$nextResponse, null];

            return [$text, 120, 80];
        }

        if (str_contains($system, 'summarizer')) {
            return [$this->fakeSummary($user), 120, 80];
        }

        if (str_contains($system, 'reply drafter')) {
            return [json_encode(['draft' => $this->fakeDraft($user)]), 90, 40];
        }

        if (str_contains($system, 'scorer')) {
            return [$this->fakeScore($user), 150, 60];
        }

        return ['', 0, 0];
    }

    /** @return array<int, array{id: int, who: string, text: string}> */
    private function lines(string $transcript): array
    {
        preg_match_all('/\[m(\d+)\][^(]*\((customer|agent)[^)]*\):\s*(.+)/', $transcript, $m, PREG_SET_ORDER);

        return array_map(fn ($x) => ['id' => (int) $x[1], 'who' => $x[2], 'text' => trim($x[3])], $m);
    }

    private function quote(string $text): string
    {
        return '“'.rtrim(mb_strimwidth($text, 0, 110, '…'), ' ').'”';
    }

    private function isIndonesian(string $text): bool
    {
        return (bool) preg_match('/\b(kak|bisa|boleh|terima kasih|halo|saya|kami|apa|berapa|tolong|sudah|ya)\b/i', $text);
    }

    /** A grounded digest of the real transcript: every point quotes a message it cites. */
    private function fakeSummary(string $user): string
    {
        $lines = $this->lines($user);
        $customer = array_values(array_filter($lines, fn ($l) => $l['who'] === 'customer'));
        $agent = array_values(array_filter($lines, fn ($l) => $l['who'] === 'agent'));
        $points = [];

        if ($customer) {
            $points[] = ['text' => 'The customer opened with '.$this->quote($customer[0]['text']), 'message_ids' => [$customer[0]['id']]];
        }
        if ($agent) {
            $points[] = ['text' => 'The team replied '.$this->quote($agent[0]['text']), 'message_ids' => [$agent[0]['id']]];
        }
        if (count($customer) > 1) {
            $last = end($customer);
            $points[] = ['text' => 'Latest from the customer: '.$this->quote($last['text']), 'message_ids' => [$last['id']]];
        }

        $final = end($lines);
        $next = $final && $final['who'] === 'customer'
            ? 'Reply to the latest customer message: '.$this->quote($final['text'])
            : 'Wait for the customer, and follow up tomorrow if there is no answer.';

        return json_encode(['points' => $points, 'next_step' => $next, 'confidence' => 0.6]);
    }

    private function fakeDraft(string $user): string
    {
        $customer = array_values(array_filter($this->lines($user), fn ($l) => $l['who'] === 'customer'));
        $last = $customer ? end($customer)['text'] : '';

        if ($this->isIndonesian($last)) {
            return str_contains($last, '?')
                ? 'Terima kasih pertanyaannya, Kak. Kami cek dulu dan kabari secepatnya ya.'
                : 'Terima kasih, Kak. Kami tindak lanjuti dan kabari segera ya.';
        }

        return str_contains($last, '?')
            ? 'Good question. Let me check the details and get back to you shortly.'
            : "Thanks for reaching out! I'll look into this and come back to you shortly.";
    }

    /** Looks for the signals a salesperson would: budget, deadline, buying intent. */
    private function fakeScore(string $user): string
    {
        $text = mb_strtolower(implode(' ', array_map(fn ($l) => $l['who'] === 'customer' ? $l['text'] : '', $this->lines($user))));
        $signals = [
            'budget_mentioned' => ['Mentioned a budget', '/\b(budget|anggaran|juta|rp ?\d|£|\$\d|million)\b/'],
            'deadline_mentioned' => ['Stated a deadline or timeframe', '/\b(before|sebelum|deadline|minggu|week|bulan|month|end of the month)\b/'],
            'purchase_intent' => ['Asked about price or next steps', '/\b(harga|price|quote|penawaran|proposal|next steps|berapa|how much|biaya)\b/'],
        ];
        $reasons = [];
        foreach ($signals as $signal => [$label, $pattern]) {
            if (preg_match($pattern, $text)) {
                $reasons[] = ['label' => $label, 'signal' => $signal];
            }
        }

        return json_encode(['adjustment' => min(15, 5 * count($reasons)), 'reasons' => $reasons]);
    }
}
