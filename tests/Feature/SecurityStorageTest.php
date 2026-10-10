<?php

namespace Tests\Feature;

use App\Services\Commerce\TransferProof;
use App\Services\ReturnRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class SecurityStorageTest extends TestCase
{
    private string $temporary;

    protected function setUp(): void
    {
        parent::setUp();
        DB::shouldReceive('connection')->never();
        $this->temporary = sys_get_temp_dir().'/homya-storage-boundary-'.Str::uuid();
        $this->app->useStoragePath($this->temporary.'/storage');
        File::ensureDirectoryExists(storage_path('app/private'), 0700);
        File::ensureDirectoryExists($this->temporary.'/outside', 0700);
        config(['filesystems.disks.local.root' => storage_path('app/private')]);
        Storage::forgetDisk('local');
    }

    protected function tearDown(): void
    {
        Storage::forgetDisk('local');
        File::deleteDirectory($this->temporary);
        parent::tearDown();
    }

    public function test_returns_cannot_write_through_a_preexisting_directory_link(): void
    {
        $this->assertTrue(symlink($this->temporary.'/outside', storage_path('app/private/returns')));
        try {
            app(ReturnRequests::class)->save(['name' => 'Synthetic fixture']);
            $this->fail('Linked return directory was accepted.');
        } catch (RuntimeException $error) {
            $this->assertSame('PATH_UNSAFE_OR_MISSING', $error->getMessage());
        }
        $this->assertSame([], File::files($this->temporary.'/outside'));
    }

    public function test_returns_cannot_read_a_record_through_a_linked_parent(): void
    {
        $id = (string) Str::uuid();
        File::put($this->temporary.'/outside/'.$id.'.encrypted', Crypt::encryptString(json_encode(['id' => $id])));
        $this->assertTrue(symlink($this->temporary.'/outside', storage_path('app/private/returns')));
        try {
            app(ReturnRequests::class)->find($id);
            $this->fail('Linked return directory was accepted.');
        } catch (HttpException $error) {
            $this->assertSame(404, $error->getStatusCode());
        }
    }

    public function test_private_root_link_is_rejected_before_return_storage(): void
    {
        rmdir(storage_path('app/private'));
        $this->assertTrue(symlink($this->temporary.'/outside', storage_path('app/private')));
        $this->expectException(RuntimeException::class);
        app(ReturnRequests::class)->save(['name' => 'Synthetic fixture']);
    }

    public function test_transfer_proof_cannot_write_through_a_linked_quarantine_parent(): void
    {
        File::ensureDirectoryExists(storage_path('app/private/commerce'), 0700);
        $this->assertTrue(symlink($this->temporary.'/outside', storage_path('app/private/commerce/transfer-proofs')));
        try {
            app(TransferProof::class)->store((object) ['id' => (string) Str::uuid()],
                UploadedFile::fake()->createWithContent('fixture.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"));
            $this->fail('Linked quarantine directory was accepted.');
        } catch (RuntimeException $error) {
            $this->assertSame('PATH_UNSAFE_OR_MISSING', $error->getMessage());
        }
        $this->assertSame([], File::files($this->temporary.'/outside'));
    }
}
