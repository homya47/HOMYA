<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use App\Services\CatalogueSource;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class StorefrontChromeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Internal footer destinations are tested separately from configured contact links.
        config(['contact.phone' => null, 'contact.email' => null]);
        config(['catalogue.source' => 'files']);
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->never();
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }

    private function localPath(string $href, string $locale): string
    {
        $url = parse_url($href);
        $originHost = parse_url(config('app.url'), PHP_URL_HOST);
        $this->assertSame($originHost, $url['host'] ?? $originHost);
        $this->assertStringStartsWith('/'.$locale.'/', $url['path']);

        return $url['path'].(isset($url['query']) ? '?'.$url['query'] : '');
    }

    public function test_service_navigation_has_localized_destinations_and_no_invented_basket_counter(): void
    {
        config(['contact.email' => 'support@example.test']);
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/contact')->assertOk()
                ->assertDontSee('chrome.', false)->assertDontSee('damdam', false);
            $xpath = $this->xpath($response->getContent());
            $contact = $xpath->query('//a[@class="utility-contact"]')->item(0);
            $this->assertNotNull($contact);
            $this->assertSame('mailto:support@example.test', $contact->getAttribute('href'));
            $this->assertSame('support@example.test', trim($contact->textContent));
            foreach (['utility-navigation' => ['suivi'], 'storefront-actions' => ['conseiller', 'suivi', 'panier']] as $class => $pages) {
                $nav = $xpath->query('//nav[@class="'.$class.'"]')->item(0);
                $this->assertNotNull($nav);
                $this->assertNotEmpty($nav->getAttribute('aria-label'));
                $links = $xpath->query('.//a', $nav);
                $this->assertCount(count($pages), $links);
                foreach ($pages as $index => $page) {
                    $link = $links->item($index);
                    $path = $this->localPath($link->getAttribute('href'), $locale);
                    $this->assertSame('/'.$locale.'/'.$page, $path);
                    $key = $class === 'utility-navigation' ? 'utility' : 'services';
                    $this->assertSame(__('chrome.'.$key.'.'.$page, [], $locale), trim($link->textContent));
                    $this->get($path)->assertOk();
                }
            }
            $services = $xpath->query('//*[@id="primary-navigation"]//li[@class="navigation-service"]/a');
            $this->assertCount(2, $services);
            foreach (['suivi', 'panier'] as $index => $page) {
                $this->assertSame('/'.$locale.'/'.$page, $this->localPath($services->item($index)->getAttribute('href'), $locale));
                $this->assertSame(__('pages.entries.'.$page.'.title', [], $locale), trim($services->item($index)->textContent));
            }
            $this->assertSame(1, $xpath->query('//button[@data-menu-toggle][@aria-controls="primary-navigation"][@aria-expanded="false"]')->length);
            $this->assertSame(0, $xpath->query('//*[contains(@class,"basket-count") or contains(@class,"cart-count") or @data-cart-count]')->length);
            $basket = $xpath->query('//nav[@class="storefront-actions"]//a[contains(@href,"/panier")]')->item(0);
            $this->assertDoesNotMatchRegularExpression('/\d/', $basket->textContent);
        }
    }

    public function test_footer_groups_and_information_links_are_accessible_and_localized(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/contact')->assertOk();
            $xpath = $this->xpath($response->getContent());
            $footer = $xpath->query('//footer[@class="home-footer"]')->item(0);
            $this->assertNotNull($footer);
            $grid = $xpath->query('.//div[contains(@class,"home-footer-grid")]', $footer)->item(0);
            $this->assertNotNull($grid);
            $this->assertSame(4, $xpath->query('./*', $grid)->length);
            $this->assertSame(3, $xpath->query('./nav', $grid)->length);
            foreach (['categories', 'help', 'legal', 'commerce'] as $group) {
                $id = 'footer-'.$group.'-heading';
                $heading = $xpath->query('//*[@id="'.$id.'"]')->item(0);
                $this->assertNotNull($heading);
                $this->assertSame(__('chrome.'.$group.'_title', [], $locale), $heading->textContent);
                $this->assertSame(1, $xpath->query('//*[@aria-labelledby="'.$id.'"]')->length);
            }
            $this->assertSame(1, $xpath->query('//footer//nav[@aria-labelledby="footer-discoveries-heading"]')->length);
            $this->assertSame(__('chrome.discoveries_title', [], $locale), $xpath->query('//*[@id="footer-discoveries-heading"]')->item(0)->textContent);
            foreach ($xpath->query('//footer//a[@href]') as $link) {
                $path = parse_url($link->getAttribute('href'), PHP_URL_PATH);
                if ($path === '/'.$locale) {
                    continue;
                }
                $this->localPath($link->getAttribute('href'), $locale);
                $this->assertNotEmpty(trim($link->textContent));
                if (! in_array($path, ['/'.$locale.'/catalogue', '/'.$locale.'/promotions'], true)) {
                    $this->get($path)->assertOk();
                }
            }
            $this->assertSame(1, $xpath->query('//footer//a[contains(@href,"/'.$locale.'/cookies")]')->length);
            $this->assertSame(2, $xpath->query('//ul[@class="footer-payment-methods"]/li')->length);
            $this->assertSame(__('chrome.payment_planned', [], $locale), $xpath->query('//*[@class="footer-payment-label"]')->item(0)->textContent);
            $this->assertSame(0, $xpath->query('//footer//form | //footer//input')->length);
        }
    }

    public function test_footer_discoveries_and_categories_return_real_nonempty_localized_catalogues(): void
    {
        if (! is_file(config('catalogue.archive_path'))) {
            $this->markTestSkipped('Local file archive is required to verify real merchandising selections.');
        }
        $hash = hash_file('sha256', config('catalogue.archive_path'));
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/contact')->assertOk();
            $xpath = $this->xpath($response->getContent());
            foreach (['discoveries', 'categories'] as $group) {
                $links = $xpath->query('//nav[@aria-labelledby="footer-'.$group.'-heading"]//a');
                $selections = config('storefront.'.$group);
                $this->assertCount(count($selections) + ($group === 'categories' ? 1 : 0), $links);
                foreach ($selections as $index => $selection) {
                    $link = $links->item($index);
                    $this->assertSame(__('chrome.'.$group.'.'.$selection['key'], [], $locale), trim($link->textContent));
                    $expected = $selection['filters'];
                    if (isset($selection['query_translation'])) {
                        $expected['q'] = __($selection['query_translation'], [], $locale);
                    }
                    parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY) ?? '', $query);
                    ksort($expected);
                    ksort($query);
                    $this->assertSame($expected, $query);
                    $catalogue = $this->get($this->localPath($link->getAttribute('href'), $locale))->assertOk();
                    $this->assertGreaterThan(0, $catalogue->viewData('products')->total(), $locale.' '.$group.' '.$selection['key']);
                    $this->assertGreaterThan(0, $this->xpath($catalogue->getContent())->query('//article[@class="product-card"]')->length);
                }
            }
            $promotions = $this->get('/'.$locale.'/promotions')->assertOk();
            $this->assertGreaterThan(0, $promotions->viewData('products')->total());
        }
        $this->assertSame($hash, hash_file('sha256', config('catalogue.archive_path')));
    }

    public function test_chrome_preserves_catalogue_context_when_switching_languages(): void
    {
        if (! is_file(config('catalogue.archive_path'))) {
            $this->markTestSkipped('Local file archive is required for catalogue context.');
        }
        $filters = ['universe' => 'appliances', 'q' => 'LG', 'sort' => 'price_asc', 'page' => '2'];
        ksort($filters);
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/catalogue?'.http_build_query($filters))->assertOk();
            $xpath = $this->xpath($response->getContent());
            $this->assertSame('LG', $xpath->query('//input[@id="catalogue-search"]')->item(0)->getAttribute('value'));
            $links = $xpath->query('//a[@data-language-link]');
            $this->assertCount(3, $links);
            foreach ($links as $link) {
                $target = $link->getAttribute('hreflang');
                $this->assertContains($target, ['fr', 'en', 'de']);
                $this->assertSame('/'.$target.'/catalogue', parse_url($link->getAttribute('href'), PHP_URL_PATH));
                parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
                ksort($query);
                $this->assertSame($filters, $query);
            }
            foreach ($xpath->query('//nav[@class="storefront-actions"]//a | //nav[@class="utility-navigation"]//a') as $link) {
                $this->localPath($link->getAttribute('href'), $locale);
            }
        }
    }

    public function test_nordica_discovery_includes_every_archived_brand_case_variant_in_each_language(): void
    {
        if (! is_file(config('catalogue.archive_path'))) {
            $this->markTestSkipped('Local file archive is required to verify actual brand case variants.');
        }
        $archive = json_decode(file_get_contents(config('catalogue.archive_path')), true, 512, JSON_THROW_ON_ERROR);
        $expected = collect($archive['products'])->filter(fn ($product) => strcasecmp(trim($product['brand']), 'La Nordica') === 0);
        $this->assertNotEmpty($expected);
        $uppercase = $expected->where('brand', 'LA NORDICA')->pluck('slug')->all();
        foreach (['poele-a-bois-en-fonte-la-nordica-brigitta-16-4-7-kw', 'poele-a-bois-la-nordica-tea-16-7-kw', 'poele-a-bois-raccordable-en-fonte-la-nordica-jennifer-16-7-7-kw'] as $slug) {
            $this->assertContains($slug, $uppercase);
        }
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/contact')->assertOk();
            $xpath = $this->xpath($response->getContent());
            $selectionIndex = collect(config('storefront.discoveries'))->search(fn ($selection) => $selection['key'] === 'nordica');
            $this->assertNotFalse($selectionIndex);
            $link = $xpath->query('//nav[@aria-labelledby="footer-discoveries-heading"]//a')->item($selectionIndex);
            $this->assertNotNull($link);
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $filters);
            $this->assertSame(['universe' => 'heating', 'q' => 'La Nordica'], $filters);
            $this->get($this->localPath($link->getAttribute('href'), $locale))->assertOk();
            // Inspect the whole filtered collection, rather than only the first 24-card page.
            $selected = app(Catalogue::class)->filtered($filters)->pluck('slug');
            $this->assertSame([], $expected->pluck('slug')->diff($selected)->values()->all(), 'Missing archived La Nordica products in '.$locale);
            foreach ($uppercase as $slug) {
                $this->assertContains($slug, $selected, 'Uppercase brand was excluded in '.$locale);
            }
        }
    }

    public function test_cookies_information_is_translated_without_tracker_or_artificial_consent_controls(): void
    {
        $introductions = [];
        foreach (['fr', 'en', 'de'] as $locale) {
            $content = __('pages.entries.cookies', [], $locale);
            $this->assertIsArray($content);
            $response = $this->get('/'.$locale.'/cookies')->assertOk()->assertSee($content['title'])->assertSee($content['intro']);
            foreach ($content['paragraphs'] as $paragraph) {
                $response->assertSee($paragraph);
            }
            $xpath = $this->xpath($response->getContent());
            $this->assertSame($content['title'], $xpath->query('//h1')->item(0)->textContent);
            $this->assertSame(0, $xpath->query('//form[not(@role="search")] | //iframe | //script | //*[@data-cookie-consent or @data-consent]')->length);
            $response->assertDontSee('googletagmanager.com', false)->assertDontSee('google-analytics.com', false)->assertDontSee('fbq(', false);
            foreach ($xpath->query('//a[@data-language-link]') as $link) {
                $this->assertSame('/'.$link->getAttribute('hreflang').'/cookies', parse_url($link->getAttribute('href'), PHP_URL_PATH));
            }
            $introductions[] = $content['intro'];
        }
        $this->assertCount(3, array_unique($introductions));
    }

    public function test_services_footer_and_cookies_remain_available_during_a_private_catalogue_failure(): void
    {
        $source = Mockery::mock(CatalogueSource::class);
        $source->shouldReceive('archive')->andThrow(new RuntimeException('PRIVATE_CHROME_SOURCE_SENTINEL'));
        $this->app->instance(CatalogueSource::class, $source);
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach (['', '/contact', '/cookies'] as $page) {
                $response = $this->get('/'.$locale.$page)->assertOk()
                    ->assertDontSee('PRIVATE_CHROME_SOURCE_SENTINEL', false)->assertDontSee('chrome.', false);
                $xpath = $this->xpath($response->getContent());
                $this->assertSame(1, $xpath->query('//input[@id="catalogue-search"][@disabled][@aria-describedby="search-help"]')->length);
                $this->assertSame(0, $xpath->query('//form[@role="search"]//button[@type="submit"]')->length);
                $this->assertSame(3, $xpath->query('//nav[@class="storefront-actions"]//a')->length);
                $this->assertSame(1, $xpath->query('//footer[@class="home-footer"]')->length);
                $this->assertSame(count(config('storefront.discoveries')), $xpath->query('//nav[@aria-labelledby="footer-discoveries-heading"]//a')->length);
                $this->assertSame(1, $xpath->query('//footer//a[contains(@href,"/'.$locale.'/cookies")]')->length);
            }
        }
    }
}
