<?php

use App\Http\Controllers\AddressLookupController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminDocumentController;
use App\Http\Controllers\AdminManagementController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InformationController;
use App\Http\Controllers\MerchantFeedController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\PromotionsController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SupportController;
use App\Http\Middleware\AdminBoundary;
use App\Http\Middleware\CommerceBoundary;
use App\Http\Middleware\SupportBoundary;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware(AdminBoundary::class)->group(function () {
    Route::get('/connexion', [AdminController::class, 'login'])->name('admin.login');
    Route::post('/connexion', [AdminController::class, 'authenticate'])->middleware('throttle:admin-login')->block()->name('admin.authenticate');
    Route::post('/deconnexion', [AdminController::class, 'logout'])->block()->name('admin.logout');
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/commandes', [AdminController::class, 'orders'])->name('admin.orders');
    Route::get('/commandes/{id}', [AdminController::class, 'order'])->whereUuid('id')->name('admin.order');
    Route::get('/documents', [AdminDocumentController::class, 'index'])->name('admin.documents.index');
    Route::get('/documents/{id}', [AdminDocumentController::class, 'edit'])->whereUuid('id')->name('admin.documents.edit');
    Route::get('/documents/{id}/pdf', [AdminDocumentController::class, 'download'])->whereUuid('id')->middleware('throttle:admin-document-download')->name('admin.documents.pdf');
    Route::get('/stocks', [AdminController::class, 'inventory'])->name('admin.inventory');
    Route::get('/retours', [AdminController::class, 'returns'])->name('admin.returns');
    Route::get('/retours/{id}', [AdminController::class, 'returnRequest'])->whereUuid('id')->name('admin.return');
    Route::get('/produits/nouveau', [AdminManagementController::class, 'product'])->name('admin.product.new');
    Route::get('/produits/{slug}', [AdminManagementController::class, 'product'])->where('slug', '[a-z0-9][a-z0-9-]{0,220}')->name('admin.product.edit');
    Route::get('/livraisons', [AdminManagementController::class, 'deliveries'])->name('admin.deliveries');
    Route::get('/messages', [AdminManagementController::class, 'inbox'])->name('admin.inbox');
    Route::get('/messages/{id}', [AdminManagementController::class, 'thread'])->whereUuid('id')->block()->name('admin.thread');
    Route::get('/activite', [AdminManagementController::class, 'activity'])->name('admin.activity');
    Route::get('/notifications', [AdminManagementController::class, 'notifications'])->middleware('throttle:60,1')->name('admin.notifications');
    Route::get('/parametres', [AdminManagementController::class, 'settings'])->name('admin.settings');
    Route::get('/integrations/google/autoriser', [AdminManagementController::class, 'googleConnect'])->middleware('throttle:admin-oauth')->block()->name('admin.google.connect');
    Route::get('/integrations/google/retour', [AdminManagementController::class, 'googleCallback'])->middleware('throttle:admin-oauth')->block()->name('admin.google.callback');
    Route::get('/integrations', [AdminManagementController::class, 'integrations'])->name('admin.integrations');
    Route::middleware('throttle:admin-write')->group(function () {
        Route::post('/documents', [AdminDocumentController::class, 'create'])->block()->name('admin.documents.create');
        Route::post('/documents/{id}', [AdminDocumentController::class, 'save'])->whereUuid('id')->block()->name('admin.documents.save');
        Route::post('/documents/{id}/emettre', [AdminDocumentController::class, 'issue'])->whereUuid('id')->block()->middleware('throttle:admin-document-issue')->name('admin.documents.issue');
        Route::post('/documents/{id}/dupliquer', [AdminDocumentController::class, 'duplicate'])->whereUuid('id')->block()->name('admin.documents.duplicate');
        Route::post('/produits/nouveau', [AdminManagementController::class, 'saveProduct'])->block()->name('admin.product.create');
        Route::post('/produits/{slug}', [AdminManagementController::class, 'saveProduct'])->where('slug', '[a-z0-9][a-z0-9-]{0,220}')->block()->name('admin.product.save');
        Route::post('/messages/{id}', [AdminManagementController::class, 'reply'])->whereUuid('id')->block()->name('admin.reply');
        Route::post('/messages/{id}/lire', [AdminManagementController::class, 'readThread'])->whereUuid('id')->block()->name('admin.thread.read');
        Route::post('/notifications/lire', [AdminManagementController::class, 'readNotifications'])->block()->name('admin.notifications.read');
        Route::post('/commandes/{id}/livraison', [AdminManagementController::class, 'delivery'])->whereUuid('id')->block()->name('admin.delivery');
        Route::post('/commandes/{id}/annuler', [AdminManagementController::class, 'cancel'])->whereUuid('id')->block()->name('admin.cancel');
        Route::post('/commandes/{id}/reception', [AdminManagementController::class, 'bankReceipt'])->whereUuid('id')->block()->name('admin.receipt');
        Route::post('/parametres', [AdminManagementController::class, 'saveSettings'])->block()->name('admin.settings.save');
        Route::post('/integrations', [AdminManagementController::class, 'saveIntegrations'])->block()->name('admin.integrations.save');
        Route::post('/integrations/{service}/synchroniser', [AdminManagementController::class, 'synchronize'])->whereIn('service', ['ga4', 'gsc', 'ads', 'merchant', 'meta'])->block()->name('admin.integrations.sync');
    });
});

