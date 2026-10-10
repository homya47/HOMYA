<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomeNavigationTest extends TestCase
{
    public function test_home_navigation_has_real_destinations_in_every_language_without_database(): void
    {
        DB::shouldReceive('connection')->never();

        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale)->assertOk();
            $document = new DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new DOMXPath($document);

            $this->assertSame(1, $xpath->query('//main')->length);
            $this->assertSame(1, $xpath->query('//h1')->length);
            $this->assertSame('noindex, nofollow', $xpath->query('//meta[@name="robots"]')->item(0)->getAttribute('content'));
            $this->assertSame(5, $xpath->query('//li[starts-with(@id, "universe-")]')->length);

            foreach ($xpath->query('//a[@href]') as $link) {
                $url = parse_url($link->getAttribute('href'));
                if (isset($url['fragment'])) {
                    $destination = $xpath;
                    if (isset($url['path']) && $url['path'] !== '/'.$locale) {
                        $target = $this->get($url['path'].(isset($url['query']) ? '?'.$url['query'] : ''))->assertOk();
                        $targetDocument = new DOMDocument;
                        @$targetDocument->loadHTML('<?xml encoding="UTF-8">'.$target->getContent());
                        $destination = new DOMXPath($targetDocument);
                    }
                    $this->assertSame(1, $destination->query('//*[@id="'.$url['fragment'].'"]')->length, 'Missing navigation destination');
                }
                if (isset($url['path'])) {
                    $this->assertMatchesRegularExpression('~^/(fr|en|de)(/catalogue|/promotions|/retours/declaration|/produit/[a-z0-9-]+|/(a-propos|provenance|conseiller|contact|livraison|retractation|paiement|suivi|panier|avis|mentions-legales|cgv|confidentialite|cookies))?$~', $url['path']);
                }
            }

            $languageLinks = $xpath->query('//nav[contains(@class, "languages")]/a');
            $this->assertSame(3, $languageLinks->length);
            $this->assertSame(1, $xpath->query('//nav[contains(@class, "languages")]/a[@aria-current="page"]')->length);
            foreach ($languageLinks as $link) {
                $this->assertNotEmpty($link->getAttribute('aria-label'));
            }
        }
    }

    public function test_unpublished_catalogue_is_explicit_and_cannot_submit_a_search(): void
    {
        config(['catalogue.archive_path' => sys_get_temp_dir().'/homya-nonexistent-'.uniqid().'.json']);
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale);
            $response->assertSee(__('home.status_detail', [], $locale));
            $document = new DOMDocument;
            @$document->loadHTML($response->getContent());
            $xpath = new DOMXPath($document);

            $this->assertSame(1, $xpath->query('//input[@type="search"][@disabled][@aria-describedby="search-help"]')->length);
            $this->assertSame(1, $xpath->query('//label[@for="catalogue-search"]')->length);
            $this->assertSame(0, $xpath->query('//form//button[@type="submit"]')->length);
            $this->assertSame(0, $xpath->query('//*[@itemscope or @itemprop="price"]')->length);
        }
    }

    public function test_cooling_department_link_and_catalogue_search_are_localized_without_losing_context(): void
    {
        DB::shouldReceive('connection')->never();
        foreach (['fr' => 'réfrigérateur', 'en' => 'Refrigerators', 'de' => 'Kühlschränke'] as $locale => $query) {
            $response = $this->get('/'.$locale)->assertOk();
            $document = new DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new DOMXPath($document);
            $link = $xpath->query('//*[@id="universe-appliances"]//a')->item(0)->getAttribute('href');
            $url = parse_url($link);
            parse_str($url['query'], $filters);
            $this->assertSame('/'.$locale.'/catalogue', $url['path']);
            $this->assertSame(['universe' => 'appliances', 'q' => $query], $filters);
            $catalogue = $this->get($url['path'].'?'.$url['query'])->assertOk();
            $document = new DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$catalogue->getContent());
            $xpath = new DOMXPath($document);
            $this->assertSame($query, $xpath->query('//input[@id="filter-query"]')->item(0)->getAttribute('value'));
            ksort($filters);
            foreach ($xpath->query('//a[@data-language-link]') as $languageLink) {
                parse_str(parse_url($languageLink->getAttribute('href'), PHP_URL_QUERY), $context);
                ksort($context);
                $this->assertSame($filters, $context);
            }
        }
    }
}
