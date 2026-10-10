<?php

namespace App\Http\Controllers;

use App\Services\Catalogue;
use App\Services\Commerce\CheckoutReadiness;
use App\Services\Commerce\OrderTerms;
use App\Services\ContactChannels;

class InformationController extends Controller
{
    public const PAGES = ['a-propos', 'provenance', 'conseiller', 'contact', 'livraison', 'retractation', 'paiement', 'suivi', 'mentions-legales', 'cgv', 'confidentialite', 'cookies'];

    public function __invoke(Catalogue $catalogue, ContactChannels $channels, string $locale, string $page)
    {
        app()->setLocale($locale);
        $languageLinks = [];
        foreach (config('homya.locales') as $language) {
            $languageLinks[$language] = route('information', ['locale' => $language, 'page' => $page]);
        }
        $content = __('pages.entries.'.$page);
        if ($page === 'contact' && ($channels->phone() !== null || $channels->email() !== null)) {
            $content['intro'] = __('contact.intro');
            if ($channels->phone() !== null) {
                $content['paragraphs'][] = __('contact.instructions');
            }
            $content['pending'] = false;
        }
        $readiness = app(CheckoutReadiness::class);
        $deferredOnly = $readiness->deferredReady() && ! $readiness->bankReady();
        if ($deferredOnly) {
            if ($page === 'paiement') {
                $content['paragraphs'] = [__('commerce.deferred_notice'), __('commerce.cart_reservation_notice'), __('commerce.deferred_contact')];
            }
            if ($page === 'suivi') {
                $content['intro'] = __('commerce.last_order');
                $content['paragraphs'] = [__('commerce.deferred_tracking_notice')];
            }
        }
        $content = app(OrderTerms::class)->adapt($page, $content);
        if ($page === 'contact' && config('dashboard.enabled')) {
            $content['paragraphs'][1] = __('support.contact_notice');
        }
        $catalogueReady = $catalogue->products()->isNotEmpty() && ! $catalogue->sourceUnavailable();

        return view('information', compact('locale', 'page', 'content', 'languageLinks', 'catalogueReady', 'deferredOnly'));
    }
}
