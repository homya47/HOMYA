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
        $schema->create('commerce_inventory', function (Blueprint $table) {
            $table->string('slug', 221)->primary();
            $table->text('reference');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('reserved')->default(0);
            $table->unsignedInteger('approved_price_cents')->nullable();
            $table->boolean('sale_enabled')->default(false);
            $table->timestamp('validated_at');
            $table->timestamps();
        });
        $schema->create('commerce_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('owner_hash', 64)->index();
            $table->uuid('checkout_key')->unique();
            $table->char('request_hash', 64);
            $table->string('locale', 2);
            $table->string('country', 2);
            $table->string('currency', 3);
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('shipping_cents');
            $table->unsignedInteger('total_cents');
            $table->text('customer_encrypted');
            $table->string('status', 24)->default('pending_payment');
            $table->string('stripe_session_id', 255)->nullable()->unique();
            $table->string('stripe_payment_id', 255)->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
        $schema->create('commerce_order_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_id');
            $table->foreign('order_id')->references('id')->on('commerce_orders')->restrictOnDelete();
            $table->string('slug', 221);
            $table->string('line_key', 230);
            $table->string('variant_id', 4)->default('');
            $table->string('variant_name', 500)->default('');
            $table->string('variant_reference', 255)->default('');
            $table->string('reference', 255);
            $table->string('name', 500);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('line_cents');
            $table->unique(['order_id', 'line_key']);
        });
        $schema->create('commerce_payment_events', function (Blueprint $table) {
            $table->string('stripe_event_id', 255)->primary();
            $table->uuid('order_id');
            $table->foreign('order_id')->references('id')->on('commerce_orders')->restrictOnDelete();
            $table->char('payload_hash', 64);
            $table->string('outcome', 24);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        // Customer orders and the stock ledger cannot be silently discarded.
        throw new RuntimeException('Commerce rollback requires a reviewed backup and explicit data-retention plan.');
    }
};
