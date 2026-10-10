<?php

namespace Tests\Feature;

use App\Services\ReturnRequests;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReturnRequestsTest extends TestCase
{
    private string $temporaryStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->temporaryStorage = sys_get_temp_dir().'/homya-returns-test-'.Str::uuid();
        $this->app->useStoragePath($this->temporaryStorage);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryStorage);
        parent::tearDown();
    }

    private function declaration(): array
    {
        return ['name' => 'Synthetic test customer', 'contract' => 'TEST-ORDER-ONLY: test goods', 'method' => 'download', 'logistics' => 'bulky'];
    }

    public function test_notification_is_not_recorded_until_explicit_confirmation_and_repeated_confirmation_is_idempotent(): void
    {
        $this->get('/fr/retours/declaration')->assertOk()->assertSee('Renoncer au contrat ici');
        $nonce = session('returns.nonce');
        $this->post('/fr/retours/verifier', $this->declaration())->assertOk()->assertSee('Confirmer la rétractation');
        $this->assertStringNotContainsString('Synthetic test customer', session('returns.draft'));
        $this->assertDirectoryDoesNotExist($this->temporaryStorage.'/app/private/returns');
        $response = $this->post('/fr/retours/confirmer', ['nonce' => $nonce])->assertRedirect();
        $location = $response->headers->get('Location');
        $id = session('returns.last.id');
        $this->post('/fr/retours/confirmer', ['nonce' => $nonce])->assertRedirect($location);
        $files = File::files($this->temporaryStorage.'/app/private/returns');
        $this->assertCount(1, $files);
        $this->assertStringNotContainsString('Synthetic test customer', File::get($files[0]));
        $this->assertSame(0600, fileperms($files[0]->getPathname()) & 0777);
        $record = app(ReturnRequests::class)->find($id);
        $this->assertSame($this->declaration(), $record['declaration']);
        $this->assertSame('received', $record['status']);
        $this->assertNotEmpty($record['submitted_at']);
        $this->get($location)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_acknowledgement_is_private_and_can_be_retained_offline_without_executable_customer_input(): void
    {
        $data = $this->declaration();
        $data['name'] = '<script>alert(1)</script>';
        $record = app(ReturnRequests::class)->save($data);
        $url = '/fr/retours/'.$record['id'].'/document';
        $this->get($url)->assertNotFound()->assertDontSee('alert(1)');
        $response = $this->withSession(['returns.access.'.$record['id'] => true])->get($url)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>', false)->assertSee($record['submitted_at']);
        $this->assertStringStartsWith('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->get('/fr/retours/'.Str::uuid().'/accuse')->assertNotFound();
    }

    public function test_missing_reference_does_not_block_withdrawal_and_no_reason_or_bank_details_are_required(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->get('/'.$locale.'/retours/declaration')->assertOk()->assertSee(__('returns.start', [], $locale));
        }
        $this->post('/fr/retours/verifier', ['name' => 'Synthetic customer', 'contract' => 'A stove received yesterday; reference unavailable', 'method' => 'download'])
            ->assertOk()->assertSee('Confirmer la rétractation');
        $this->assertDirectoryDoesNotExist($this->temporaryStorage.'/app/private/returns');
        $this->post('/fr/retours/confirmer', ['nonce' => 'tampered'])->assertStatus(419);
    }

    public function test_invalid_or_oversized_inputs_cannot_create_a_record(): void
    {
        $this->get('/fr/retours/declaration');
        $this->post('/fr/retours/verifier', ['name' => ['invalid'], 'contract' => str_repeat('x', 501), 'method' => 'email', 'email' => "invalid\r\nvalue", 'notes' => str_repeat('x', 1001)])
            ->assertRedirect()->assertSessionHasErrors(['name', 'contract', 'method', 'email', 'notes']);
        $this->assertDirectoryDoesNotExist($this->temporaryStorage.'/app/private/returns');
    }

    public function test_storage_failure_never_claims_that_the_declaration_was_received(): void
    {
        $this->get('/fr/retours/declaration');
        $nonce = session('returns.nonce');
        $this->post('/fr/retours/verifier', $this->declaration())->assertOk();
        File::ensureDirectoryExists($this->temporaryStorage.'/app/private');
        File::put($this->temporaryStorage.'/app/private/returns', 'synthetic-storage-conflict');
        $this->post('/fr/retours/confirmer', ['nonce' => $nonce])->assertStatus(503)
            ->assertDontSee('synthetic-sensitive-error')->assertDontSee('Déclaration de rétractation reçue');
    }
}
