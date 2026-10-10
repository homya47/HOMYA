<?php

namespace App\Http\Controllers;

use App\Services\Admin\Activity;
use App\Services\ReturnRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

final class ReturnController extends CommerceController
{
    public function create(Request $request, string $locale)
    {
        $request->session()->put('returns.nonce', (string) Str::uuid());

        return view('returns.form', ['locale' => $locale, 'review' => false, 'data' => $this->draft($request)]);
    }

    public function review(Request $request, string $locale)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'contract' => 'required|string|max:500',
            'email' => 'nullable|email:rfc|max:254',
            'method' => 'required|in:download',
            'logistics' => 'nullable|in:parcel,bulky,unknown',
            'notes' => 'nullable|string|max:1000',
        ]);
        $request->session()->put('returns.draft', Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR)));

        return view('returns.form', ['locale' => $locale, 'review' => true, 'data' => $data]);
    }

    public function store(Request $request, ReturnRequests $requests, string $locale)
    {
        $nonce = $request->input('nonce');
        abort_unless(is_string($nonce) && $request->session()->get('returns.nonce') === $nonce, 419);
        $previous = $request->session()->get('returns.last');
        if (is_array($previous) && $previous['nonce'] === $nonce) {
            return to_route('returns.receipt', ['locale' => $locale, 'id' => $previous['id']]);
        }
        $data = $this->draft($request);
        abort_unless($data !== [], 419);
        $record = $requests->save($data);
        try {
            app(Activity::class)->record('return_received', $record['id']);
        } catch (\Throwable) {
        }
        $request->session()->put('returns.last', ['nonce' => $nonce, 'id' => $record['id']]);
        $request->session()->put('returns.access.'.$record['id'], true);
        $request->session()->forget('returns.draft');

        return to_route('returns.receipt', ['locale' => $locale, 'id' => $record['id']]);
    }

    public function receipt(Request $request, ReturnRequests $requests, string $locale, string $id)
    {
        abort_unless($request->session()->get('returns.access.'.$id) === true, 404);
        $record = $requests->find($id);

        return view('returns.receipt', compact('record', 'locale'));
    }

    public function download(Request $request, ReturnRequests $requests, string $locale, string $id)
    {
        abort_unless($request->session()->get('returns.access.'.$id) === true, 404);
        $record = $requests->find($id);

        return response()->view('returns.document', compact('record', 'locale'))
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="HOMYA-retractation-'.$id.'.html"')
            ->header('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; sandbox");
    }

    private function draft(Request $request): array
    {
        $encrypted = $request->session()->get('returns.draft');

        return is_string($encrypted) ? json_decode(Crypt::decryptString($encrypted), true, 32, JSON_THROW_ON_ERROR) : [];
    }
}
