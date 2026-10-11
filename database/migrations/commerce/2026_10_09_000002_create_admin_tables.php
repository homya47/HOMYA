<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'commerce';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);
        $this->createOrResumeEmpty($schema, 'commerce_admin_products', [
            'slug', 'reference', 'content', 'price_cents', 'published', 'revision', 'created_at', 'updated_at',
        ], fn () => $schema->create('commerce_admin_products', function (Blueprint $t) {
            $t->string('slug', 221)->primary();
            $t->string('reference', 255);
            $t->text('content');
            $t->unsignedInteger('price_cents');
            $t->boolean('published')->default(false);
            $t->unsignedInteger('revision')->default(1);
            $t->timestamps();
        }));
        $this->createOrResumeEmpty($schema, 'commerce_admin_threads', [
            'id', 'customer_encrypted', 'access_hash', 'unread', 'created_at', 'updated_at',
        ], fn () => $schema->create('commerce_admin_threads', function (Blueprint $t) {
            $t->char('id', 36)->primary();
            $t->text('customer_encrypted');
            $t->char('access_hash', 64);
            $t->boolean('unread')->default(true)->index();
            $t->timestamps();
        }));
        $this->createOrResumeEmpty($schema, 'commerce_admin_messages', [
            'id', 'thread_id', 'sequence', 'author', 'body_encrypted', 'nonce', 'email_status', 'created_at',
        ], fn () => $schema->create('commerce_admin_messages', function (Blueprint $t) {
            $t->char('id', 36)->primary();
            $t->char('thread_id', 36);
            $t->foreign('thread_id')->references('id')->on('commerce_admin_threads')->restrictOnDelete();
            $t->unsignedInteger('sequence');
            $t->unique(['thread_id', 'sequence']);
            $t->string('author', 8);
            $t->text('body_encrypted');
            $t->char('nonce', 36)->unique();
            $t->string('email_status', 24)->default('not_requested');
            $t->timestamp('created_at');
            $t->index(['thread_id', 'created_at']);
        }));
        $this->createOrResumeEmpty($schema, 'commerce_admin_events', [
            'id', 'type', 'subject', 'count', 'payload_encrypted', 'read_at', 'created_at', 'updated_at',
        ], fn () => $schema->create('commerce_admin_events', function (Blueprint $t) {
            $t->char('id', 64)->primary();
            $t->string('type', 40);
            $t->string('subject', 255)->default('');
            $t->unsignedInteger('count')->default(1);
            $t->text('payload_encrypted')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['type', 'created_at']);
            $t->index(['read_at', 'created_at']);
        }));
        $this->createOrResumeEmpty($schema, 'commerce_admin_shipments', [
            'order_id', 'status', 'carrier', 'tracking_number', 'tracking_url', 'revision', 'created_at', 'updated_at',
        ], fn () => $schema->create('commerce_admin_shipments', function (Blueprint $t) {
            // MariaDB 10.7+ stores UUID as its own type; match commerce_orders.id exactly.
            $t->uuid('order_id')->primary();
            $t->foreign('order_id')->references('id')->on('commerce_orders')->restrictOnDelete();
            $t->string('status', 24);
            $t->string('carrier', 120)->default('');
            $t->string('tracking_number', 180)->default('');
            $t->string('tracking_url', 500)->default('');
            $t->unsignedInteger('revision')->default(1);
            $t->timestamps();
        }));
        $this->createOrResumeEmpty($schema, 'commerce_admin_reports', [
            'service', 'account', 'payload_encrypted', 'period', 'synced_at',
        ], fn () => $schema->create('commerce_admin_reports', function (Blueprint $t) {
            $t->string('service', 24)->primary();
            $t->string('account', 255);
            $t->text('payload_encrypted');
            $t->string('period', 60);
            $t->timestamp('synced_at');
        }));

        $this->alignShipmentOrderId();
        $this->ensureForeignKey('commerce_admin_messages', 'thread_id', 'commerce_admin_threads', 'id');
        $this->ensureForeignKey('commerce_admin_shipments', 'order_id', 'commerce_orders', 'id');
    }

    public function down(): void
    {
        throw new RuntimeException('Administrator data rollback requires its own reviewed retention and recovery plan.');
    }

    /**
     * MariaDB DDL is not transactional. Resume only a table left empty by an
     * interrupted execution of this migration, and refuse unknown schema/data.
     */
    private function createOrResumeEmpty($schema, string $table, array $expectedColumns, \Closure $create): void
    {
        if (! $schema->hasTable($table)) {
            $create();

            return;
        }

        $db = DB::connection($this->connection);
        $columns = $db->select(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            [$table],
        );
        if ($db->table($table)->exists() || array_column($columns, 'COLUMN_NAME') !== $expectedColumns) {
            throw new RuntimeException('Refusing to resume a populated or unexpected commerce table.');
        }
    }

    private function alignShipmentOrderId(): void
    {
        $db = DB::connection($this->connection);
        $parent = $db->selectOne(
            'SELECT DATA_TYPE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['commerce_orders', 'id'],
        );
        $child = $db->selectOne(
            'SELECT DATA_TYPE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['commerce_admin_shipments', 'order_id'],
        );

        if ($parent === null || $child === null) {
            throw new RuntimeException('Commerce order UUID columns are unavailable.');
        }

        if ($parent->COLUMN_TYPE !== $child->COLUMN_TYPE) {
            if ($parent->DATA_TYPE !== 'uuid' || $child->DATA_TYPE !== 'char'
                || $db->table('commerce_admin_shipments')->exists()) {
                throw new RuntimeException('Commerce order UUID columns do not match safely.');
            }

            $db->statement('ALTER TABLE `commerce_admin_shipments` MODIFY `order_id` UUID NOT NULL');
        }
    }

    private function ensureForeignKey(string $table, string $column, string $referencedTable, string $referencedColumn): void
    {
        $db = DB::connection($this->connection);
        $name = $table.'_'.$column.'_foreign';
        $constraint = $db->selectOne(
            'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            [$table, $name],
        );

        if ($constraint !== null) {
            if ($constraint->COLUMN_NAME !== $column || $constraint->REFERENCED_TABLE_NAME !== $referencedTable
                || $constraint->REFERENCED_COLUMN_NAME !== $referencedColumn) {
                throw new RuntimeException('Unexpected commerce foreign key encountered.');
            }

            return;
        }

        $db->statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$column}`) REFERENCES `{$referencedTable}` (`{$referencedColumn}`) ON DELETE RESTRICT");
    }
};
