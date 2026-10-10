<?php

namespace App\Console\Commands;

use App\Services\Catalogue;
use App\Services\MerchantProducts;
use App\Services\Seo;
use Illuminate\Console\Command;

final class GoogleReadinessAudit extends Command
{
    protected $signature = 'homya:google-audit {--locale=fr : fr, en or de} {--json : Machine-readable report}';

    protected $description = 'Audit public catalogue and Merchant data without querying inventory or contacting Google';

    public function handle(Catalogue $catalogue, MerchantProducts $merchant, Seo $seo): int
    {
        $locale = $this->option('locale');
        if (! in_array($locale, config('homya.locales'), true)) {
            $this->error('Unsupported locale.');

            return self::FAILURE;
        }
        app()->setLocale($locale);
        $products = $catalogue->products();
        if ($catalogue->sourceUnavailable() || $catalogue->translationUnavailable() || $products->isEmpty()) {
            $this->error('Catalogue unavailable.');

            return self::FAILURE;
        }
        $reviews = $merchant->reviews();
        $rows = [];
        $counts = [];
        foreach ($products as $summary) {
            $product = $catalogue->product($summary['slug']);
            if ($product === null) {
                $this->error('Product unavailable.');

                return self::FAILURE;
            }
            $problems = $merchant->problems($product, $reviews[$product['slug']] ?? [], $locale);
            foreach ($problems as $problem) {
                $counts[$problem] = ($counts[$problem] ?? 0) + 1;
            }
            $variantRows = [];
            if ($product['has_variants']) {
                foreach ($merchant->variantProducts($product, $reviews[$product['slug']] ?? [], $locale) as [$variant, $variantReview]) {
                    $variantRows[] = ['id' => MerchantProducts::id($variant), 'variant' => $variant['variant_id'],
                        'title' => $variant['variant_title'], 'url_path' => route('product', ['locale' => $locale, 'slug' => $product['slug'], 'variant' => $variant['variant_id']], false),
                        'fingerprint' => MerchantProducts::fingerprint($variant), 'problems' => array_values($merchant->problems($variant, $variantReview, $locale))];
                }
            }
            $rows[] = ['slug' => $product['slug'], 'fingerprint' => MerchantProducts::fingerprint($product),
                'problems' => array_values($problems), 'catalogue_issues' => $product['issues'],
                'specification_reviews' => $product['specification_reviews'] ?? [], 'variants' => $variantRows];
        }
        ksort($counts);
        $report = ['locale' => $locale, 'products' => count($rows), 'indexing_configured' => $seo->enabled(),
            'merchant_configured' => $merchant->ready(), 'stock_checked' => false, 'google_contacted' => false,
            'problem_counts' => $counts, 'items' => $rows];
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->info(count($rows).' products audited ('.$locale.'). Stock and Google accounts not checked.');
            $this->table(['Problem', 'Products'], array_map(fn ($key, $count) => [$key, $count], array_keys($counts), $counts));
            $this->line('Use --json to obtain the per-product fingerprints for a separate factual review.');
        }

        return self::SUCCESS;
    }
}
