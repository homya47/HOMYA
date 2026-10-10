<?php

use App\Services\Admin\Activity;
use App\Services\Admin\Integrations;
use App\Services\Admin\Messages;
use App\Services\Admin\ProductManager;
use App\Services\Commerce\CheckoutReadiness;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;

// Visual fixtures only: SQLite in memory, no merchant SQL, no email/API call.
putenv('APP_ENV=testing');
putenv('HOMYA_CATALOGUE_SOURCE=files');
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['dashboard.enabled' => true, 'database.connections.commerce' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true], 'cache.default' => 'array']);
DB::purge('commerce');
(require database_path('migrations/commerce/2026_10_09_000001_create_commerce_tables.php'))->up();
(require database_path('migrations/commerce/2026_10_09_000002_create_admin_tables.php'))->up();
URL::forceRootUrl('http://127.0.0.1:8080');
$session = $app->make('session')->driver();
$session->start();
$request = Request::create('http://127.0.0.1:8080/admin/messages');
$request->setLaravelSession($session);
$app->instance('request', $request);
$app->make('view')->share('errors', new ViewErrorBag);
$ticket = app(Messages::class)->create(['name' => 'Client de démonstration', 'email' => 'fixture@example.invalid'], 'Bonjour, pourriez-vous préciser les modalités de livraison ?', (string) Str::uuid());
app(Messages::class)->reply($ticket['id'], 'Bonjour, votre dossier est suivi depuis cet espace privé. Nous pouvons consulter les informations de votre commande.', (string) Str::uuid(), true);
$session->put('admin.reply_nonce.'.$ticket['id'], (string) Str::uuid());
$version = str_repeat('a', 64);
$directory = base_path('output/qa-commerce/admin-preview');
File::ensureDirectoryExists($directory);
$pages = [
    'product' => ['admin.product', ['product' => app(ProductManager::class)->form(null)]],
    'inbox' => ['admin.inbox', ['threads' => app(Messages::class)->inbox()]],
    'thread' => ['admin.thread', app(Messages::class)->thread($ticket['id']) + ['emailReady' => false]],
    'activity' => ['admin.activity', app(Activity::class)->traffic()],
    'settings' => ['admin.settings', ['version' => $version, 'readiness' => app(CheckoutReadiness::class)]],
    'integrations' => ['admin.integrations', ['version' => $version, 'integrations' => app(Integrations::class), 'reports' => []]],
    'support' => ['support.form', ['locale' => 'fr']],
];
foreach ($pages as $name => [$view, $data]) {
    file_put_contents($directory.'/'.$name.'.html', view($view, $data)->render());
}
echo json_encode(['result' => 'rendered', 'pages' => count($pages), 'database' => 'SQLite in-memory fixtures', 'external_calls' => false]);
