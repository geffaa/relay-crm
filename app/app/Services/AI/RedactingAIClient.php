<?php

namespace App\Services\AI;

use App\Support\Redactor;

/** Wraps any client so payment details never reach the model and are not echoed back into the CRM. */
class RedactingAIClient implements AIClient
{
    public function __construct(private readonly AIClient $inner) {}

    public function name(): string
    {
        return $this->inner->name();
    }

    public function complete(string $system, string $user): array
    {
        [$text, $in, $out] = $this->inner->complete($system, Redactor::mask($user));

        return [Redactor::mask($text), $in, $out];
    }
}
