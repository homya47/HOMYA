<?php

namespace App\Services;

final class LegalInformation
{
    public function returnAddress(): ?string
    {
        $address = config('legal.company.address');

        return config('legal.returns_at_registered_office') === true && is_string($address) && trim($address) !== ''
            ? trim($address) : null;
    }

    public function mediator(): ?array
    {
        $details = config('legal.mediator');
        if (! is_array($details) || ($details['verified'] ?? false) !== true) {
            return null;
        }
        $public = [];
        foreach (['name', 'address', 'website'] as $field) {
            $value = $details[$field] ?? null;
            if (! is_string($value) || trim($value) === '') {
                return null;
            }
            $public[$field] = trim($value);
        }
        $url = parse_url($public['website']);
        if (! filter_var($public['website'], FILTER_VALIDATE_URL)
            || ($url['scheme'] ?? null) !== 'https' || empty($url['host'])
            || isset($url['user']) || isset($url['pass'])) {
            return null;
        }

        $public['membership_active'] = ($details['membership_active'] ?? false) === true;
        $email = $details['email'] ?? null;
        if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $public['email'] = $email;
        }
        $phone = $details['phone'] ?? null;
        if (is_string($phone) && preg_match('/^\+[1-9][0-9]{7,14}$/D', $phone)) {
            $public['phone'] = $phone;
        }

        return $public;
    }
}
