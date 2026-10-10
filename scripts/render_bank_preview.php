<?php

use App\Services\Commerce\OrderPdf;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\ViewErrorBag;

// Isolated visual fixture. No database, actual bank details, order creation or change to .env.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
view()->share('errors', new ViewErrorBag);
$order = (object) ['id' => '00000000-0000-4000-8000-000000000001', 'total_cents' => 24690, 'status' => 'pending_bank_transfer', 'created_at' => now()->format('Y-m-d H:i:s')];
$snapshot = ['details' => ['beneficiary' => 'Bénéficiaire fictif de test', 'bank_name' => 'Banque fictive de test', 'iban' => 'DE36000000000000000000', 'bic' => 'TESTDE00']];
$items = [(object) ['name' => 'Article de démonstration', 'variant_name' => 'Option de démonstration', 'quantity' => 2, 'line_cents' => 24690, 'reference' => 'TEST-ONLY', 'variant_reference' => 'TEST-OPTION']];
$order->customer_encrypted = Crypt::encryptString(json_encode(['bank_transfer' => $snapshot,
    'name' => 'Client fictif de démonstration', 'email' => 'fixture@example.invalid', 'address' => 'Adresse fictive de test',
    'postal_code' => '00000', 'city' => 'Ville fictive', 'country' => 'FR'], JSON_THROW_ON_ERROR));
$manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true, 32, JSON_THROW_ON_ERROR);
$css = $manifest['resources/css/app.css']['file'];
$deadline = now()->addHours(72)->setTimezone('Europe/Paris');
foreach (['fr', 'en', 'de'] as $locale) {
    app()->setLocale($locale);
    $html = view('commerce.bank-transfer', compact('order', 'snapshot', 'items', 'deadline', 'locale') + ['active' => true, 'bankReady' => true, 'errors' => new ViewErrorBag])->render();
    $html = preg_replace('/<link[^>]+href="[^"\n]*\/build\/[^"\n]+"[^>]*>/', '', $html);
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/s', '', $html);
    $html = str_replace('</head>', '<link rel="icon" href="data:,"><link rel="stylesheet" href="http://127.0.0.1:8080/build/'.$css.'"></head>', $html);
    $html = str_replace(rtrim(config('app.url'), '/'), 'http://127.0.0.1:8080', $html);
    $html = str_replace('</body>', '<script>'.file_get_contents(resource_path('js/app.js')).'</script><script>document.querySelectorAll("form").forEach(f => f.addEventListener("submit", e => e.preventDefault()));</script></body>', $html);
    foreach (['bank', 'summary'] as $kind) {
        $url = route('order.document', ['locale' => $locale, 'id' => $order->id, 'kind' => $kind]);
        $html = str_replace(str_replace(rtrim(config('app.url'), '/'), 'http://127.0.0.1:8080', $url), '/'.$kind.'-document-'.$locale.'.pdf', $html);
        file_put_contents(base_path('output/qa-commerce/'.$kind.'-document-'.$locale.'.pdf'), app(OrderPdf::class)->render($order, $items, $kind, $locale));
    }
    $html = str_replace('<main ', '<p style="padding:20px;background:#fff0bf;font-weight:bold">APERÇU TECHNIQUE — coordonnées fictives, aucun virement possible.</p><main ', $html);
    file_put_contents(base_path('output/qa-commerce/bank-preview-'.$locale.'.html'), $html);
}
echo "Three isolated bank page fixtures rendered. No database or bank configuration accessed.\n";
$longOrder = clone $order;
$longOrder->total_cents = 20 * 12345;
$longItems = array_fill(0, 20, (object) ['name' => str_repeat('W', 500), 'variant_name' => 'Test long', 'reference' => str_repeat('W', 255), 'variant_reference' => '', 'quantity' => 1, 'line_cents' => 12345]);
app()->setLocale('fr');
file_put_contents(base_path('output/qa-commerce/summary-multipage-fr.pdf'), app(OrderPdf::class)->render($longOrder, $longItems, 'summary', 'fr'));
