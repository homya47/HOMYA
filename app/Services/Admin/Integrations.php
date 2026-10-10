<?php

namespace App\Services\Admin;

use App\Services\Commerce\OrderStore;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

final class Integrations
{
    public const SERVICES = ['ga4' => 'Google Analytics 4', 'gsc' => 'Search Console', 'ads' => 'Google Ads', 'merchant' => 'Merchant Center', 'meta' => 'Meta Ads'];

    public function account(string $service): string
    {
        return (string) config($service === 'meta' ? 'dashboard.meta.account' : 'dashboard.google.'.match ($service) {
            'gsc' => 'search_console', default => $service
        });
    }

    public function configured(string $service): bool
    {
        if ($service === 'meta') {
            return $this->account($service) !== '' && config('dashboard.meta.token') !== '' && preg_match('/\Av[0-9]{2}\.0\z/', config('dashboard.meta.version'));
        }

        return $this->account($service) !== '' && config('dashboard.google.refresh_token') !== '' && config('dashboard.google.client_id') !== '' && config('dashboard.google.client_secret') !== '' && ($service !== 'ads' || config('dashboard.google.developer_token') !== '');
    }

    public function reports(): array
    {
        $rows = app(OrderStore::class)->connection()->table('commerce_admin_reports')->get();
        $reports = [];
        foreach ($rows as $row) {
            // A previous account's results must never be shown as belonging to newly configured credentials.
            if ($row->account !== $this->account($row->service)) {
                continue;
            }
            $reports[$row->service] = ['synced_at' => $row->synced_at, 'period' => $row->period, 'rows' => json_decode(Crypt::decryptString($row->payload_encrypted), true, 32, JSON_THROW_ON_ERROR)];
        }

        return $reports;
    }

