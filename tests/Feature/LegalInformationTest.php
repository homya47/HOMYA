<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use App\Services\Commerce\OrderTerms;
use App\Services\ContactChannels;
use App\Services\LegalInformation;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class LegalInformationTest extends TestCase
{
    private const PAGES = ['mentions-legales', 'cgv', 'retractation', 'confidentialite', 'cookies'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['contact.phone' => null, 'contact.email' => null]);
        config(['legal.mediator.verified' => false]);
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->never();
        $catalogue = Mockery::mock(Catalogue::class);
        $catalogue->shouldReceive('products')->andReturn(collect());
        $catalogue->shouldReceive('sourceUnavailable')->andReturn(true);
        $this->app->instance(Catalogue::class, $catalogue);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }

    public function test_every_language_preserves_all_three_jurisdictions_without_the_removed_notices(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach (self::PAGES as $page) {
                $content = __('pages.entries.'.$page, [], $locale);
                $response = $this->get('/'.$locale.'/'.$page)->assertOk()
                    ->assertDontSee(__('legal.draft', [], $locale))
                    ->assertDontSee(__('legal.draft_detail', [], $locale))
                    ->assertDontSee(__('legal.scope', [], $locale))
                    ->assertDontSee('legal.', false)
                    ->assertDontSee('damdam', false);
                $xpath = $this->xpath($response->getContent());
                $this->assertTrue($content['pending']);
                $this->assertSame(0, $xpath->query('//*[contains(@class,"information-status") or contains(@class,"legal-scope")]')->length);
                if ($page === 'cgv') {
                    $payment = collect($content['sections'])->firstWhere('id', 'commande');
                    foreach ([2, 3] as $index) {
                        $response->assertDontSee($payment['paragraphs'][$index]);
                    }
                }
                $this->assertSame(1, $xpath->query('//h1')->length);
                $this->assertSame($content['title'], $xpath->query('//h1')->item(0)->textContent);
                foreach (['france', 'belgique', 'allemagne'] as $country) {
                    $this->assertSame(1, $xpath->query('//h2[@id="legal-'.$country.'"]')->length);
                }
                $this->assertSame(config('legal.reviewed_on'), $xpath->query('//time')->item(0)->getAttribute('datetime'));
                $this->assertSame(0, $xpath->query('//article//form | //article//input | //article//iframe | //article//script')->length);
                foreach ($xpath->query('//a[@data-language-link]') as $link) {
                    $this->assertSame('/'.$link->getAttribute('hreflang').'/'.$page, parse_url($link->getAttribute('href'), PHP_URL_PATH));
                }
            }
        }
    }

    public function test_contents_links_and_related_documents_resolve_without_a_catalogue_or_database(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach (self::PAGES as $page) {
                $xpath = $this->xpath($this->get('/'.$locale.'/'.$page)->assertOk()->getContent());
                $ids = [];
                foreach ($xpath->query('//*[@id]') as $element) {
                    $this->assertNotContains($element->getAttribute('id'), $ids);
                    $ids[] = $element->getAttribute('id');
                }
                foreach ($xpath->query('//nav[@class="legal-toc"]//a') as $link) {
                    $id = substr($link->getAttribute('href'), 1);
                    $this->assertContains($id, $ids);
                    $this->assertSame($link->textContent, $xpath->query('//h2[@id="'.$id.'"]')->item(0)->textContent);
                }
                foreach ($xpath->query('//aside//a') as $link) {
                    $path = parse_url($link->getAttribute('href'), PHP_URL_PATH);
                    $this->assertStringStartsWith('/'.$locale.'/', $path);
                    $this->get($path)->assertOk();
                }
                $this->assertSame(1, $xpath->query('//aside//a[@aria-current="page"]')->length);
            }
        }
    }

    public function test_cookies_use_the_real_session_configuration_and_do_not_invent_consent_controls(): void
    {
        config(['session.cookie' => 'test-session-only', 'session.lifetime' => 37, 'session.expire_on_close' => false]);
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/cookies')->assertOk()
                ->assertSee('test-session-only')->assertSee('XSRF-TOKEN')
                ->assertSee(__('legal.cookie_duration', ['minutes' => 37], $locale))
                ->assertDontSee('googletagmanager.com', false)
                ->assertDontSee('google-analytics.com', false);
            $xpath = $this->xpath($response->getContent());
            $this->assertSame(2, $xpath->query('//dl[@class="legal-cookie-inventory"]/div')->length);
            $this->assertSame(0, $xpath->query('//*[@data-cookie-consent or @data-consent]')->length);
        }
        config(['session.expire_on_close' => true]);
        $this->get('/fr/cookies')->assertOk()
            ->assertSee(__('legal.cookie_session_duration', [], 'fr'))
            ->assertDontSee(__('legal.cookie_duration', ['minutes' => 37], 'fr'));
    }

    public function test_official_references_are_localized_safe_links_and_never_load_embedded_services(): void
    {
        $hosts = ['www.economie.gouv.fr', 'economie.fgov.be', 'news.economie.fgov.be', 'www.gesetze-im-internet.de', 'entreprendre.service-public.gouv.fr', 'europa.eu', 'www.legifrance.gouv.fr', 'cnil.fr', 'eur-lex.europa.eu', 'www.autoriteprotectiondonnees.be'];
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach (self::PAGES as $page) {
                $html = $this->get('/'.$locale.'/'.$page)->assertOk()->getContent();
                $xpath = $this->xpath($html);
                $links = $xpath->query('//section[contains(@class,"legal-sources")]//a');
                $this->assertGreaterThan(0, $links->length);
                foreach ($links as $link) {
                    $url = parse_url($link->getAttribute('href'));
                    $this->assertSame('https', $url['scheme']);
                    $this->assertContains($url['host'], $hosts);
                    $this->assertSame('noopener noreferrer', $link->getAttribute('rel'));
                    $this->assertSame('no-referrer', $link->getAttribute('referrerpolicy'));
                    $this->assertNotEmpty(trim($link->textContent));
                }
                $this->assertStringNotContainsString('ec.europa.eu/consumers/odr', $html);
                $this->assertStringNotContainsString('mailto:', $html);
                $this->assertStringNotContainsString('wa.me/', $html);
            }
        }
    }

    public function test_return_summary_is_accessible_in_every_language_without_account_or_customer_data(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/retractation')->assertOk();
            $xpath = $this->xpath($response->getContent());
            foreach (['modalites', 'procedure', 'delai', 'retour', 'exceptions', 'preparation'] as $section) {
                $this->assertSame(1, $xpath->query('//h2[@id="legal-'.$section.'"]')->length);
            }
            $this->assertSame(5, $xpath->query('//section[@aria-labelledby="legal-modalites"]/ul/li')->length);
            $this->assertSame(4, $xpath->query('//section[@aria-labelledby="legal-procedure"]/ul/li')->length);
            $this->assertStringContainsString('14', $xpath->query('//section[@aria-labelledby="legal-modalites"]')->item(0)->textContent);
            $this->assertSame(0, $xpath->query('//article//form | //article//input')->length);
            // Every field must be named: orphan paragraphs would silently disappear in Blade.
            foreach (__('pages.entries', [], $locale) as $content) {
                foreach ($content['sections'] ?? [] as $section) {
                    foreach (array_keys($section) as $key) {
                        $this->assertIsString($key);
                    }
                }
            }
        }
    }

    public function test_purchase_policies_link_to_all_related_documents_in_the_selected_language(): void
    {
        $pages = [...self::PAGES, 'livraison', 'paiement', 'contact'];
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach ($pages as $page) {
                $response = $this->get('/'.$locale.'/'.$page)->assertOk()
                    ->assertSee(__('legal.purchase_information', [], $locale))
                    ->assertDontSee('legal.purchase_information', false);
                $xpath = $this->xpath($response->getContent());
                $links = $xpath->query('//aside//a');
                $this->assertSame(8, $links->length);
                $paths = [];
                foreach ($links as $link) {
                    $paths[] = parse_url($link->getAttribute('href'), PHP_URL_PATH);
                }
                foreach ($pages as $destination) {
                    $this->assertContains('/'.$locale.'/'.$destination, $paths);
                }
                $this->assertSame(1, $xpath->query('//aside//a[@aria-current="page"]')->length);
            }
        }
    }

    public function test_shipping_payment_and_terms_describe_the_customer_purchase_path(): void
    {
        $expected = [
            'fr' => ['Livraison gratuite en France, en Belgique et en Allemagne.', 'volumineux', 'référence de commande', 'Le délai global annoncé'],
            'en' => ['Free delivery in France, Belgium and Germany.', 'bulky', 'order reference', 'The stated overall delivery period'],
            'de' => ['Kostenlose Lieferung in Frankreich, Belgien und Deutschland.', 'sperrige', 'Bestellreferenz', 'Die angekündigte Gesamtlieferzeit'],
        ];
        foreach ($expected as $locale => [$shipping, $access, $reference, $handling]) {
            $this->get('/'.$locale.'/livraison')->assertOk()->assertSee($shipping)->assertSee($access);
            $this->get('/'.$locale.'/paiement')->assertOk()->assertSee($reference)->assertSee('72');
            $this->get('/'.$locale.'/cgv')->assertOk()->assertSee($handling)->assertSee('72');
            $this->get('/'.$locale.'/contact')->assertOk()->assertDontSee('mailto:', false)->assertDontSee('wa.me/', false);
        }
    }

    public function test_delivery_period_starts_at_payment_without_inventing_the_type_of_days(): void
    {
        foreach (['fr' => ['à compter du paiement', 'jours ouvrés'], 'en' => ['from payment', 'working days'], 'de' => ['ab Zahlung', 'Werktage']] as $locale => [$start, $days]) {
            foreach (['livraison', 'cgv'] as $page) {
                $this->get('/'.$locale.'/'.$page)->assertOk()->assertSee($start)->assertDontSee($days);
            }
        }
    }

    public function test_seller_identity_is_consistent_without_copying_reference_company_contacts(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach (['mentions-legales', 'cgv', 'confidentialite', 'contact'] as $page) {
                $response = $this->get('/'.$locale.'/'.$page)->assertOk();
                foreach (config('legal.company') as $value) {
                    $response->assertSee($value);
                }
                $response->assertSee(__('legal.contact_pending', [], $locale))
                    ->assertDontSee('contact@homeconfort.com', false)
                    ->assertDontSee('938 138 179', false)
                    ->assertDontSee('Gay Lussac', false)
                    ->assertDontSee('mailto:', false)
                    ->assertDontSee('tel:', false);
                $xpath = $this->xpath($response->getContent());
                $this->assertSame(1, $xpath->query('//dl[@class="company-details"]')->length);
                $this->assertSame(13, $xpath->query('//dl[@class="company-details"]/div')->length);
            }
        }
    }

    public function test_legal_identity_changes_are_included_in_terms_fingerprint_without_payment_secrets(): void
    {
        app()->setLocale('fr');
        $terms = app(OrderTerms::class);
        $before = $terms->hash();
        config(['commerce.stripe.secret' => 'synthetic-private-test-value']);
        $this->assertSame($before, $terms->hash());
        config(['legal.company.address' => 'Synthetic correspondence address']);
        $this->assertNotSame($before, $terms->hash());
        $this->get('/fr/cgv')->assertSee('Synthetic correspondence address')
            ->assertDontSee('synthetic-private-test-value', false);
        $after = $terms->hash();
        config(['contact.email' => 'contact@example.test']);
        $this->assertNotSame($after, $terms->hash());
    }

    public function test_only_valid_configured_email_is_rendered_and_no_contact_form_is_invented(): void
    {
        foreach ([null, '', 'not-an-email', "test@example.test\r\nBcc:other@example.test", '<script>@example.test', ['contact@example.test']] as $email) {
            config(['contact.email' => $email]);
            $this->assertNull(app(ContactChannels::class)->email());
            $this->get('/fr/contact')->assertOk()->assertDontSee('mailto:', false);
        }
        config(['contact.email' => 'contact@example.test']);
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/contact')->assertOk()
                ->assertSee('mailto:contact@example.test', false)
                ->assertDontSee('wa.me/', false)
                ->assertDontSee('tel:', false)
                ->assertDontSee(__('pages.pending', [], $locale));
            $this->assertSame(0, $this->xpath($response->getContent())->query('//article//form')->length);
        }
    }

    public function test_local_open_orders_keep_tax_included_prices_and_actual_collected_data_in_policies(): void
    {
        config(['commerce.enabled' => true, 'commerce.deferred_enabled' => true,
            'commerce.inventory_enabled' => true, 'commerce.legal_ready' => false,
            'commerce.bank.enabled' => false, 'commerce.stripe.secret' => '']);
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->get('/'.$locale.'/cgv')->assertOk()
                ->assertDontSee(__('commerce.deferred_legal_notice', [], $locale))->assertSee('72');
            $this->get('/'.$locale.'/confidentialite')->assertOk()
                ->assertSee(__('commerce.deferred_data_notice', [], $locale))->assertSee('IGN');
            $this->get('/'.$locale.'/retractation')->assertOk()
                ->assertSee(__('commerce.deferred_withdrawal_notice', [], $locale))->assertSee('14');
        }
        $this->assertFalse(config('commerce.legal_ready'));
    }

    public function test_confirmed_return_destination_is_shared_and_does_not_invent_a_carrier_or_contact(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $html = $this->get('/'.$locale.'/retractation')->assertOk()->getContent();
            $xpath = $this->xpath($html);
            $address = $xpath->query('//dl[contains(@class,"return-details")]//dd');
            $this->assertSame(1, $address->length);
            $this->assertStringContainsString(config('legal.company.address'), $address->item(0)->textContent);
            $this->assertSame(1, $xpath->query('//h2[@id="legal-emballage"]')->length);
            $this->assertStringNotContainsString('mailto:', $html);
            $this->assertStringNotContainsString('Michel', $html);
        }
        $before = app(OrderTerms::class)->hash();
        config(['legal.returns_at_registered_office' => false]);
        $this->assertNull(app(LegalInformation::class)->returnAddress());
        $this->assertNotSame($before, app(OrderTerms::class)->hash());
    }

    public function test_mediation_identification_cannot_be_claimed_with_only_a_name(): void
    {
        config(['legal.mediator' => ['verified' => true, 'name' => 'Synthetic mediator']]);
        $this->assertNull(app(LegalInformation::class)->mediator());
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach (['cgv', 'mentions-legales'] as $page) {
                $response = $this->get('/'.$locale.'/'.$page)->assertOk()
                    ->assertSee(__('legal.mediator_pending', [], $locale))
                    ->assertDontSee('Synthetic mediator');
                $this->assertSame(1, $this->xpath($response->getContent())->query('//h2[@id="legal-mediation"]')->length);
            }
        }
    }

    public function test_only_verified_complete_mediation_details_render_safe_links_and_change_terms(): void
    {
        $before = app(OrderTerms::class)->hash();
        config(['legal.mediator' => ['verified' => false, 'name' => '<script>Test</script>',
            'address' => 'Synthetic postal address', 'website' => 'https://mediator.example.test/apply']]);
        $this->assertNull(app(LegalInformation::class)->mediator());
        $this->assertSame($before, app(OrderTerms::class)->hash());
        config(['legal.mediator.verified' => true]);
        $this->assertNotNull(app(LegalInformation::class)->mediator());
        $this->assertNotSame($before, app(OrderTerms::class)->hash());
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach (['cgv', 'mentions-legales'] as $page) {
                $response = $this->get('/'.$locale.'/'.$page)->assertOk()
                    ->assertSee('&lt;script&gt;Test&lt;/script&gt;', false)
                    ->assertDontSee('<script>Test</script>', false)
                    ->assertDontSee(__('legal.mediator_pending', [], $locale));
                $links = $this->xpath($response->getContent())->query('//dl[contains(@class,"mediator-details")]//a');
                $this->assertSame(1, $links->length);
                $this->assertSame('https://mediator.example.test/apply', $links->item(0)->getAttribute('href'));
                $this->assertSame('noopener noreferrer', $links->item(0)->getAttribute('rel'));
            }
        }
        foreach (['javascript:alert(1)', 'http://mediator.example.test', 'https://user:secret@mediator.example.test', 'https://', ['https://mediator.example.test']] as $url) {
            config(['legal.mediator.website' => $url]);
            $this->assertNull(app(LegalInformation::class)->mediator());
            $this->get('/fr/cgv')->assertOk()->assertSee(__('legal.mediator_pending'))
                ->assertDontSee('mediator.example.test', false);
        }
    }

    public function test_selected_mediator_is_not_presented_as_effective_until_membership_is_confirmed(): void
    {
        config(['legal.mediator.verified' => true, 'legal.mediator.membership_active' => false]);
        $before = app(OrderTerms::class)->hash();
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->get('/'.$locale.'/cgv')->assertOk()->assertSee('CM2C')
                ->assertSee(__('legal.mediator_membership_pending', [], $locale))
                ->assertSee('declarer-un-litige@cm2c.net');
        }
        config(['legal.mediator.membership_active' => true]);
        $this->assertNotSame($before, app(OrderTerms::class)->hash());
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach (['cgv', 'mentions-legales'] as $page) {
                $this->get('/'.$locale.'/'.$page)->assertOk()->assertSee('49 Rue de Ponthieu')
                    ->assertSee('https://www.cm2c.net/', false)
                    ->assertSee('mailto:declarer-un-litige@cm2c.net', false)
                    ->assertSee('tel:+33189470014', false)
                    ->assertDontSee(__('legal.mediator_membership_pending', [], $locale));
            }
        }
    }
}
