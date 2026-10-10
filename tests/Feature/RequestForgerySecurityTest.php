<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class RequestForgerySecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Exercise the real middleware instead of Laravel's PHPUnit testing bypass.
        $this->app['env'] = 'local';
        config(['app.debug' => false, 'session.driver' => 'array', 'dashboard.enabled' => false]);
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->never();
        Http::preventStrayRequests();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->withHeaders(['Sec-Fetch-Site' => 'cross-site']);
    }

    public function test_sensitive_writes_reject_missing_and_incorrect_csrf_tokens_before_controllers(): void
    {
        $id = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $routes = [['POST', '/fr/commande'], ['POST', '/fr/assistance'], ['POST', '/fr/assistance/reprendre'],
            ['POST', '/fr/retours/verifier'], ['POST', '/fr/retours/confirmer'], ['POST', '/admin/connexion'],
            ['POST', '/admin/parametres'], ['POST', '/fr/commande/'.$id.'/preuve'],
            ['POST', '/fr/commande/'.$id.'/preuve'], ['PATCH', '/fr/panier/fixture'], ['DELETE', '/fr/panier/fixture']];
        foreach ($routes as [$method, $path]) {
            foreach ([[], ['_token' => 'invalid-fixture-token']] as $data) {
                $this->withSession(['_token' => 'valid-fixture-token'])->call($method, 'http://localhost'.$path, $data)
                    ->assertStatus(419)->assertDontSee('SQLSTATE');
            }
        }
        $this->assertSame([], app(PreventRequestForgery::class)->getExcludedPaths());
    }

    public function test_matching_csrf_token_reaches_form_validation_without_creating_a_return(): void
    {
        $this->withSession(['_token' => 'valid-fixture-token'])->post('http://localhost/fr/retours/verifier', ['_token' => 'valid-fixture-token'])
            ->assertRedirect()->assertSessionHasErrors(['name', 'contract', 'method']);
        $this->assertNull(session('returns.last'));
    }
}
