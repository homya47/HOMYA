<?php

namespace App\Services\Commerce;

use App\Services\Catalogue;
use App\Services\LegalInformation;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

/** Small text-only A4 PDFs. No HTML parser, network, scripts or attachments. */
final class OrderPdf
{
    public function render(object $order, iterable $items, string $kind, string $locale): string
    {
        $customer = json_decode(Crypt::decryptString($order->customer_encrypted), true, 32, JSON_THROW_ON_ERROR);
        $lines = [__('commerce.pdf_'.$kind), __('commerce.order_reference').' '.$order->id,
            __('commerce.pdf_date').' '.substr($order->created_at, 0, 10), ''];
        if ($kind === 'bank') {
            $snapshot = app(BankTransfer::class)->snapshot($order);
            foreach (['beneficiary', 'bank_name', 'iban', 'bic'] as $field) {
                $lines[] = __('commerce.bank_'.$field).' : '.$snapshot['details'][$field];
            }
            $lines[] = __('commerce.bank_reference').' : '.$order->id;
            $lines[] = __('commerce.bank_due').' '.app(BankTransfer::class)->deadline($order)->setTimezone('Europe/Paris')->format('d/m/Y H:i').' (Europe/Paris)';
            $lines[] = __('commerce.bank_deadline_notice');
            $lines[] = __('commerce.bank_reference_help');
            $lines[] = __('commerce.bank_receipt_notice');
        } else {
            foreach (['name', 'email', 'address', 'postal_code', 'city', 'country'] as $field) {
                $lines[] = __('commerce.'.$field).' : '.$customer[$field];
            }
            $lines[] = '';
            foreach ($items as $item) {
                $lines[] = $item->name.($item->variant_name ? ' / '.$item->variant_name : '');
                $lines[] = __('catalogue.reference').' '.$item->reference.($item->variant_reference ? ' / '.$item->variant_reference : '');
                $lines[] = __('commerce.quantity').' '.$item->quantity.' - '.Catalogue::money($item->line_cents, $locale);
                $lines[] = '';
            }
            $lines[] = __('commerce.shipping_free');
            $lines[] = __('commerce.pdf_summary_notice');
            $lines[] = __('commerce.status.'.$order->status);
            if ($order->status === 'pending_payment_details') {
                $lines[] = __('commerce.deferred_notice');
                $lines[] = __('commerce.deferred_deadline', ['deadline' => app(BankTransfer::class)->deadline($order)->setTimezone('Europe/Paris')->format('d/m/Y H:i')]);
            }
            $lines[] = '';
            $lines[] = __('commerce.total_tax_included').' : '.Catalogue::money($order->total_cents, $locale);
            $legalInformation = app(LegalInformation::class);
            $address = $legalInformation->returnAddress();
            $mediator = $legalInformation->mediator();
            if ($address !== null || ($mediator !== null && $mediator['membership_active'])) {
                $lines[] = "\f";
                $lines[] = __('legal.related');
                $lines[] = '';
            }
            if ($address !== null) {
                $lines[] = __('legal.return_address').' : HOMYA, '.$address;
            }
            if ($mediator !== null && $mediator['membership_active']) {
                $lines[] = '';
                $lines[] = __('legal.mediator_name').' : '.$mediator['name'];
                $lines[] = $mediator['address'];
                $lines[] = $mediator['website'];
                foreach (['email', 'phone'] as $field) {
                    if (isset($mediator[$field])) {
                        $lines[] = __('legal.company_labels.'.$field).' : '.$mediator[$field];
                    }
                }
            }
        }
        if ($kind === 'bank') {
            $lines[] = '';
            $lines[] = __('commerce.total_tax_included').' : '.Catalogue::money($order->total_cents, $locale);
        }
        $wrapped = [];
        foreach ($lines as $line) {
            if ($line === "\f") {
                $wrapped[] = null;

                continue;
            }
            $clean = preg_replace('/[\x00-\x1f\x7f\p{Z}]/u', ' ', $line);
            $encoded = mb_convert_encoding($clean, 'Windows-1252', 'UTF-8');
            if (mb_convert_encoding($encoded, 'UTF-8', 'Windows-1252') !== $clean) {
                throw ValidationException::withMessages(['document' => __('commerce.pdf_character_notice')]);
            }
            // Fixed narrow wrapping also handles long unbroken references.
            foreach (explode("\n", wordwrap($encoded, 48, "\n", true)) as $part) {
                $wrapped[] = $part;
            }
        }
        $pages = [[]];
        foreach ($wrapped as $row) {
            $current = count($pages) - 1;
            if ($row === null) {
                if ($pages[$current] !== []) {
                    $pages[] = [];
                }

                continue;
            }
            if (count($pages[$current]) === 38) {
                $pages[] = [];
                $current++;
            }
            $pages[$current][] = $row;
        }
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => '',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];
        $kids = [];
        foreach ($pages as $index => $rows) {
            $id = 4 + $index * 2;
            $kids[] = "$id 0 R";
            $objects[$id] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.($id + 1).' 0 R >>';
            $stream = "q 1 0.82 0.32 rg 48 775 500 4 re f Q BT /F1 20 Tf 48 805 Td (HOMYA) Tj ET\nBT /F1 11 Tf 16 TL 48 740 Td\n";
            foreach ($rows as $row) {
                $escaped = strtr($row, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
                $stream .= '('.$escaped.") Tj T*\n";
            }
            $stream .= 'ET BT /F1 9 Tf 48 35 Td (HOMYA - '.($index + 1).' / '.count($pages).') Tj ET';
            $objects[$id + 1] = '<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Count '.count($pages).' /Kids ['.implode(' ', $kids).'] >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$body\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1).' /Root 1 0 R >>'."\nstartxref\n$xref\n%%EOF\n";
    }
}
