<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_code', 40)->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('destination_id')->constrained()->restrictOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('channel', 20);
            $table->string('status', 30)->default('issued');
            $table->char('currency', 3)->default('IDR');
            $table->bigInteger('unit_price_snapshot');
            $table->bigInteger('tax_snapshot')->default(0);
            $table->bigInteger('service_fee_snapshot')->default(0);
            $table->timestamp('valid_start_at');
            $table->timestamp('valid_end_at');
            $table->timestamp('issued_at');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('qr_payload', 512);
            $table->char('qr_payload_hash', 64)->unique();
            $table->unsignedSmallInteger('qr_version')->default(1);
            $table->string('qr_secret_hint', 64)->nullable();
            $table->timestamps();

            $table->index(['status', 'valid_end_at']);
            $table->index(['destination_id', 'status']);
        });

        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('destination_id')->constrained()->restrictOnDelete();
            $table->foreignId('gate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('result', 20)->default('allow');
            $table->timestamp('checked_in_at');
            $table->foreignId('checked_in_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_context', 120)->nullable();
            $table->timestamps();

            $table->index(['destination_id', 'checked_in_at']);
            $table->index(['gate_id', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
        Schema::dropIfExists('tickets');
    }
};
