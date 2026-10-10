<?php

namespace App\Http\Controllers;

use App\Services\Admin\Messages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SupportController extends CommerceController
{
    public function callAction($method, $parameters)
    {
        try {
            return parent::callAction($method, $parameters);
        } catch (ValidationException $error) {
            return back()->withErrors($error->errors());
        }
    }

    public function form(Request $request, string $locale)
    {
        abort_unless(config('dashboard.enabled'), 503);
        app()->setLocale($locale);
        $request->session()->put('support.nonce', (string) Str::uuid());

        return view('support.form', compact('locale'));
    }

    public function create(Request $request, Messages $messages, string $locale)
    {
        abort_unless(config('dashboard.enabled'), 503);
        $data = $request->validate(['name' => 'required|string|max:150', 'email' => 'nullable|email:rfc|max:254', 'body' => 'required|string|max:5000', 'nonce' => 'required|uuid']);
        abort_unless($request->session()->get('support.nonce') === $data['nonce'], 419);
        $existing = $request->session()->get('support.created.'.$data['nonce']);
        if (is_string($existing)) {
            return to_route('support.thread', ['locale' => $locale, 'id' => $existing]);
        }
        $ticket = $messages->create(['name' => $data['name'], 'email' => $data['email'] ?? ''], $data['body'], $data['nonce']);
        $request->session()->put('support.access.'.$ticket['id'], true);
        $request->session()->put('support.code.'.$ticket['id'], Crypt::encryptString($ticket['code']));
        $request->session()->put('support.created.'.$data['nonce'], $ticket['id']);

        return to_route('support.thread', ['locale' => $locale, 'id' => $ticket['id']]);
    }

    public function resume(Request $request, Messages $messages, string $locale)
    {
        abort_unless(config('dashboard.enabled'), 503);
        $data = $request->validate(['id' => 'required|uuid', 'code' => 'required|string|max:80']);
        if (! $messages->authorize($data['id'], $data['code'])) {
            return back()->withErrors(['id' => __('support.access_error', [], $locale)]);
        }
        $request->session()->regenerate();
        $request->session()->put('support.access.'.$data['id'], true);

        return to_route('support.thread', ['locale' => $locale, 'id' => $data['id']]);
    }

    public function thread(Request $request, Messages $messages, string $locale, string $id)
    {
        abort_unless(config('dashboard.enabled') && $request->session()->get('support.access.'.$id) === true, 404);
        app()->setLocale($locale);
        $request->session()->put('support.reply_nonce.'.$id, (string) Str::uuid());
        $code = $request->session()->get('support.code.'.$id);

        return view('support.thread', $messages->thread($id) + ['locale' => $locale, 'code' => is_string($code) ? Crypt::decryptString($code) : null]);
    }

    public function reply(Request $request, Messages $messages, string $locale, string $id)
    {
        abort_unless(config('dashboard.enabled') && $request->session()->get('support.access.'.$id) === true, 404);
        $data = $request->validate(['body' => 'required|string|max:5000', 'nonce' => 'required|uuid']);
        abort_unless($request->session()->get('support.reply_nonce.'.$id) === $data['nonce'], 419);
        $messages->reply($id, $data['body'], $data['nonce'], false);

        return to_route('support.thread', ['locale' => $locale, 'id' => $id]);
    }
}