Route::redirect('/', '/fr', 301);
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [SeoController::class, 'index'])->name('seo.sitemap');
Route::get('/sitemaps/{locale}/pages.xml', [SeoController::class, 'pages'])->whereIn('locale', config('homya.locales'))->name('seo.pages');
Route::get('/sitemaps/{locale}/products-{chunk}.xml', [SeoController::class, 'products'])->whereIn('locale', config('homya.locales'))->where('chunk', '[1-9][0-9]{0,4}')->name('seo.products');
Route::get('/google/merchant/{locale}.xml', MerchantFeedController::class)->whereIn('locale', config('homya.locales'))->name('merchant.feed');

Route::middleware(CommerceBoundary::class)->group(function () {
    Route::get('/{locale}/suivi', [OrderTrackingController::class, 'show'])->whereIn('locale', config('homya.locales'))->name('order.tracking');
    Route::post('/{locale}/suivi', [OrderTrackingController::class, 'lookup'])->whereIn('locale', config('homya.locales'))->middleware('throttle:10,1')->block()->name('order.tracking.lookup');
    Route::get('/{locale}/retours/declaration', [ReturnController::class, 'create'])->whereIn('locale', config('homya.locales'))->block()->name('returns.create');
    Route::post('/{locale}/retours/verifier', [ReturnController::class, 'review'])->whereIn('locale', config('homya.locales'))->middleware('throttle:return-review')->block()->name('returns.review');
    Route::post('/{locale}/retours/confirmer', [ReturnController::class, 'store'])->whereIn('locale', config('homya.locales'))->middleware('throttle:return-submit')->block()->name('returns.store');
    Route::get('/{locale}/retours/{id}/accuse', [ReturnController::class, 'receipt'])->whereIn('locale', config('homya.locales'))->whereUuid('id')->block()->name('returns.receipt');
    Route::get('/{locale}/retours/{id}/document', [ReturnController::class, 'download'])->whereIn('locale', config('homya.locales'))->whereUuid('id')->block()->name('returns.download');
    Route::post('/{locale}/commande/adresses', AddressLookupController::class)->whereIn('locale', config('homya.locales'))->middleware('throttle:address-lookup')->name('checkout.addresses');
    Route::get('/{locale}/panier', [CartController::class, 'show'])->whereIn('locale', config('homya.locales'))->name('cart.show');
    Route::post('/{locale}/panier', [CartController::class, 'add'])->whereIn('locale', config('homya.locales'))->block()->name('cart.add');
    Route::patch('/{locale}/panier/{slug}', [CartController::class, 'update'])->whereIn('locale', config('homya.locales'))->where('slug', '[a-z0-9][a-z0-9-]{0,220}(?:~v[1-9][0-9]{0,2})?')->block()->name('cart.update');
    Route::delete('/{locale}/panier/{slug}', [CartController::class, 'remove'])->whereIn('locale', config('homya.locales'))->where('slug', '[a-z0-9][a-z0-9-]{0,220}(?:~v[1-9][0-9]{0,2})?')->block()->name('cart.remove');
    Route::get('/{locale}/commande', [CheckoutController::class, 'show'])->whereIn('locale', config('homya.locales'))->block()->name('checkout.show');
    Route::post('/{locale}/commande', [CheckoutController::class, 'store'])->whereIn('locale', config('homya.locales'))->middleware('throttle:10,1')->block()->name('checkout.store');
    Route::get('/{locale}/commande/{id}/virement', [CheckoutController::class, 'bank'])->whereIn('locale', config('homya.locales'))->whereUuid('id')->block()->name('order.bank');
    Route::post('/{locale}/commande/{id}/virement', [CheckoutController::class, 'chooseBank'])->whereIn('locale', config('homya.locales'))->whereUuid('id')->middleware('throttle:10,1')->block()->name('order.choose-bank');
    Route::get('/{locale}/commande/{id}/documents/{document}', [CheckoutController::class, 'commercialDocument'])->whereIn('locale', config('homya.locales'))->whereUuid('id')->whereUuid('document')->block()->middleware('throttle:commercial-document-download')->name('order.commercial-document');
    Route::get('/{locale}/commande/{id}/document/{kind}', [CheckoutController::class, 'document'])->whereIn('locale', config('homya.locales'))->whereUuid('id')->whereIn('kind', ['bank', 'summary'])->block()->name('order.document');
    Route::post('/{locale}/commande/{id}/preuve', [CheckoutController::class, 'proof'])->whereIn('locale', config('homya.locales'))->whereUuid('id')->middleware('throttle:transfer-proof')->block()->name('order.proof');
    Route::get('/{locale}/commande/{id}', [CheckoutController::class, 'order'])->whereIn('locale', config('homya.locales'))->whereUuid('id')->block()->name('order.show');
    Route::post('/{locale}/commande/{id}/paiement', [CheckoutController::class, 'retry'])->whereIn('locale', config('homya.locales'))->whereUuid('id')->middleware('throttle:10,1')->block()->name('order.retry');
});

