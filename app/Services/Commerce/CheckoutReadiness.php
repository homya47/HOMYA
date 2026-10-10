<?php

namespace App\Services\Commerce;

final class CheckoutReadiness
{
    public function ready(): bool
    {
        return $this->bankReady() || $this->deferredReady();
    }

    public function deferredReady(): bool
    {
        $origin = parse_url((string) config('app.url'));
        $local = app()->environment('local', 'testing')
            && in_array($origin['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true)
            && in_array($origin['scheme'] ?? '', ['http', 'https'], true);

        return config('commerce.enabled') === true && config('commerce.deferred_enabled') === true
            && ($local || (app()->environment('production') && ! config('app.debug')
                && ($origin['scheme'] ?? '') === 'https' && config('commerce.legal_ready') === true));
    }

    public function bankReady(): bool
    {
        $origin = parse_url((string) config('app.url'));
        $local = app()->environment('local', 'testing') && ($origin['scheme'] ?? '') === 'http'
            && in_array($origin['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true);

        return config('commerce.enabled') === true && config('commerce.legal_ready') === true
            && (app()->environment('local', 'testing') ? ($local || ($origin['scheme'] ?? '') === 'https')
                : (app()->environment('production') && ! config('app.debug') && ($origin['scheme'] ?? '') === 'https'))
            && app(BankTransfer::class)->details() !== null;
    }

    public function cardReady(): bool
    {
        // HOMYA accepts bank transfer only; card payment was removed by the owner.
        return false;
    }
}
