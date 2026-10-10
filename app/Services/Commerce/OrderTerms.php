<?php

namespace App\Services\Commerce;

use App\Services\ContactChannels;
use App\Services\LegalInformation;

final class OrderTerms
{
    public function adapt(string $page, array $content): array
    {
        $updates = __('returns.policy.'.$page);
        if (is_array($updates)) {
            foreach ($content['sections'] ?? [] as $index => $section) {
                if (isset($updates[$section['id']])) {
                    $content['sections'][$index] = array_replace($section, $updates[$section['id']]);
                }
            }
        }
        $readiness = app(CheckoutReadiness::class);
        if (! $readiness->deferredReady() || $readiness->cardReady() || $readiness->bankReady()) {
            return $content;
        }
        foreach ($content['sections'] ?? [] as $index => $section) {
            if ($page === 'cgv' && $section['id'] === 'perimetre') {
                $content['sections'][$index]['paragraphs'][1] = __('commerce.deferred_legal_notice');
            }
            if ($page === 'confidentialite' && $section['id'] === 'donnees') {
                $content['sections'][$index]['paragraphs'][] = __('commerce.deferred_data_notice');
            }
            if ($page === 'retractation' && $section['id'] === 'preparation') {
                $content['sections'][$index]['paragraphs'][] = __('commerce.deferred_withdrawal_notice');
            }
        }

        return $content;
    }

    public function hash(): string
    {
        $terms = __('legal');
        foreach ($terms['entries'] as $page => $content) {
            $terms['entries'][$page] = $this->adapt($page, $content);
        }

        $terms['seller'] = config('legal.company');
        $terms['returns_address'] = app(LegalInformation::class)->returnAddress();
        $terms['mediator'] = app(LegalInformation::class)->mediator();
        $terms['contact'] = [
            'phone' => app(ContactChannels::class)->phone(),
            'email' => app(ContactChannels::class)->email(),
        ];

        return hash('sha256', json_encode($terms, JSON_THROW_ON_ERROR));
    }
}
