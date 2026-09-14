<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destinations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });

        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->char('currency', 3)->default('IDR');
            $table->bigInteger('unit_price');
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('service_fee_amount')->default(0);
            $table->string('validity_type', 30)->default('same_day');
            $table->unsignedInteger('validity_days')->nullable();
            $table->time('valid_from_time')->nullable();
            $table->time('valid_until_time')->nullable();
            $table->unsignedInteger('max_per_order')->default(20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['destination_id', 'code']);
            $table->index(['destination_id', 'is_active']);
        });

        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('external_ref', 80)->nullable();
            $table->string('display_name', 120)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email', 190)->nullable();
            $table->timestamps();

            $table->index('phone');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
        Schema::dropIfExists('ticket_types');
        Schema::dropIfExists('destinations');
    }
};
