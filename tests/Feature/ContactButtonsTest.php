<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use App\Services\ContactChannels;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ContactButtonsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['contact.phone' => null, 'contact.email' => null]);
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->never();
        $catalogue = Mockery::mock(Catalogue::class);
        $catalogue->shouldReceive('products')->andReturn(collect());
        $catalogue->shouldReceive('sourceUnavailable')->andReturn(false);
        $this->app->instance(Catalogue::class, $catalogue);
    }

    public function test_missing_or_unsafe_numbers_never_create_contact_links(): void
    {
        foreach ([null, '', 'not-confirmed', 'https://example.com', '+33 1 23 45 67 89', '+0123456789', '+123', '+1234567890123456', "+33123456789\n", ['+33123456789'], '<script>alert(1)</script>'] as $phone) {
            config(['contact.phone' => $phone]);
            $this->assertNull(app(ContactChannels::class)->phone());
            $this->assertSame([], app(ContactChannels::class)->links());
            $this->assertSame('', trim(Blade::render('<x-contact-actions />')));
            $this->assertSame('', trim(Blade::render('<x-whatsapp-chat />')));
            $advice = Blade::render('<x-advice-panel locale="fr" />');
            $this->assertStringNotContainsString('wa.me/', $advice);
            $this->assertStringNotContainsString('sms:', $advice);
            $this->assertStringContainsString(e(__('contact.advice_pending')), $advice);
        }
    }

    public function test_links_use_only_the_validated_number_and_do_not_send_a_prefilled_message(): void
    {
        // Synthetic local test fixture only; no request is sent to these links.
        config(['contact.phone' => '+33123456789']);
        $this->assertSame([
            'whatsapp' => 'https://wa.me/33123456789',
            'sms' => 'sms:+33123456789',
        ], app(ContactChannels::class)->links());
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            $html = Blade::render('<x-contact-actions />');
            $this->assertStringContainsString('href="https://wa.me/33123456789"', $html);
            $this->assertStringContainsString('href="sms:+33123456789"', $html);
            $this->assertStringContainsString('rel="noopener noreferrer"', $html);
            $this->assertStringContainsString('referrerpolicy="no-referrer"', $html);
            $this->assertStringContainsString(e(__('contact.application_help')), $html);
            $this->assertStringNotContainsString('?text=', $html);
            $this->assertStringNotContainsString('?body=', $html);
            $this->assertStringNotContainsString('<script', $html);
            $this->assertStringNotContainsString('contact.label', $html);
            $advice = Blade::render('<x-advice-panel :locale="$locale" />', ['locale' => $locale]);
            $this->assertStringContainsString('href="https://wa.me/33123456789"', $advice);
            $this->assertStringContainsString(e(__('contact.chat_action')), $advice);
            $this->assertStringNotContainsString(e(__('contact.advice_pending')), $advice);
            $this->assertStringNotContainsString('Elise', $advice);
            $chat = Blade::render('<x-whatsapp-chat />');
            $this->assertStringContainsString('href="https://wa.me/33123456789"', $chat);
            $this->assertStringContainsString('referrerpolicy="no-referrer"', $chat);
            $this->assertStringNotContainsString('?text=', $chat);
            $this->assertStringNotContainsString('<script', $chat);
        }
    }

    public function test_unconfigured_contact_has_no_external_buttons_or_preparation_notice(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->get('/'.$locale.'/contact')->assertOk()
                ->assertDontSee(__('pages.pending', [], $locale))
                ->assertDontSee('wa.me/', false)
                ->assertDontSee('sms:', false);
        }
    }

    public function test_configured_contact_is_translated_and_available_in_the_page_and_footer(): void
    {
        config(['contact.phone' => '+33123456789']);
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/contact')->assertOk()
                ->assertSee(__('contact.intro', [], $locale))
                ->assertSee(__('contact.instructions', [], $locale))
                ->assertDontSee(__('pages.pending', [], $locale));
            $this->assertSame(3, substr_count($response->getContent(), 'href="https://wa.me/33123456789"'));
            $this->assertSame(2, substr_count($response->getContent(), 'href="sms:+33123456789"'));
        }
    }
}
