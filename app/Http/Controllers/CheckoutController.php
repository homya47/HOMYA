<?php

namespace App\Http\Controllers;

use App\Services\Commerce\BankTransfer;
use App\Services\Commerce\Cart;
use App\Services\Commerce\CheckoutReadiness;
use App\Services\Commerce\OrderPdf;
use App\Services\Commerce\OrderStore;
use App\Services\Commerce\OrderTerms;
use App\Services\Commerce\TransferProof;
use App\Services\Documents\DocumentStore;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CheckoutController extends CommerceController
{
    public function show(Request $request, Cart $cart, CheckoutReadiness $readiness, OrderStore $orders, string $locale)
    {
        if (! $readiness->ready()) {
            return response()->view('commerce.unavailable', [], 503);
        }
        $quote = $cart->quote($request->session());
        if (! $quote['valid']) {
            return redirect()->route('cart.show', ['locale' => $locale]);
        }
        $owner = $this->ownerHash($request, false);
        if ($owner !== '' && is_string($request->session()->get('commerce.last_order'))) {
            $last = $orders->connection()->table('commerce_orders')->where('id', $request->session()->get('commerce.last_order'))
                ->where('owner_hash', $owner)->first();
            if ($last && in_array($last->status, ['expired', 'bank_transfer_expired'], true)
                && $request->session()->get('commerce.checkout_key') === $last->checkout_key) {
                $request->session()->forget(['commerce.checkout_key', 'commerce.quote_hash', 'commerce.terms_accepted_at']);
            }
        }
        $key = $request->session()->get('commerce.checkout_key');
        if (! is_string($key)) {
            $key = (string) Str::uuid();
            $request->session()->put('commerce.checkout_key', $key);
        }
        $quoteHash = $this->quoteHash($quote);
        $request->session()->put('commerce.quote_hash', $quoteHash);

        return view('commerce.checkout', compact('quote', 'key', 'quoteHash', 'locale') + ['cardReady' => $readiness->cardReady(), 'bankReady' => $readiness->bankReady()]);
    }

    public function store(Request $request, Cart $cart, CheckoutReadiness $readiness, OrderStore $orders, string $locale)
    {
        if (! $readiness->ready()) {
            return response()->view('commerce.unavailable', [], 503);
        }
        $data = $request->validate([
            'checkout_key' => ['required', 'uuid'], 'quote_hash' => ['required', 'string', 'size:64'],
            'email' => ['required', 'email:rfc', 'max:254'], 'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:200'], 'postal_code' => ['required', 'string', 'max:12'],
            'city' => ['required', 'string', 'max:100'], 'country' => ['required', Rule::in(config('commerce.countries'))],
            'terms' => ['accepted'], 'payment_method' => ['nullable', Rule::in(['bank_transfer', 'deferred'])],
        ]);
        $quote = $cart->quote($request->session());
        if (! hash_equals((string) $request->session()->get('commerce.checkout_key', ''), $data['checkout_key'])
            || ! hash_equals((string) $request->session()->get('commerce.quote_hash', ''), $data['quote_hash'])) {
            throw ValidationException::withMessages(['cart' => __('commerce.stale_checkout')]);
        }
        if (! hash_equals($this->quoteHash($quote), $data['quote_hash'])) {
            throw ValidationException::withMessages(['cart' => __('commerce.changed_offer')]);
        }
        $postal = $data['country'] === 'BE' ? '/\A[0-9]{4}\z/' : '/\A[0-9]{5}\z/';
        if (! preg_match($postal, $data['postal_code'])) {
            throw ValidationException::withMessages(['postal_code' => __('commerce.invalid_postal')]);
        }
        $customer = array_intersect_key($data, array_flip(['email', 'name', 'address', 'postal_code', 'city', 'country']));
        $method = $data['payment_method'] ?? ($readiness->bankReady() ? 'bank_transfer' : 'deferred');
        abort_if($method === 'bank_transfer' && ! $readiness->bankReady(), 503);
        abort_if($method === 'deferred' && ! $readiness->deferredReady(), 503);
        $customer['payment_method'] = $method;
        $customer['terms_hash'] = app(OrderTerms::class)->hash();
        $customer['terms_accepted_at'] = $request->session()->get('commerce.terms_accepted_at') ?? now()->toIso8601String();
        $request->session()->put('commerce.terms_accepted_at', $customer['terms_accepted_at']);
        $order = $orders->reserve($quote, $customer, $this->ownerHash($request, true), $data['checkout_key'], $locale);
        $request->session()->put('commerce.last_order', $order->id);

        return $this->paymentRedirect($order, $orders, $readiness, $locale);
    }

    public function order(Request $request, OrderStore $orders, string $locale, string $id)
    {
        $owner = $this->ownerHash($request, false);
        abort_if($owner === '', 404);
        $orders->expireBankTransfers();
        $order = $orders->owned($id, $owner);
        $items = $orders->items($id);
        if ($order->status === 'paid') {
            $ordered = [];
            foreach ($items as $item) {
                $ordered[$item->line_key] = (int) $item->quantity;
            }
            $current = $request->session()->get('commerce.cart', []);
            ksort($ordered);
            if (is_array($current)) {
                ksort($current);
                if ($current === $ordered && $request->session()->get('commerce.checkout_key') === $order->checkout_key) {
                    $request->session()->forget(['commerce.cart', 'commerce.checkout_key', 'commerce.quote_hash', 'commerce.terms_accepted_at']);
                }
            }
        }

        return view('commerce.order', compact('order', 'items', 'locale') + ['bankReady' => app(CheckoutReadiness::class)->bankReady(),
            'documents' => config('dashboard.enabled') ? app(DocumentStore::class)->forOrder($id) : [],
            'cardReady' => app(CheckoutReadiness::class)->cardReady(), 'deadline' => app(BankTransfer::class)->deadline($order)->setTimezone('Europe/Paris')]);
    }

    public function commercialDocument(Request $request, OrderStore $orders, string $locale, string $id, string $document)
    {
        abort_unless(config('dashboard.enabled'), 404);
        $owner = $this->ownerHash($request, false);
        abort_if($owner === '', 404);
        $orders->owned($id, $owner);
        $store = app(DocumentStore::class);
        $record = $store->find($document);
        abort_unless($record['status'] === 'issued' && $record['data']['order_id'] === $id, 404);

        return response($store->pdf($record), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$record['number'].'.pdf"']);
    }

    public function retry(Request $request, OrderStore $orders, CheckoutReadiness $readiness, string $locale, string $id)
    {
        abort_unless($readiness->bankReady(), 503);
        $owner = $this->ownerHash($request, false);
        abort_if($owner === '', 404);
        $order = $orders->owned($id, $owner);

        if ($order->status === 'pending_payment_details') {
            $order = $orders->activateDeferredPayment($id);
            if ($order->status === 'pending_payment_details') {
                return redirect()->route('order.show', compact('locale', 'id'))->withErrors(['payment_method' => __('commerce.card_deadline_too_close')]);
            }
        }

        return $this->paymentRedirect($order, $orders, $readiness, $locale);
    }

    public function bank(Request $request, OrderStore $orders, BankTransfer $bank, string $locale, string $id)
    {
        $owner = $this->ownerHash($request, false);
        abort_if($owner === '', 404);
        $order = $orders->owned($id, $owner);
        $orders->expireBankTransfers();
        $order = $orders->owned($id, $owner);
        if ($order->status === 'pending_payment_details') {
            return redirect()->route('order.show', compact('locale', 'id'));
        }
        if ($order->status === 'paid') {
            return redirect()->route('order.show', compact('locale', 'id'));
        }
        $snapshot = $bank->snapshot($order);
        $active = $order->status === 'pending_bank_transfer' && now()->lt($bank->deadline($order));
        $deadline = $bank->deadline($order)->setTimezone('Europe/Paris');

        return view('commerce.bank-transfer', compact('order', 'snapshot', 'active', 'deadline', 'locale')
            + ['bankReady' => app(CheckoutReadiness::class)->bankReady(), 'items' => $orders->items($id)]);
    }

    public function chooseBank(Request $request, OrderStore $orders, CheckoutReadiness $readiness, string $locale, string $id)
    {
        abort_unless($readiness->bankReady(), 503);
        $owner = $this->ownerHash($request, false);
        abort_if($owner === '', 404);
        $order = $orders->owned($id, $owner);
        if ($order->status === 'pending_payment_details') {
            $order = $orders->activateDeferredPayment($id);
        }
        if ($order->status === 'paid') {
            return redirect()->route('order.show', compact('locale', 'id'));
        }
        try {
            app(BankTransfer::class)->switch($order, $orders);
        } catch (Throwable) {
            // No banking instructions are exposed while an earlier card payment might still complete.
        }

        return redirect()->route('order.bank', compact('locale', 'id'));
    }

    public function document(Request $request, OrderStore $orders, OrderPdf $pdf, string $locale, string $id, string $kind)
    {
        $owner = $this->ownerHash($request, false);
        abort_if($owner === '', 404);
        $order = $orders->owned($id, $owner);
        if ($kind === 'bank') {
            abort_unless($order->status === 'pending_bank_transfer' && now()->lt(app(BankTransfer::class)->deadline($order))
                && app(BankTransfer::class)->snapshot($order), 404);
        }

        return response($pdf->render($order, $orders->items($id), $kind, $locale))->withHeaders([
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="HOMYA-'.$kind.'-'.$id.'.pdf"',
        ]);
    }

    public function proof(Request $request, OrderStore $orders, TransferProof $proof, string $locale, string $id)
    {
        $owner = $this->ownerHash($request, false);
        abort_if($owner === '', 404);
        $order = $orders->owned($id, $owner);
        abort_unless($order->status === 'pending_bank_transfer' && now()->lt(app(BankTransfer::class)->deadline($order))
            && app(BankTransfer::class)->snapshot($order), 409);
        $request->validate(['proof' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg', 'extensions:pdf,png,jpg,jpeg', 'max:2048']]);
        $proof->store($order, $request->file('proof'));

        return redirect()->route('order.bank', compact('locale', 'id'))->with('commerce_message', __('commerce.proof_received'));
    }

    private function paymentRedirect(object $order, OrderStore $orders, CheckoutReadiness $readiness, string $locale)
    {
        $id = $order->id;
        if ($order->status === 'pending_bank_transfer') {
            return redirect()->route('order.bank', compact('locale', 'id'));
        }
        if ($order->status !== 'pending_payment') {
            return redirect()->route('order.show', compact('locale', 'id'));
        }
        if ($readiness->bankReady()) {
            try {
                $order = $orders->owned($id, $order->owner_hash);
                app(BankTransfer::class)->switch($order, $orders);
            } catch (Throwable) {
                // Keep the existing card reservation and show the reconciliation message without a RIB.
            }
        }

        return redirect()->route('order.bank', compact('locale', 'id'));
    }

    private function ownerHash(Request $request, bool $create): string
    {
        $owner = $request->session()->get('commerce.owner');
        if (! is_string($owner) && $create) {
            $owner = bin2hex(random_bytes(32));
            $request->session()->put('commerce.owner', $owner);
        }

        return is_string($owner) ? hash('sha256', $owner) : '';
    }

    private function quoteHash(array $quote): string
    {
        return hash('sha256', json_encode(array_map(fn ($line) => [$line['key'], $line['quantity'], $line['line_cents']], $quote['lines']), JSON_THROW_ON_ERROR));
    }
}