Route::prefix('{locale}/assistance')->middleware(SupportBoundary::class)->whereIn('locale', config('homya.locales'))->group(function () {
    Route::get('/', [SupportController::class, 'form'])->block()->name('support.form');
    Route::post('/', [SupportController::class, 'create'])->middleware('throttle:5,1')->block()->name('support.create');
    Route::post('/reprendre', [SupportController::class, 'resume'])->middleware('throttle:5,1')->block()->name('support.resume');
    Route::get('/{id}', [SupportController::class, 'thread'])->whereUuid('id')->block()->name('support.thread');
    Route::post('/{id}', [SupportController::class, 'reply'])->whereUuid('id')->middleware('throttle:10,1')->block()->name('support.reply');
});

Route::get('/{locale}/promotions', PromotionsController::class)
    ->whereIn('locale', config('homya.locales'))->name('promotions');

Route::get('/{locale}/catalogue', [CatalogueController::class, 'index'])
    ->whereIn('locale', config('homya.locales'))->name('catalogue');
Route::get('/{locale}/produit/{slug}', [CatalogueController::class, 'show'])
    ->whereIn('locale', config('homya.locales'))
    ->where('slug', '[a-z0-9][a-z0-9-]{0,220}')->name('product');

Route::get('/{locale}/{page}', InformationController::class)
    ->whereIn('locale', config('homya.locales'))
    ->whereIn('page', InformationController::PAGES)->name('information');

Route::get('/{locale}', HomeController::class)
    ->whereIn('locale', config('homya.locales'))
    ->name('home');
