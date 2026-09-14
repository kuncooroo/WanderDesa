<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->bigInteger('initial_cash');
            $table->bigInteger('total_cash_sales')->default(0);
            $table->bigInteger('total_cash_refund')->default(0);
            $table->bigInteger('expected_cash')->default(0);
            $table->bigInteger('actual_cash')->nullable();
            $table->bigInteger('difference')->nullable();
            $table->string('status', 20)->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'opened_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('cashier_shift_id')
                ->nullable()
                ->constrained('cashier_shifts')
                ->nullOnDelete();
            $table->foreignId('refund_cashier_shift_id')
                ->nullable()
                ->constrained('cashier_shifts')
                ->nullOnDelete();

            $table->index('cashier_shift_id');
            $table->index('refund_cashier_shift_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refund_cashier_shift_id');
            $table->dropConstrainedForeignId('cashier_shift_id');
        });

        Schema::dropIfExists('cashier_shifts');
    }
};