    public function synchronize(string $service): void
    {
        if (! isset(self::SERVICES[$service]) || ! $this->configured($service)) {
            throw new \RuntimeException('INTEGRATION_UNCONFIGURED');
        }
        $account = $this->account($service);
        $period = $service === 'gsc' ? now()->subDays(30)->toDateString().' → '.now()->subDays(3)->toDateString() : '30 derniers jours';
        $headers = [];
        $request = Http::timeout(12)->connectTimeout(5)->withoutRedirecting()->acceptJson();
        if ($service === 'meta') {
            if (! preg_match('/\Aact_[0-9]{5,30}\z/', $account)) {
                throw new \RuntimeException('ACCOUNT_INVALID');
            }
            $response = $request->withToken(config('dashboard.meta.token'))->get('https://graph.facebook.com/'.config('dashboard.meta.version').'/'.$account.'/insights', ['fields' => 'date_start,date_stop,impressions,clicks,spend,actions', 'date_preset' => 'last_30d', 'level' => 'account', 'limit' => 30]);
        } else {
            $request = $request->withToken($this->googleToken());
            if ($service === 'gsc') {
                $response = $request->post('https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($account).'/searchAnalytics/query', ['startDate' => now()->subDays(30)->toDateString(), 'endDate' => now()->subDays(3)->toDateString(), 'dimensions' => ['date'], 'rowLimit' => 100]);
            } elseif ($service === 'ga4') {
                $this->numeric($account);
                $response = $request->post('https://analyticsdata.googleapis.com/v1beta/properties/'.$account.':runReport', ['dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'yesterday']], 'dimensions' => [['name' => 'date'], ['name' => 'sessionDefaultChannelGroup']], 'metrics' => [['name' => 'sessions'], ['name' => 'totalUsers'], ['name' => 'ecommercePurchases'], ['name' => 'purchaseRevenue']], 'limit' => 100]);
            } elseif ($service === 'ads') {
                $this->numeric($account);
                $headers = ['developer-token' => config('dashboard.google.developer_token')];
                if (config('dashboard.google.ads_manager')) {
                    $this->numeric(config('dashboard.google.ads_manager'));
                    $headers['login-customer-id'] = config('dashboard.google.ads_manager');
                }
                $response = $request->withHeaders($headers)->post('https://googleads.googleapis.com/v24/customers/'.$account.'/googleAds:search', ['query' => 'SELECT customer.currency_code, segments.date, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions FROM customer WHERE segments.date DURING LAST_30_DAYS']);
            } else {
                $this->numeric($account);
                $response = $request->get('https://merchantapi.googleapis.com/products/v1/accounts/'.$account.'/products', ['pageSize' => 100]);
                $period = 'Échantillon de 100 produits maximum';
            }
        }
        $data = $this->json($response);
        $rows = match ($service) {
            'ga4' => array_map(fn ($r) => ['date' => $r['dimensionValues'][0]['value'] ?? '', 'canal' => $r['dimensionValues'][1]['value'] ?? '', 'sessions' => $r['metricValues'][0]['value'] ?? '', 'utilisateurs' => $r['metricValues'][1]['value'] ?? '', 'achats_attribues' => $r['metricValues'][2]['value'] ?? '', 'revenu_attribue' => $r['metricValues'][3]['value'] ?? ''], $data['rows'] ?? []),
            'gsc' => array_map(fn ($r) => ['date' => $r['keys'][0] ?? '', 'clics' => $r['clicks'] ?? 0, 'impressions' => $r['impressions'] ?? 0, 'ctr' => $r['ctr'] ?? 0, 'position' => $r['position'] ?? 0], $data['rows'] ?? []),
            'ads' => array_map(fn ($r) => ['date' => $r['segments']['date'] ?? '', 'impressions' => $r['metrics']['impressions'] ?? '', 'clics' => $r['metrics']['clicks'] ?? '', 'cout_micros' => $r['metrics']['costMicros'] ?? '', 'conversions_attribuees' => $r['metrics']['conversions'] ?? '', 'devise' => $r['customer']['currencyCode'] ?? ''], $data['results'] ?? []),
            'merchant' => array_map(fn ($r) => ['produit' => $r['offerId'] ?? $r['name'] ?? '', 'titre' => $r['productAttributes']['title'] ?? '', 'problemes' => implode(', ', array_column($r['productStatus']['itemLevelIssues'] ?? [], 'code'))], $data['products'] ?? []),
            'meta' => array_map(fn ($r) => ['debut' => $r['date_start'] ?? '', 'fin' => $r['date_stop'] ?? '', 'impressions' => $r['impressions'] ?? '', 'clics' => $r['clicks'] ?? '', 'depense' => $r['spend'] ?? '', 'achats_attribues' => collect($r['actions'] ?? [])->firstWhere('action_type', 'purchase')['value'] ?? 0], $data['data'] ?? []),
        };
        app(OrderStore::class)->connection()->table('commerce_admin_reports')->updateOrInsert(['service' => $service], ['account' => $account, 'payload_encrypted' => Crypt::encryptString(json_encode(array_slice($rows, 0, 100), JSON_THROW_ON_ERROR)), 'period' => $period, 'synced_at' => now()]);
    }

    private function googleToken(): string
    {
        $response = Http::asForm()->timeout(10)->connectTimeout(5)->withoutRedirecting()->post('https://oauth2.googleapis.com/token', ['client_id' => config('dashboard.google.client_id'), 'client_secret' => config('dashboard.google.client_secret'), 'refresh_token' => config('dashboard.google.refresh_token'), 'grant_type' => 'refresh_token']);
        $data = $this->json($response);
        if (! is_string($data['access_token'] ?? null) || $data['access_token'] === '') {
            throw new \RuntimeException('GOOGLE_AUTH_FAILED');
        }

        return $data['access_token'];
    }

    public function json($response): array
    {
        if (! $response->successful() || strlen($response->body()) > 5_000_000 || ! is_array($response->json())) {
            throw new \RuntimeException('REMOTE_READ_FAILED');
        }

        return $response->json();
    }

    private function numeric(string $id): void
    {
        if (! preg_match('/\A[0-9]{1,30}\z/', $id)) {
            throw new \RuntimeException('ACCOUNT_INVALID');
        }
    }
}
