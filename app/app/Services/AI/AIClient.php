<?php

namespace App\Services\AI;

interface AIClient
{
    /** The model or mode that produced the output, recorded with every summary, draft and score. */
    public function name(): string;

    /**
     * Run a prompt and return [text, inputTokens, outputTokens].
     *
     * @return array{0: string, 1: int, 2: int}
     */
    public function complete(string $system, string $user): array;
}
