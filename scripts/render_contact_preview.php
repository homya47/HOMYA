<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\ViewErrorBag;

// Inactive visual fixture only. This number never changes .env or contact configuration on the site.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
view()->share('errors', new ViewErrorBag);
config(['contact.phone' => '+33123456789']);
$manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true, 32, JSON_THROW_ON_ERROR);
foreach (['fr', 'en', 'de'] as $locale) {
    app()->setLocale($locale);
    $page = 'conseiller';
    $content = __('pages.entries.conseiller');
    $languageLinks = [];
    $catalogueReady = false;
    $html = view('information', compact('locale', 'page', 'content', 'languageLinks', 'catalogueReady'))->render();
    $html = preg_replace('/<link[^>]+href="[^"\n]*\/build\/[^"\n]+"[^>]*>/', '', $html);
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/s', '', $html);
    $html = str_replace(rtrim(config('app.url'), '/'), 'http://127.0.0.1:8080', $html);
    $html = str_replace('</head>', '<link rel="icon" href="data:,"><link rel="stylesheet" href="http://127.0.0.1:8080/build/'.$manifest['resources/css/app.css']['file'].'"></head>', $html);
    $html = str_replace('<main ', '<p style="padding:20px;background:#fff0bf;font-weight:bold">APERÇU TECHNIQUE — numéro fictif, ne pas contacter. Aucun message envoyé.</p><main ', $html);
    $html = str_replace('</body>', '<script>document.querySelectorAll("a[href^=\"https://wa.me/\"],a[href^=\"sms:\"]").forEach(a=>a.addEventListener("click",e=>e.preventDefault()));</script></body>', $html);
    file_put_contents(base_path('output/qa-commerce/contact-preview-'.$locale.'.html'), $html);
}
echo "Three contact fixtures rendered. No message sent or site configuration changed.\n";
