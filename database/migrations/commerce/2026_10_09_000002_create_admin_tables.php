<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'commerce';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);
        $schema->create('commerce_admin_products', function (Blueprint $t) {
            $t->string('slug', 221)->primary();
            $t->string('reference', 255);
            $t->text('content');
            $t->unsignedInteger('price_cents');
            $t->boolean('published')->default(false);
            $t->unsignedInteger('revision')->default(1);
            $t->timestamps();
        });
        $schema->create('commerce_admin_threads', function (Blueprint $t) {
            $t->char('id', 36)->primary();
            $t->text('customer_encrypted');
            $t->char('access_hash', 64);
            $t->boolean('unread')->default(true)->index();
            $t->timestamps();
        });
        $schema->create('commerce_admin_messages', function (Blueprint $t) {
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
        });
        $schema->create('commerce_admin_events', function (Blueprint $t) {
            $t->char('id', 64)->primary();
            $t->string('type', 40);
            $t->string('subject', 255)->default('');
            $t->unsignedInteger('count')->default(1);
            $t->text('payload_encrypted')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['type', 'created_at']);
            $t->index(['read_at', 'created_at']);
        });
        $schema->create('commerce_admin_shipments', function (Blueprint $t) {
            $t->char('order_id', 36)->primary();
            $t->foreign('order_id')->references('id')->on('commerce_orders')->restrictOnDelete();
            $t->string('status', 24);
            $t->string('carrier', 120)->default('');
            $t->string('tracking_number', 180)->default('');
            $t->string('tracking_url', 500)->default('');
            $t->unsignedInteger('revision')->default(1);
            $t->timestamps();
        });
        $schema->create('commerce_admin_reports', function (Blueprint $t) {
            $t->string('service', 24)->primary();
            $t->string('account', 255);
            $t->text('payload_encrypted');
            $t->string('period', 60);
            $t->timestamp('synced_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Administrator data rollback requires its own reviewed retention and recovery plan.');
    }
};
