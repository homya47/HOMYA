<?php

namespace Tests\Unit;

use App\Services\HostedCatalogueReadGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class HostedCatalogueReadGuardTest extends TestCase
{
    public static function invalidTargets(): array
    {
        return [
            'external host' => ['host', 'db.example.invalid'],
            'wrong port' => ['port', '3307'],
            'unsafe database identifier' => ['database', 'other-db'],
            'empty database' => ['database', ''],
            'shared writer account' => ['username', 'homya_commerce_writer'],
            'empty reader password' => ['password', ''],
            'wrong collation' => ['collation', 'utf8mb4_unicode_ci'],
            'non strict connection' => ['strict', false],
            'connection URL' => ['url', 'mysql://user:password@localhost/db'],
        ];
    }

    public function test_distinct_local_mariadb_read_account_for_catalogue_mirror_is_accepted(): void
    {
        $reader = $this->reader();
        $reader['database'] = 'homya_catalogue';
        HostedCatalogueReadGuard::validateTarget($reader, $this->writer());
        $this->assertTrue(true);
    }

    public function test_reader_and_writer_can_share_a_database_when_using_distinct_accounts(): void
    {
        HostedCatalogueReadGuard::validateTarget($this->reader(), $this->writer());
        $this->assertTrue(true);
    }

    #[DataProvider('invalidTargets')]
    public function test_unsafe_or_mismatched_reader_target_is_rejected(string $key, mixed $value): void
    {
        $reader = $this->reader();
        $reader[$key] = $value;

        $this->expectException(RuntimeException::class);
        HostedCatalogueReadGuard::validateTarget($reader, $this->writer());
    }

    public function test_empty_writer_target_is_rejected(): void
    {
        $writer = $this->writer();
        $writer['database'] = '';

        $this->expectException(RuntimeException::class);
        HostedCatalogueReadGuard::validateTarget($this->reader(), $writer);
    }

    private function reader(): array
    {
        return ['driver' => 'mariadb', 'url' => null, 'host' => 'localhost', 'port' => '3306',
            'database' => 'homya', 'username' => 'homya_catalogue_reader', 'password' => 'synthetic-only',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin', 'strict' => true, 'options' => []];
    }

    private function writer(): array
    {
        return ['database' => 'homya', 'username' => 'homya_commerce_writer'];
    }
}
