<?php

namespace App\Services;

class ContactChannels
{
    public function email(): ?string
    {
        $email = config('contact.email');

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public function phone(): ?string
    {
        $phone = config('contact.phone');

        if (! is_string($phone) || ! preg_match('/\A\+[1-9][0-9]{6,14}\z/', $phone)) {
            return null;
        }

        return $phone;
    }

    public function links(): array
    {
        $phone = $this->phone();

        return $phone === null ? [] : [
            'whatsapp' => 'https://wa.me/'.substr($phone, 1),
            'sms' => 'sms:'.$phone,
        ];
    }
}
