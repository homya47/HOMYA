<?php

namespace App\Services;

/** @deprecated Use PromotionPricing for the permanent catalogue price policy. */
final class PromotionPreview
{
    public static function quote(?int $cents): ?array
    {
        return PromotionPricing::quote($cents);
    }
}
