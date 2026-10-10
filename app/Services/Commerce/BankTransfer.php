<?php

namespace App\Services\Commerce;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

final class BankTransfer
{
    public const RESERVATION_HOURS = 72;

    public function details(): ?array
    {
        $details = config('commerce.bank');
        $iban = preg_replace('/\s+/', '', strtoupper((string) ($details['iban'] ?? '')));
        $bic = preg_replace('/\s+/', '', strtoupper((string) ($details['bic'] ?? '')));
        if (config('commerce.bank.enabled') !== true || ! preg_match('/\A[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}\z/', $iban)
            || ! preg_match('/\A[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}(?:[A-Z0-9]{3})?\z/', $bic)) {
            return null;
        }
        $remainder = 0;
        foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $character) {
            foreach (str_split(ctype_digit($character) ? $character : (string) (ord($character) - 55)) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }
        foreach (['beneficiary', 'bank_name'] as $key) {
            if (! is_string($details[$key] ?? null) || trim($details[$key]) === '' || mb_strlen($details[$key]) > 200) {
                return null;
            }
        }
        if ($remainder !== 1 || (isset(['FR' => 27, 'BE' => 16, 'DE' => 22][substr($iban, 0, 2)])
            && strlen($iban) !== ['FR' => 27, 'BE' => 16, 'DE' => 22][substr($iban, 0, 2)])) {
            return null;
        }

        return ['beneficiary' => trim($details['beneficiary']), 'iban' => $iban, 'bic' => $bic, 'bank_name' => trim($details['bank_name'])];
    }

    public function snapshot(object $order): ?array
    {
        $customer = json_decode(Crypt::decryptString($order->customer_encrypted), true, 32, JSON_THROW_ON_ERROR);

        return $customer['bank_transfer'] ?? null;
    }

    public function switch(object $order, OrderStore $orders): object
    {
        $details = $this->details() ?? throw new RuntimeException('Bank transfer unavailable.');
        if ($order->status === 'pending_bank_transfer') {
            return $order;
        }
        $customer = json_decode(Crypt::decryptString($order->customer_encrypted), true, 32, JSON_THROW_ON_ERROR);
        if ($order->stripe_session_id !== null || ($customer['card_attempted'] ?? false)) {
            throw new RuntimeException('Legacy card reconciliation required.');
        }

        return $orders->switchToBank($order->id, $details);
    }

    public function deadline(object $order): Carbon
    {
        // Fixed business rule confirmed by HOMYA; never reset on retry or payment-method switch.
        return Carbon::parse($order->created_at)->addHours(self::RESERVATION_HOURS);
    }
}
