<?php

namespace App\Services;

final class PromotionPricing
{
    /** Calculate once from the archival base price, never from a reduced price. */
    public static function quote(?int $cents): ?array
    {
        if ($cents === null || $cents < 100000) {
            return null;
        }
        $percent = $cents > 200000 ? 25 : ($cents >= 150000 ? 15 : 5);
        $price = intdiv($cents * (100 - $percent) + 50, 100);

        return ['percent' => $percent, 'price_cents' => $price, 'saving_cents' => $cents - $price];
    }
}
