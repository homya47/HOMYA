<?php

namespace App\Services\Documents;

use App\Services\AdminDashboard;
use App\Services\ContactChannels;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class DocumentData
{
    public const TYPES = ['quote' => 'Devis', 'purchase_order' => 'Bon de commande', 'invoice' => 'Facture'];

    public static function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ').' €';
    }

    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', str_replace(',', '.', $amount)), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public function blank(string $type, ?string $orderId = null): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        $data = ['type' => $type, 'order_id' => $orderId, 'customer' => ['name' => '', 'company' => '', 'email' => '', 'address' => '', 'postal_code' => '', 'city' => '', 'country' => '', 'siren' => '', 'vat' => ''], 'professional' => false, 'delivery_address' => '', 'buyer_reference' => '', 'tax_mode' => '', 'tax_note' => '', 'valid_until' => '', 'operation_date' => '', 'due_date' => '', 'payment_terms' => '', 'delivery_terms' => '', 'discount_terms' => '', 'late_terms' => '', 'legal_notes' => '', 'eco_reviewed' => false, 'guarantee_reviewed' => false, 'lines' => [['reference' => '', 'name' => '', 'quantity' => 1, 'price' => '', 'vat_rate' => '', 'eco' => '']]];
        if ($orderId) {
            $source = app(AdminDashboard::class)->order($orderId);
            $customer = $source['customer'];
            foreach (array_keys($data['customer']) as $key) {
                $data['customer'][$key] = $customer[$key] ?? '';
            }
            $data['professional'] = ! empty($customer['company']);
            $data['delivery_address'] = implode(', ', array_filter([$customer['address'] ?? '', $customer['address_extra'] ?? '', $customer['postal_code'] ?? '', $customer['city'] ?? '', $customer['country'] ?? '']));
            $data['lines'] = $this->orderLines($source);
        }

        return $data;
    }

    private function orderLines(array $source): array
    {
        $lines = [];
        foreach ($source['items'] as $item) {
            $lines[] = ['reference' => $item->variant_reference ?: $item->reference, 'name' => $item->name.($item->variant_name ? ' · '.$item->variant_name : ''), 'quantity' => $item->quantity, 'price' => self::decimal($item->unit_price_cents), 'vat_rate' => '', 'eco' => ''];
        }
        if ($source['order']->shipping_cents > 0) {
            $lines[] = ['reference' => 'LIVRAISON', 'name' => 'Frais de livraison', 'quantity' => 1, 'price' => self::decimal($source['order']->shipping_cents), 'vat_rate' => '', 'eco' => ''];
        }

        return $lines;
    }

    public function normalize(array $input, array $existing): array
    {
        $text = 'nullable|string|max:2000';
        if (is_array($input['customer'] ?? null) && is_string($input['customer']['country'] ?? null)) {
            $input['customer']['country'] = strtoupper(trim($input['customer']['country']));
        }
        $money = ['nullable', 'regex:/\A[0-9]{1,6}(?:[.,][0-9]{1,2})?\z/'];
        $rules = ['customer' => 'required|array', 'customer.name' => 'nullable|string|max:150', 'customer.company' => 'nullable|string|max:200', 'customer.email' => 'nullable|email:rfc|max:254', 'customer.address' => 'nullable|string|max:300', 'customer.postal_code' => 'nullable|string|max:15', 'customer.city' => 'nullable|string|max:100', 'customer.country' => ['nullable', 'regex:/\A[A-Z]{2}\z/'], 'customer.siren' => 'nullable|regex:/\A[0-9]{9}\z/', 'customer.vat' => 'nullable|regex:/\A[A-Z]{2}[A-Z0-9]{2,15}\z/', 'professional' => 'nullable|boolean', 'delivery_address' => 'nullable|string|max:500', 'buyer_reference' => 'nullable|string|max:100', 'tax_mode' => ['nullable', Rule::in(['taxed', 'exempt', 'reverse'])], 'tax_note' => $text, 'valid_until' => 'nullable|date_format:Y-m-d', 'operation_date' => 'nullable|date_format:Y-m-d', 'due_date' => 'nullable|date_format:Y-m-d', 'payment_terms' => $text, 'delivery_terms' => $text, 'discount_terms' => $text, 'late_terms' => $text, 'legal_notes' => $text, 'eco_reviewed' => 'nullable|boolean', 'guarantee_reviewed' => 'nullable|boolean', 'lines' => 'required|array|min:1|max:50', 'lines.*' => 'required|array', 'lines.*.reference' => 'nullable|string|max:255', 'lines.*.name' => 'nullable|string|max:600', 'lines.*.quantity' => 'required|integer|min:1|max:10000', 'lines.*.price' => $money, 'lines.*.vat_rate' => ['nullable', 'regex:/\A[0-9]{1,2}(?:[.,][0-9]{1,2})?\z/'], 'lines.*.eco' => $money];
        $data = Validator::make($input, $rules)->validate();
        $data['type'] = $existing['type'];
        $data['order_id'] = $existing['order_id'];
        foreach (['professional', 'eco_reviewed', 'guarantee_reviewed'] as $key) {
            $data[$key] = (bool) ($data[$key] ?? false);
        }
        foreach (['delivery_address', 'buyer_reference', 'tax_mode', 'tax_note', 'valid_until', 'operation_date', 'due_date', 'payment_terms', 'delivery_terms', 'discount_terms', 'late_terms', 'legal_notes'] as $key) {
            $data[$key] ??= '';
        }
        $data['customer'] = array_intersect_key($data['customer'], $this->blank($data['type'])['customer']);
        foreach ($this->blank($data['type'])['customer'] as $key => $value) {
            $data['customer'][$key] = trim((string) ($data['customer'][$key] ?? ''));
        }
        $data['lines'] = array_values(array_map(function ($line) use ($data) {
            $price = $line['price'] ?? '';
            $rate = $line['vat_rate'] ?? '';
            if ($data['tax_mode'] !== 'taxed') {
                $rate = '';
            }
            $gross = $price === '' ? null : $this->cents($price) * (int) $line['quantity'];
            $basis = $rate === '' ? null : $this->cents($rate);
            $net = $gross === null ? null : ($data['tax_mode'] === 'taxed' && $basis !== null ? intdiv($gross * 10000 + intdiv(10000 + $basis, 2), 10000 + $basis) : (in_array($data['tax_mode'], ['exempt', 'reverse'], true) ? $gross : null));
            $eco = ($line['eco'] ?? '') === '' ? null : $this->cents($line['eco']);
            if ($eco !== null && $price !== '' && $eco > $this->cents($price)) {
                throw ValidationException::withMessages(['lines' => 'L’éco-participation incluse ne peut dépasser le prix unitaire TTC.']);
            }

            return ['reference' => trim((string) ($line['reference'] ?? '')), 'name' => trim((string) ($line['name'] ?? '')), 'quantity' => (int) $line['quantity'], 'price' => (string) $price, 'vat_rate' => $basis === null ? '' : rtrim(rtrim(self::decimal($basis), '0'), '.'), 'eco' => (string) ($line['eco'] ?? ''), 'unit_cents' => $price === '' ? null : $this->cents($price), 'rate_basis' => $basis, 'gross_cents' => $gross, 'net_cents' => $net, 'tax_cents' => $net === null ? null : $gross - $net, 'eco_cents' => $eco];
        }, $data['lines']));
        $grossKnown = collect($data['lines'])->every(fn ($line) => $line['gross_cents'] !== null);
        $taxKnown = collect($data['lines'])->every(fn ($line) => $line['net_cents'] !== null);
        $data['total_cents'] = $grossKnown ? array_sum(array_column($data['lines'], 'gross_cents')) : null;
        $data['net_cents'] = $taxKnown ? array_sum(array_column($data['lines'], 'net_cents')) : null;
        $data['tax_cents'] = $taxKnown ? array_sum(array_column($data['lines'], 'tax_cents')) : null;
        $data['tax_groups'] = [];
        if ($taxKnown) {
            foreach ($data['lines'] as $line) {
                $key = $line['vat_rate'] ?: '0';
                $data['tax_groups'][$key] = ($data['tax_groups'][$key] ?? 0) + $line['tax_cents'];
            }
        }
        // An order document must describe exactly the committed sale, never an edited catalogue offer.
        if ($data['order_id']) {
            $source = app(AdminDashboard::class)->order($data['order_id']);
            $expected = $this->orderLines($source);
            foreach ($data['lines'] as $index => $line) {
                $original = $expected[$index] ?? null;
                if (! $original || $line['reference'] !== $original['reference'] || $line['name'] !== $original['name'] || $line['quantity'] !== (int) $original['quantity'] || $line['unit_cents'] !== $this->cents($original['price'])) {
                    throw ValidationException::withMessages(['lines' => 'Les articles, références, quantités et prix d’une commande liée sont conservés. Créez un document libre pour une autre offre.']);
                }
            }
            if (count($expected) !== count($data['lines']) || $data['total_cents'] !== (int) $source['order']->total_cents || $source['order']->currency !== 'EUR') {
                throw ValidationException::withMessages(['lines' => 'Le total doit correspondre exactement à la commande en euros.']);
            }
        }

        return $data;
    }

    public function missing(array $data): array
    {
        $errors = [];
        foreach (['name' => 'Nom du client', 'address' => 'Adresse de facturation', 'postal_code' => 'Code postal', 'city' => 'Ville', 'country' => 'Pays'] as $key => $label) {
            if (empty($data['customer'][$key])) {
                $errors[] = $label.' à renseigner.';
            }
        }
        foreach (['tax_mode' => 'Régime de TVA', 'payment_terms' => 'Conditions de paiement', 'delivery_terms' => 'Conditions de livraison'] as $key => $label) {
            if (empty($data[$key])) {
                $errors[] = $label.' à renseigner.';
            }
        }
        if (in_array($data['tax_mode'], ['exempt', 'reverse'], true) && empty($data['tax_note'])) {
            $errors[] = 'Renseignez le fondement légal de l’exonération ou de l’autoliquidation.';
        }
        if ($data['tax_mode'] === 'reverse' && (! $data['professional'] || empty($data['customer']['company']) || empty($data['customer']['vat']))) {
            $errors[] = 'Autoliquidation : confirmez le client professionnel, son entreprise et son numéro de TVA.';
        }
        if ($data['total_cents'] === null || $data['net_cents'] === null || $data['total_cents'] < 1) {
            $errors[] = 'Renseignez les prix et tous les taux de TVA applicables (total strictement positif).';
        }
        foreach ($data['lines'] as $line) {
            if ($line['name'] === '' || $line['reference'] === '') {
                $errors[] = 'Chaque ligne nécessite une désignation précise et une référence.';
                break;
            }
        }
        if (! $data['eco_reviewed'] || ! $data['guarantee_reviewed']) {
            $errors[] = 'Vérifiez les éco-contributions incluses et les mentions de garantie applicables.';
        }
        $today = now('Europe/Paris')->toDateString();
        if ($data['type'] === 'quote' && (empty($data['valid_until']) || $data['valid_until'] < $today)) {
            $errors[] = 'Renseignez une date de validité du devis non passée.';
        }
        if ($data['type'] === 'invoice') {
            if (empty($data['operation_date']) || $data['operation_date'] > $today) {
                $errors[] = 'Renseignez la date réelle de vente ou de livraison, au plus tard aujourd’hui.';
            }
            if (empty($data['due_date']) || empty($data['discount_terms'])) {
                $errors[] = 'Renseignez l’échéance et les conditions d’escompte.';
            }
            if ($data['professional'] && (empty($data['customer']['company']) || empty($data['late_terms']) || ($data['customer']['country'] === 'FR' && empty($data['customer']['siren'])))) {
                $errors[] = 'Client professionnel : entreprise, SIREN français et taux / conditions de pénalités de retard requis.';
            }
        }
        if ($data['order_id'] && $data['type'] !== 'quote') {
            $source = app(AdminDashboard::class)->order($data['order_id']);
            if (in_array($source['order']->status, ['cancelled', 'expired', 'bank_transfer_expired', 'failed'], true)) {
                $errors[] = 'Cette commande n’est plus active. Son document ne peut être émis.';
            }
        }

        return array_unique($errors);
    }

    public function issuer(): array
    {
        $company = config('legal.company');
        foreach (['name', 'form', 'capital', 'address', 'siren', 'vat', 'rcs'] as $key) {
            if (empty($company[$key])) {
                throw ValidationException::withMessages(['issuer' => 'Les informations légales du vendeur sont incomplètes.']);
            }
        }
        $company['email'] = app(ContactChannels::class)->email();
        $company['phone'] = app(ContactChannels::class)->phone();

        return $company;
    }
}
