<?php

namespace Tests\Unit;

use App\Services\CatalogueArchiveImporter;
use Illuminate\Database\MariaDbConnection;
use Mockery;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ArchiveWriteReplayTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_real_laravel_query_path_does_not_replay_a_lost_insert(): void
    {
        $statement = Mockery::mock(PDOStatement::class);
        $statement->shouldReceive('execute')->once()->andThrow(new PDOException('server has gone away'));
        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')->once()->with('synthetic insert')->andReturn($statement);
        $pdo->shouldNotReceive('lastInsertId');
        $connection = new MariaDbConnection($pdo, 'synthetic', '', ['name' => 'synthetic']);
        $reconnections = 0;
        $connection->setReconnector(function () use (&$reconnections): void {
            $reconnections++;
        });
        CatalogueArchiveImporter::preventReplay($connection);
        try {
            $connection->insert('synthetic insert');
            $this->fail('Ambiguous write must stop.');
        } catch (RuntimeException $e) {
            $this->assertSame('ARCHIVE_RECONNECT_FORBIDDEN', $e->getMessage());
        }
        $this->assertSame(0, $reconnections);
    }

    public function test_successful_insert_and_independent_connections_are_preserved(): void
    {
        $statement = Mockery::mock(PDOStatement::class);
        $statement->shouldReceive('execute')->once()->andReturn(true);
        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('prepare')->once()->andReturn($statement);
        $pdo->shouldReceive('lastInsertId')->once()->with(null)->andReturn('0');
        $connection = new MariaDbConnection($pdo, 'synthetic', '', ['name' => 'synthetic']);
        CatalogueArchiveImporter::preventReplay($connection);
        $this->assertTrue($connection->insert('synthetic insert'));
        $other = new MariaDbConnection(fn () => throw new RuntimeException('REAL_PDO_FORBIDDEN'));
        $called = false;
        $other->setReconnector(function () use (&$called): void {
            $called = true;
        });
        $other->reconnect();
        $this->assertTrue($called);
    }
}
