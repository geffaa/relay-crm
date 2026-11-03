<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class Phone
{
    public function __construct(
        private readonly string $defaultRegion = 'ID',
    ) {}

    /**
     * Normalize a phone number into E.164, e.g. "0812-3456-789" -> "+628123456789".
     * Returns null when the input cannot be parsed as a valid number.
     */
    public function toE164(?string $input): ?string
    {
        $trimmed = trim((string) $input);

        if ($trimmed === '') {
            return null;
        }

        // A leading "00" international prefix is treated the same as "+".
        if (str_starts_with($trimmed, '00')) {
            $trimmed = '+'.substr($trimmed, 2);
        }

        try {
            $util = PhoneNumberUtil::getInstance();
            $parsed = $util->parse($trimmed, $this->defaultRegion);
        } catch (NumberParseException) {
            return null;
        }

        if (! $util->isValidNumber($parsed)) {
            return null;
        }

        return $util->format($parsed, PhoneNumberFormat::E164);
    }
}
