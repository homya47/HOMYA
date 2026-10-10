<?php

namespace Tests\Unit;

use App\Services\PromotionPricing;
use PHPUnit\Framework\TestCase;

class PromotionPreviewTest extends TestCase
{
    public function test_missing_and_below_threshold_prices_are_not_eligible(): void
    {
        foreach ([null, -1, 0, 99999] as $price) {
            $this->assertNull(PromotionPricing::quote($price));
        }
    }

    public function test_each_price_boundary_uses_the_validated_rate_and_preserves_price_integrity(): void
    {
        foreach ([
            [100000, 5, 95000, 5000],
            [149999, 5, 142499, 7500],
            [150000, 15, 127500, 22500],
            [200000, 15, 170000, 30000],
            [200001, 25, 150001, 50000],
        ] as [$base, $percent, $final, $saving]) {
            $quote = PromotionPricing::quote($base);
            $this->assertSame(['percent' => $percent, 'price_cents' => $final, 'saving_cents' => $saving], $quote);
            $this->assertSame($base, $quote['price_cents'] + $quote['saving_cents']);
        }
    }

    public function test_fractional_cents_round_the_final_price_once_with_half_cents_upwards(): void
    {
        foreach ([
            [100010, 95010, 5000],
            [150010, 127509, 22501],
            [200002, 150002, 50000],
            [200003, 150002, 50001],
        ] as [$base, $final, $saving]) {
            $quote = PromotionPricing::quote($base);
            $this->assertSame($final, $quote['price_cents']);
            $this->assertSame($saving, $quote['saving_cents']);
        }
    }
}
