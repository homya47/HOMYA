<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\ViewErrorBag;

// Isolated checkout visual fixture: no SQL, bank configuration or order submission.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
view()->share('errors', new ViewErrorBag);
$quote = ['lines' => [['product' => ['name' => 'Article de démonstration'], 'variant_name' => 'Option de démonstration', 'quantity' => 2, 'line_cents' => 24690]], 'total_cents' => 24690];
$key = '00000000-0000-4000-8000-000000000001';
$quoteHash = str_repeat('0', 64);
$manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true, 32, JSON_THROW_ON_ERROR);
foreach (['fr', 'en', 'de'] as $locale) {
    foreach (['both' => [true, true], 'bank' => [false, true], 'card' => [true, false]] as $mode => [$cardReady, $bankReady]) {
        app()->setLocale($locale);
        $html = view('commerce.checkout', compact('quote', 'key', 'quoteHash', 'locale', 'cardReady', 'bankReady'))->render();
        $html = preg_replace('/<link[^>]+href="[^"\n]*\/build\/[^"\n]+"[^>]*>/', '', $html);
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/s', '', $html);
        $html = str_replace(rtrim(config('app.url'), '/'), 'http://127.0.0.1:8080', $html);
        $html = str_replace('</head>', '<link rel="icon" href="data:,"><link rel="stylesheet" href="http://127.0.0.1:8080/build/'.$manifest['resources/css/app.css']['file'].'"></head>', $html);
        $html = str_replace('</body>', '<script>'.file_get_contents(resource_path('js/app.js')).'</script><script>document.querySelectorAll("form").forEach(f => f.addEventListener("submit", e => { if (!e.defaultPrevented) f.dataset.previewSubmitCount = String(Number(f.dataset.previewSubmitCount || 0) + 1); e.preventDefault(); }));</script></body>', $html);
        $html = str_replace('<main ', '<p style="padding:20px;background:#fff0bf;font-weight:bold">APERÇU TECHNIQUE — formulaire inactif, aucune commande ni paiement.</p><main ', $html);
        file_put_contents(base_path('output/qa-commerce/selection-checkout-'.$locale.($mode === 'both' ? '' : '-'.$mode).'.html'), $html);
    }
}
echo "Nine isolated checkout fixtures rendered. No SQL or payment enabled.\n";
