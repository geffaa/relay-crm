<?php

namespace App\Support;

/** Display and totals for deals quoted in several currencies. Conversion is an estimate (config/relay.php). */
final class Money
{
    public static function convert(float $amount, string $from, string $to): float
    {
        if ($from === $to) {
            return $amount;
        }
        $rates = config('relay.fx_to_idr');
        if (! isset($rates[$from], $rates[$to])) {
            return $amount;
        }

        return $amount * $rates[$from] / $rates[$to];
    }
}
