<?php

namespace App\Http\Controllers;

use App\Services\MerchantProducts;
use App\Services\Seo;
use Illuminate\Http\Request;
use Throwable;

final class MerchantFeedController extends Controller
{
    public function __invoke(Request $request, MerchantProducts $merchant, Seo $seo, string $locale)
    {
        abort_unless($seo->onPublicOrigin($request) && $merchant->ready(), 404);
        try {
            $items = $merchant->entries($locale);
            if ($items === []) {
                return response('Merchant feed unavailable.', 503)->header('X-Robots-Tag', 'noindex, nofollow');
            }
            $body = $merchant->xml($items, $locale);
        } catch (Throwable) {
            // Never render database errors, private approval files or credentials.
            return response('Merchant feed unavailable.', 503)->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return response($body)->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
