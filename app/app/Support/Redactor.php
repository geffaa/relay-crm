<?php

namespace App\Support;

/**
 * Masks payment details before text leaves Roster for an AI model, and again on the way back.
 * Long digit runs, grouped card numbers, and numbers that follow words like "rekening" or "IBAN" are hidden;
 * prices such as "Rp 60.000.000" and phone-style short numbers are left alone.
 */
final class Redactor
{
    public const MASK = '[number hidden]';

    public static function mask(string $text): string
    {
        // Numbers announced by a payment word, with up to 24 characters of digits, spaces, dots or dashes.
        $text = preg_replace('/\b(rekening|no\.?\s?rek|norek|account(?:\s+number|\s+no\.?)?|acc\.?|iban|swift|va|virtual\s+account|kartu(?:\s+kredit)?|card)\b([^\d\n]{0,12})(\d[\d .\-]{6,24}\d)/iu', '$1$2'.self::MASK, $text) ?? $text;
        // Card numbers written as 4-4-4-4.
        $text = preg_replace('/\b(?:\d{4}[ \-]){3}\d{4}\b/', self::MASK, $text) ?? $text;
        // Any unbroken run of 12 to 19 digits (cards, long account numbers).
        $text = preg_replace('/\b\d{12,19}\b/', self::MASK, $text) ?? $text;
        // IBAN-shaped values.
        $text = preg_replace('/\b[A-Z]{2}\d{2}[A-Z0-9]{11,30}\b/', self::MASK, $text) ?? $text;

        return $text;
    }
}
