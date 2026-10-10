<?php

namespace App\Http\Controllers;

use App\Services\Commerce\CheckoutReadiness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

final class AddressLookupController extends CommerceController
{
    public function __invoke(Request $request, CheckoutReadiness $readiness)
    {
        abort_unless($readiness->ready(), 503);
        $data = $request->validate(['address' => ['required', 'string', 'min:3', 'max:200'], 'country' => ['required', 'in:FR']]);
        try {
            $response = Http::acceptJson()->connectTimeout(2)->timeout(4)->withoutRedirecting()
                ->get('https://data.geopf.fr/geocodage/search', ['q' => $data['address'], 'index' => 'address', 'limit' => 6]);
            abort_unless($response->successful() && strlen($response->body()) < 100000, 503);
            $suggestions = [];
            foreach (array_slice($response->json('features') ?? [], 0, 6) as $feature) {
                $entry = $feature['properties'] ?? [];
                if (! is_string($entry['name'] ?? null) || ! is_string($entry['city'] ?? null)
                    || ! is_string($entry['postcode'] ?? null) || ! preg_match('/\A[0-9]{5}\z/', $entry['postcode'])
                    || mb_strlen($entry['name']) > 200 || mb_strlen($entry['city']) > 100) {
                    continue;
                }
                $suggestions[] = ['address' => $entry['name'], 'postal_code' => $entry['postcode'], 'city' => $entry['city']];
            }

            return response()->json(['suggestions' => $suggestions]);
        } catch (Throwable) {
            return response()->json(['suggestions' => [], 'unavailable' => true], 503);
        }
    }
}
