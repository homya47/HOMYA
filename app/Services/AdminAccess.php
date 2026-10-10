<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class AdminAccess
{
    public function configured(): bool
    {
        return is_string(config('admin.username')) && config('admin.username') !== ''
            && is_string(config('admin.password_hash')) && password_get_info(config('admin.password_hash'))['algo'] !== null;
    }

    public function fingerprint(): string
    {
        return hash('sha256', config('admin.username').'|'.config('admin.password_hash'));
    }

    public function valid(Request $request): bool
    {
        $state = $request->session()->get('admin.auth');
        $valid = $this->configured() && is_array($state)
            && hash_equals($this->fingerprint(), (string) ($state['fingerprint'] ?? ''))
            && now()->timestamp - ($state['started'] ?? 0) < config('admin.absolute_seconds')
            && now()->timestamp - ($state['seen'] ?? 0) < config('admin.idle_seconds');
        if ($valid) {
            $request->session()->put('admin.auth.seen', now()->timestamp);
        } else {
            $request->session()->forget('admin.auth');
        }

        return $valid;
    }

    public function login(Request $request, string $username, string $password): bool
    {
        if (! $this->configured()) {
            return false;
        }
        $passwordValid = Hash::check($password, config('admin.password_hash'));
        if (! $passwordValid || ! hash_equals(config('admin.username'), $username)) {
            return false;
        }
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        $request->session()->put('admin.auth', ['fingerprint' => $this->fingerprint(), 'started' => now()->timestamp, 'seen' => now()->timestamp]);

        return true;
    }
}
