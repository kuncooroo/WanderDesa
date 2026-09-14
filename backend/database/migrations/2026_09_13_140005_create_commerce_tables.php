<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 40)->unique();
            $table->foreignId('destination_id')->constrained()->restrictOnDelete();
            $table->string('channel', 20);
            $table->string('status', 30)->default('pending_payment');
            $table->foreignId('visitor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('currency', 3)->default('IDR');
            $table->bigInteger('subtotal');
            $table->bigInteger('discount_total')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('service_fee_total')->default(0);
            $table->bigInteger('grand_total');
            $table->string('discount_code', 40)->nullable();
            $table->string('customer_note')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['destination_id', 'created_at']);
            $table->index(['channel', 'created_at']);
            $table->index('expires_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->restrictOnDelete();
            $table->string('ticket_type_code', 40);
            $table->string('ticket_type_name', 160);
            $table->unsignedInteger('quantity');
            $table->bigInteger('unit_price');
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('service_fee_amount')->default(0);
            $table->bigInteger('line_subtotal');
            $table->bigInteger('line_tax_total')->default(0);
            $table->bigInteger('line_service_fee_total')->default(0);
            $table->bigInteger('line_grand_total');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 40)->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('pending');
            $table->string('method', 30);
            $table->string('provider', 40)->nullable();
            $table->bigInteger('amount');
            $table->char('currency', 3)->default('IDR');
            $table->string('provider_payment_id', 120)->nullable()->unique();
            $table->string('provider_reference', 120)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('failure_code', 60)->nullable();
            $table->string('failure_message')->nullable();
            $table->foreignId('collected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['provider', 'status']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('event_id', 120);
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->char('payload_hash', 64);
            $table->timestamp('processed_at')->nullable();
            $table->string('process_status', 20)->default('received');
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->char('key_hash', 64);
            $table->string('scope', 40);
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id');
            $table->char('request_hash', 64);
            $table->unsignedInteger('response_code')->nullable();
            $table->string('resource_type', 40)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['scope', 'actor_type', 'actor_id', 'key_hash']);
            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
