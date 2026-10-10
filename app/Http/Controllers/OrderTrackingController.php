<?php

namespace App\Http\Controllers;

use App\Services\Commerce\OrderTracking;
use Illuminate\Http\Request;

final class OrderTrackingController extends CommerceController
{
    public function show(string $locale)
    {
        return view('commerce.tracking', compact('locale') + ['reference' => '', 'tracking' => null, 'searched' => false]);
    }

    public function lookup(Request $request, OrderTracking $orders, string $locale)
    {
        $input = $request->input('reference');
        $reference = is_string($input) ? trim($input) : '';
        $tracking = strlen($reference) <= 36 ? $orders->find($reference) : null;

        return view('commerce.tracking', compact('locale', 'tracking') + [
            'reference' => strlen($reference) <= 36 ? $reference : '', 'searched' => true,
        ]);
    }
}
