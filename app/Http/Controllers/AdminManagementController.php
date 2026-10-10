<?php

namespace App\Http\Controllers;

use App\Services\Admin\Activity;
use App\Services\Admin\Deliveries;
use App\Services\Admin\Integrations;
use App\Services\Admin\Messages;
use App\Services\Admin\PrivateSettings;
use App\Services\Admin\ProductManager;
use App\Services\Catalogue;
use App\Services\Commerce\BankTransfer;
use App\Services\Commerce\CheckoutReadiness;
use App\Services\Commerce\OrderStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class AdminManagementController extends Controller
{
    public function callAction($method, $parameters)
    {
        try {
            abort_unless(config('dashboard.enabled'), 503);

            return $this->{$method}(...array_values($parameters));
        } catch (ValidationException $error) {
            return back()->withErrors($error->errors()); // Never flash customer text or configuration secrets.
        } catch (\Throwable $error) {
            return response()->view('admin.unavailable', ['status' => $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 503], $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 503);
        }
    }

    public function product(ProductManager $products, ?string $slug = null)
    {
        return view('admin.product', ['product' => $products->form($slug)]);
    }

    public function saveProduct(Request $request, ProductManager $products, ?string $slug = null)
    {
        $rules = ['reference' => 'required|string|max:255', 'revision' => 'required|integer|min:0', 'price' => ['required', 'regex:/\A[0-9]{1,6}(?:[.,][0-9]{1,2})?\z/'], 'quantity' => 'required|integer|min:0|max:10000000', 'condition' => ['nullable', Rule::requiredIf($request->boolean('published')), Rule::in(['new', 'neuf', 'used', 'occasion', 'refurbished', 'reconditionne'])], 'published' => 'nullable|boolean', 'brand' => 'required|string|max:200', 'category' => 'required|string|max:200', 'universe' => ['required', Rule::in(['firewood', 'heating', 'appliances', 'furniture', 'televisions', 'other'])], 'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120', 'images' => 'nullable|array|max:5', 'images.*' => 'file|mimes:jpg,jpeg,png,webp|max:5120', 'remove_images' => 'nullable|array|max:20', 'remove_images.*' => 'integer|min:0|max:19'];
        foreach (['fr', 'en', 'de'] as $locale) {
            $rules['languages.'.$locale.'.name'] = ($locale === 'fr' ? 'required' : 'nullable').'|string|max:255';
            $rules['languages.'.$locale.'.description'] = 'nullable|string|max:20000';
            $rules['languages.'.$locale.'.category'] = ($locale === 'fr' ? 'required' : 'nullable').'|string|max:200';
        }
        $data = $request->validate($rules);
        if (Catalogue::cents($data['price']) < 1) {
            throw ValidationException::withMessages(['price' => 'Le prix TTC doit être strictement positif.']);
        }
        foreach (['en', 'de'] as $locale) {
            if (! empty($data['languages'][$locale]['name']) && empty($data['languages'][$locale]['category'])) {
                throw ValidationException::withMessages(['languages' => 'Renseignez aussi la catégorie traduite pour chaque langue publiée.']);
            }
        }
        $id = $products->save($slug, $data, $request->file('image'), $request->file('images', []));

        return to_route('admin.product.edit', ['slug' => $id])->with('admin_message', 'Produit enregistré. Prix et stock synchronisés.');
    }

    public function inbox(Messages $messages)
    {
        return view('admin.inbox', ['threads' => $messages->inbox()]);
    }

    public function thread(Request $request, Messages $messages, string $id)
    {
        $request->session()->put('admin.reply_nonce.'.$id, (string) Str::uuid());

        return view('admin.thread', $messages->thread($id) + ['emailReady' => $messages->emailReady()]);
    }

    public function reply(Request $request, Messages $messages, string $id)
    {
        $data = $request->validate(['body' => 'required|string|max:5000', 'nonce' => 'required|uuid', 'email' => 'nullable|boolean']);
        abort_unless($request->session()->get('admin.reply_nonce.'.$id) === $data['nonce'], 419);
        $messages->reply($id, $data['body'], $data['nonce'], true, (bool) ($data['email'] ?? false));

        return to_route('admin.thread', ['id' => $id])->with('admin_message', 'Réponse publiée dans le dossier privé du client.');
    }

    public function readThread(string $id)
    {
        abort_unless(app(OrderStore::class)->connection()->table('commerce_admin_threads')->where('id', $id)->update(['unread' => false]), 404);

        return to_route('admin.thread', ['id' => $id])->with('admin_message', 'Conversation marquée comme lue.');
    }

    public function activity(Activity $activity)
    {
        return view('admin.activity', $activity->traffic());
    }

    public function notifications(Activity $activity)
    {
        return response()->json($activity->counts());
    }

    public function readNotifications(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array|max:100', 'ids.*' => 'required|string|size:64']);
        app(OrderStore::class)->connection()->table('commerce_admin_events')->whereIn('id', $data['ids'])->whereNotIn('type', Activity::TRAFFIC)->update(['read_at' => now()]);

        return to_route('admin.activity')->with('admin_message', 'Notifications affichées marquées comme lues.');
    }

    public function deliveries(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(array_keys(Deliveries::LABELS))]]);
        $query = app(OrderStore::class)->connection()->table('commerce_orders as o')->leftJoin('commerce_admin_shipments as s', 's.order_id', '=', 'o.id')->where('o.status', 'paid')->select('o.id', 'o.created_at', 's.status', 's.carrier', 's.tracking_number');
        if (! empty($data['status'])) {
            $query->where('s.status', $data['status']);
        }

        return view('admin.deliveries', ['deliveries' => $query->orderByDesc('o.created_at')->paginate(20)->withQueryString()]);
    }

    public function delivery(Request $request, Deliveries $deliveries, string $id)
    {
        $data = $request->validate(['revision' => 'required|integer|min:0', 'status' => ['required', Rule::in(array_keys(Deliveries::LABELS))], 'carrier' => 'nullable|string|max:200', 'tracking_number' => 'nullable|string|max:180', 'tracking_url' => 'nullable|url:https|max:500', 'confirmed' => 'sometimes|accepted']);
        if (! empty($data['tracking_url']) && parse_url($data['tracking_url'], PHP_URL_USER)) {
            throw ValidationException::withMessages(['tracking_url' => 'Le lien de suivi ne doit pas contenir d’identifiants.']);
        }
        $deliveries->save($id, $data);

        return back()->with('admin_message', 'Livraison mise à jour.');
    }

    public function cancel(Request $request, Deliveries $deliveries, string $id)
    {
        $request->validate(['confirmed' => 'required|accepted']);
        $deliveries->cancel($id);

        return back()->with('admin_message', 'Commande annulée et réservation libérée.');
    }

    public function bankReceipt(Request $request, OrderStore $orders, string $id)
    {
        $this->reauthenticate($request);
        $data = $request->validate(['amount' => ['required', 'regex:/\A[0-9]{1,6}(?:[.,][0-9]{1,2})?\z/'], 'reference' => 'required|string|max:200', 'confirmed' => 'required|accepted']);
        $orders->confirmBankReceipt($id, Catalogue::cents($data['amount']), $data['reference']);

        return back()->with('admin_message', 'Réception bancaire confirmée. Paiement enregistré.');
    }

    public function settings(PrivateSettings $settings, CheckoutReadiness $readiness)
    {
        return view('admin.settings', ['version' => $settings->version(), 'readiness' => $readiness]);
    }

    public function saveSettings(Request $request, PrivateSettings $settings)
    {
        $this->reauthenticate($request);
        $data = $request->validate(['version' => 'required|string|size:64', 'email' => 'nullable|email:rfc|max:254', 'phone' => ['nullable', 'regex:/\A\+[1-9][0-9]{7,14}\z/'], 'beneficiary' => 'nullable|string|max:200', 'iban' => 'nullable|string|max:42', 'bic' => 'nullable|string|max:19', 'bank_name' => 'nullable|string|max:200', 'bank_enabled' => 'nullable|boolean']);
        $bank = ['enabled' => (bool) ($data['bank_enabled'] ?? false), 'beneficiary' => $data['beneficiary'] ?? '', 'iban' => strtoupper(preg_replace('/\s+/', '', $data['iban'] ?? '')), 'bic' => strtoupper($data['bic'] ?? ''), 'bank_name' => $data['bank_name'] ?? ''];
        $before = config('commerce.bank');
        try {
            config(['commerce.bank' => $bank]);
            if ($bank['enabled'] && app(BankTransfer::class)->details() === null) {
                throw ValidationException::withMessages(['iban' => 'Le RIB est incomplet ou l’IBAN/BIC est invalide.']);
            }
        } finally {
            config(['commerce.bank' => $before]);
        }
        $values = ['HOMYA_CONTACT_EMAIL' => $data['email'] ?? '', 'HOMYA_CONTACT_PHONE' => $data['phone'] ?? '', 'HOMYA_BANK_TRANSFER_ENABLED' => $bank['enabled'] ? 'true' : 'false', 'HOMYA_BANK_BENEFICIARY' => $bank['beneficiary'], 'HOMYA_BANK_IBAN' => $bank['iban'], 'HOMYA_BANK_BIC' => $bank['bic'], 'HOMYA_BANK_NAME' => $bank['bank_name']];
        $settings->save($values, $data['version']);
        app(Activity::class)->record('settings_saved', 'Paiements et contact', null, (string) Str::uuid());

        return back()->with('admin_message', 'Paramètres enregistrés. Vérifiez les états de disponibilité ci-dessous.');
    }

    public function integrations(PrivateSettings $settings, Integrations $integrations)
    {
        return view('admin.integrations', ['version' => $settings->version(), 'integrations' => $integrations, 'reports' => $integrations->reports()]);
    }

    public function saveIntegrations(Request $request, PrivateSettings $settings)
    {
        $this->reauthenticate($request);
        $map = ['client_id' => 'HOMYA_GOOGLE_CLIENT_ID', 'client_secret' => 'HOMYA_GOOGLE_CLIENT_SECRET', 'refresh_token' => 'HOMYA_GOOGLE_REFRESH_TOKEN', 'ga4' => 'HOMYA_GA4_PROPERTY_ID', 'gsc' => 'HOMYA_GSC_SITE', 'ads' => 'HOMYA_GOOGLE_ADS_CUSTOMER_ID', 'ads_manager' => 'HOMYA_GOOGLE_ADS_MANAGER_ID', 'developer_token' => 'HOMYA_GOOGLE_ADS_DEVELOPER_TOKEN', 'merchant' => 'HOMYA_MERCHANT_ACCOUNT_ID', 'gtm' => 'HOMYA_GTM_CONTAINER_ID', 'meta_account' => 'HOMYA_META_ACCOUNT_ID', 'access_token' => 'HOMYA_META_ACCESS_TOKEN', 'meta_version' => 'HOMYA_META_API_VERSION'];
        $rules = ['version' => 'required|string|size:64'];
        foreach ($map as $key => $env) {
            $rules[$key] = ['nullable', 'string', 'max:4096', 'not_regex:/[\r\n\x00\x27]/'];
        }
        foreach (['ga4', 'ads', 'ads_manager', 'merchant'] as $key) {
            $rules[$key][] = 'regex:/\A[0-9]{1,30}\z/';
        }
        $rules['gtm'][] = 'regex:/\AGTM-[A-Z0-9]+\z/';
        $rules['meta_account'][] = 'regex:/\Aact_[0-9]{5,30}\z/';
        $rules['meta_version'][] = 'regex:/\Av[0-9]{2}\.0\z/';
        $data = $request->validate($rules);
        if (! empty($data['gsc']) && ! str_starts_with($data['gsc'], 'sc-domain:') && ! filter_var($data['gsc'], FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages(['gsc' => 'Propriété Search Console : domaine sc-domain:… ou URL de propriété.']);
        }
        $values = [];
        foreach ($map as $key => $env) {
            if (! empty($data[$key])) {
                $values[$env] = $data[$key];
            }
        }
        $settings->save($values, $data['version']);
        app(Activity::class)->record('settings_saved', 'Intégrations', null, (string) Str::uuid());

        return back()->with('admin_message', 'Configuration enregistrée. Synchronisez chaque service pour vérifier son accès.');
    }

    public function synchronize(Integrations $integrations, string $service)
    {
        try {
            $integrations->synchronize($service);
        } catch (\Throwable) {
            return back()->withErrors(['integration' => 'Lecture impossible. Vérifiez les identifiants, autorisations, version d’API et prérequis du compte. Les résultats précédents restent datés.']);
        }

        return back()->with('admin_message', 'Données du service actualisées.');
    }

    public function googleConnect(Request $request, PrivateSettings $settings)
    {
        if (! config('dashboard.google.client_id') || ! config('dashboard.google.client_secret')) {
            return to_route('admin.integrations')->withErrors(['integration' => 'Enregistrez d’abord l’identifiant et le secret OAuth Google.']);
        }
        $state = Str::random(64);
        $request->session()->put('admin.google_oauth', ['state' => $state, 'created' => now()->timestamp, 'version' => $settings->version()]);
        $query = http_build_query(['client_id' => config('dashboard.google.client_id'), 'redirect_uri' => route('admin.google.callback'), 'response_type' => 'code', 'scope' => 'https://www.googleapis.com/auth/analytics.readonly https://www.googleapis.com/auth/webmasters.readonly https://www.googleapis.com/auth/adwords https://www.googleapis.com/auth/content', 'state' => $state, 'access_type' => 'offline', 'prompt' => 'consent']);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function googleCallback(Request $request, PrivateSettings $settings)
    {
        $pending = $request->session()->pull('admin.google_oauth');
        if (! is_array($pending) || ! is_string($request->query('state')) || ! hash_equals($pending['state'], $request->query('state')) || now()->timestamp - $pending['created'] > 300 || ! hash_equals($pending['version'], $settings->version())) {
            return to_route('admin.integrations')->withErrors(['integration' => 'Autorisation expirée ou invalide. Recommencez la connexion Google.']);
        }
        if (! is_string($request->query('code')) || strlen($request->query('code')) > 4096) {
            return to_route('admin.integrations')->withErrors(['integration' => 'Autorisation Google annulée ou incomplète.']);
        }
        try {
            $response = Http::asForm()->timeout(12)->connectTimeout(5)->withoutRedirecting()->post('https://oauth2.googleapis.com/token', ['client_id' => config('dashboard.google.client_id'), 'client_secret' => config('dashboard.google.client_secret'), 'code' => $request->query('code'), 'grant_type' => 'authorization_code', 'redirect_uri' => route('admin.google.callback')]);
            $data = app(Integrations::class)->json($response);
            if (! is_string($data['refresh_token'] ?? null) || $data['refresh_token'] === '') {
                throw new \RuntimeException('REFRESH_TOKEN_MISSING');
            }
            $settings->save(['HOMYA_GOOGLE_REFRESH_TOKEN' => $data['refresh_token']], $pending['version']);
        } catch (\Throwable) {
            return to_route('admin.integrations')->withErrors(['integration' => 'Connexion Google non enregistrée. Vérifiez le projet OAuth, les autorisations et le jeton de renouvellement.']);
        }

        return to_route('admin.integrations')->with('admin_message', 'Autorisation Google enregistrée. Vérifiez ensuite chaque compte par synchronisation.');
    }

    private function reauthenticate(Request $request): void
    {
        $request->validate(['current_password' => 'required|string|max:256']);
        if (! Hash::check($request->string('current_password')->toString(), config('admin.password_hash'))) {
            throw ValidationException::withMessages(['current_password' => 'Mot de passe administrateur incorrect.']);
        }
    }
}
