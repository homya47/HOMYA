<?php

namespace Tests\Support;

use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\ClientInterface;

final class StripeTransportFake implements ClientInterface
{
    public array $sessions = [];

    public array $creates = [];

    public bool $timeoutAfterCreate = false;

    public bool $unavailable = false;

    public bool $expireUnavailable = false;

    public bool $checkoutUnavailable = false;

    private array $idempotency = [];

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        if (! str_starts_with($absUrl, 'https://api.stripe.com/v1/checkout/sessions')) {
            throw new \LogicException('Unexpected external request; real network disabled in this fixture.');
        }
        if ($this->unavailable) {
            throw new ApiConnectionException('Fixture unavailable, no real request.');
        }
        if ($method === 'post' && str_ends_with($absUrl, '/expire')) {
            if ($this->expireUnavailable) {
                throw new ApiConnectionException('Fixture expiry unavailable.');
            }
            $id = basename(dirname(parse_url($absUrl, PHP_URL_PATH)));
            $session = $this->sessions[$id];
            if ($session['status'] !== 'open') {
                throw new \RuntimeException('Fixture session cannot expire.');
            }
            $session['status'] = 'expired';
            $session['url'] = null;
            $this->sessions[$id] = $session;
        } elseif ($method === 'post') {
            $key = '';
            foreach ($headers as $header) {
                if (str_starts_with($header, 'Idempotency-Key:')) {
                    $key = trim(substr($header, 16));
                }
            }
            if ($key === '') {
                throw new \LogicException('Missing idempotency.');
            }
            if (isset($this->idempotency[$key])) {
                $session = $this->sessions[$this->idempotency[$key]];
            } else {
                $this->creates[] = $params;
                $id = 'cs_test_fixture_'.count($this->creates);
                $amount = array_sum(array_map(fn ($line) => $line['quantity'] * $line['price_data']['unit_amount'], $params['line_items']));
                $session = ['id' => $id, 'object' => 'checkout.session', 'mode' => 'payment', 'livemode' => false,
                    'client_reference_id' => $params['client_reference_id'], 'metadata' => $params['metadata'],
                    'amount_total' => $amount, 'currency' => 'eur', 'status' => 'open', 'payment_status' => 'unpaid',
                    'payment_intent' => null, 'url' => 'https://checkout.stripe.com/c/pay/'.$id];
                if ($this->checkoutUnavailable) {
                    $session['url'] = null;
                }
                $this->idempotency[$key] = $id;
                $this->sessions[$id] = $session;
                if ($this->timeoutAfterCreate) {
                    $this->timeoutAfterCreate = false;
                    throw new ApiConnectionException('Fixture timeout, no real payment.');
                }
            }
        } elseif ($method === 'get') {
            $id = basename(parse_url($absUrl, PHP_URL_PATH));
            $session = $this->sessions[$id] ?? throw new \LogicException('Unknown fixture session.');
        } else {
            throw new \LogicException('Unsupported fixture method.');
        }

        return [json_encode($session, JSON_THROW_ON_ERROR), 200, []];
    }
}
