<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>
@page { margin: 35px 35px 55px; } body{font-family:'DejaVu Sans',sans-serif;font-size:10px;color:#222;line-height:1.45} h1{font-size:23px;margin:0} h2{font-size:12px;margin:12px 0 6px} p{margin:4px 0} table{width:100%;border-collapse:collapse;table-layout:fixed} td,th{padding:7px;vertical-align:top;word-wrap:break-word} .head{border-bottom:4px solid #f7c844;margin-bottom:16px} .head td{padding:0 0 12px} .brand{width:90px} .right{text-align:right} .muted{color:#555} .draft{background:#fff3bd;padding:9px;font-weight:bold;margin:12px 0} .parties td{width:50%;padding:10px;background:#f5f5f5}.lines th{background:#ffe59b;text-align:left;font-size:9px}.lines td{border-bottom:1px solid #ddd;font-size:9px}.lines thead{display:table-header-group}.lines tr{page-break-inside:avoid}.total{margin:15px 0;page-break-inside:avoid}.total td{padding:3px 7px}.grand{font-size:15px;font-weight:bold;background:#fff3bd}.conditions{white-space:pre-wrap;word-wrap:break-word}.identity{margin-top:18px;font-size:8px;border-top:1px solid #ddd;padding-top:8px} .code{font-size:8px;word-wrap:break-word}
</style></head><body>
@php($money = fn ($value) => $value === null ? 'À renseigner' : \App\Services\Documents\DocumentData::money($value))
<table class="head"><tr><td width="30%">@if($logo)<img class="brand" src="{{ $logo }}" alt="HOMYA">@else<strong>HOMYA</strong>@endif</td><td width="70%" class="right"><h1>{{ \App\Services\Documents\DocumentData::TYPES[$data['type']] }}</h1><strong>{{ $record['number'] ?? 'Sans numéro' }}</strong><p>Date : {{ isset($record['issued_at']) ? substr($record['issued_at'], 0, 10) : 'Brouillon' }}</p></td></tr></table>
@if($record['status'] === 'draft')<div class="draft">BROUILLON — NON ÉMIS — ne constitue pas une facture définitive</div>@endif
<table class="parties"><tr><td>
<h2>Vendeur / émetteur</h2>
<strong>{{ $issuer['name'] }}</strong>
<p>{{ $issuer['form'] }}<br>Capital social : {{ $issuer['capital'] }}</p>
<p>Siège social : {{ $issuer['address'] }}</p>
@if($issuer['email'] ?? null)<p>Email : {{ $issuer['email'] }}</p>@endif
@if($issuer['phone'] ?? null)<p>Téléphone : {{ $issuer['phone'] }}</p>@endif
<p>SIREN : {{ $issuer['siren'] }}<br>SIRET (siège) : {{ $issuer['siret'] ?? '' }}<br>TVA intracommunautaire : {{ $issuer['vat'] }}</p>
</td><td><h2>Client / facturation</h2>@if($data['customer']['company'])<strong>{{ $data['customer']['company'] }}</strong>@endif<p>{{ $data['customer']['name'] }}</p><p>{{ $data['customer']['address'] }}</p><p>{{ $data['customer']['postal_code'] }} {{ $data['customer']['city'] }} · {{ $data['customer']['country'] }}</p>@if($data['customer']['siren'])<p>SIREN : {{ $data['customer']['siren'] }}</p>@endif @if($data['customer']['vat'])<p>TVA : {{ $data['customer']['vat'] }}</p>@endif @if($data['customer']['email'])<p>{{ $data['customer']['email'] }}</p>@endif</td></tr></table>
@if($data['delivery_address'])<p><strong>Adresse de livraison :</strong> {{ $data['delivery_address'] }}</p>@endif
@if($data['buyer_reference'])<p><strong>Référence du bon de commande client :</strong> {{ $data['buyer_reference'] }}</p>@endif
@if($record['source_document']['number'] ?? null)<p>Document d’origine : {{ $record['source_document']['number'] }}</p>@endif
@if($data['order_id'])<p class="code">Commande e-commerce : {{ $data['order_id'] }}</p>@endif
@if($data['type'] === 'quote')<p><strong>Valable jusqu’au :</strong> {{ $data['valid_until'] ?: 'À renseigner' }}</p>@endif
@if($data['type'] === 'invoice')<p><strong>Date de vente / livraison :</strong> {{ $data['operation_date'] ?: 'À renseigner' }} · Nature : vente de biens</p>@endif
<h2>Articles · montants en EUR</h2>
<table class="lines"><thead><tr><th width="37%">Désignation / référence</th><th width="6%">Qté</th><th width="15%">P.U. HT¹</th><th width="10%">TVA</th><th width="15%">Total HT</th><th width="17%">Total TTC</th></tr></thead><tbody>@foreach($data['lines'] as $line)<tr><td><strong>{{ $line['name'] ?: 'À renseigner' }}</strong><br>{{ $line['reference'] ?: 'À renseigner' }}<br><span class="muted">P.U. TTC : {{ $money($line['unit_cents']) }}</span>@if($line['eco_cents'] !== null && $line['eco_cents'] > 0)<br>Éco-participation incluse : {{ $money($line['eco_cents']) }} / unité@endif</td><td>{{ $line['quantity'] }}</td><td>{{ $line['net_cents'] === null ? 'À renseigner' : number_format($line['net_cents'] / ($line['quantity'] * 100), 4, ',', ' ').' €' }}</td><td>{{ $data['tax_mode'] === 'taxed' ? ($line['vat_rate'] !== '' ? str_replace('.', ',', $line['vat_rate']).' %' : 'À renseigner') : ($data['tax_mode'] ? 'Non collectée' : 'À renseigner') }}</td><td>{{ $money($line['net_cents']) }}</td><td>{{ $money($line['gross_cents']) }}</td></tr>@endforeach</tbody></table>
<p class="muted">¹ Montant HT calculé et arrondi au centime sur chaque ligne à partir du prix TTC ; prix unitaire HT affiché avec quatre décimales.</p>
<table class="total"><tr><td width="60%"></td><td>Total HT</td><td class="right">{{ $money($data['net_cents']) }}</td></tr>@foreach($data['tax_groups'] as $rate => $amount)<tr><td></td><td>TVA {{ $data['tax_mode'] === 'taxed' ? str_replace('.', ',', (string) $rate).' %' : 'non collectée' }}</td><td class="right">{{ $money($amount) }}</td></tr>@endforeach<tr class="grand"><td></td><td>Total TTC</td><td class="right">{{ $money($data['total_cents']) }}</td></tr></table>
@if($data['tax_note'])<p class="conditions">{{ $data['tax_note'] }}</p>@endif
<h2>Conditions</h2><p class="conditions"><strong>Paiement :</strong> {{ $data['payment_terms'] ?: 'À renseigner' }}</p><p class="conditions"><strong>Livraison :</strong> {{ $data['delivery_terms'] ?: 'À renseigner' }}</p>
@if($data['type'] === 'invoice')<p><strong>Échéance :</strong> {{ $data['due_date'] ?: 'À renseigner' }}</p><p class="conditions"><strong>Escompte :</strong> {{ $data['discount_terms'] ?: 'À renseigner' }}</p>@if($data['professional'])<p class="conditions"><strong>Pénalités de retard :</strong> {{ $data['late_terms'] ?: 'À renseigner' }}</p><p>Indemnité forfaitaire pour frais de recouvrement : 40 € (client professionnel).</p>@endif @endif
@if($data['legal_notes'])<p class="conditions">{{ $data['legal_notes'] }}</p>@endif
<p class="muted">Ce document ne constitue pas une confirmation de réception d’un paiement.</p>
@if($data['type'] === 'quote')<p>Acceptation du client : nom, date et signature précédés de « Bon pour accord ».</p>@endif
@if($data['type'] === 'purchase_order')<p>Validation du client : nom, date et signature.</p>@endif
<div class="identity">
<strong>{{ $issuer['name'] }}</strong> · {{ $issuer['rcs'] }}
@if($issuer['rcs_registered_on'] ?? null)<br>Inscription au RCS : {{ $issuer['rcs_registered_on'] }}@endif
@if($issuer['rne_registered_on'] ?? null) · Inscription au RNE : {{ $issuer['rne_registered_on'] }}@endif
@if($issuer['manager'] ?? null)<br>Gérant : {{ $issuer['manager'] }}@endif
</div>
</body></html>
