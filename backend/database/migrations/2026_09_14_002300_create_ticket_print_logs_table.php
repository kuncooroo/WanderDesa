<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_print_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->string('result', 20);
            $table->string('actor_type', 20)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('message', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_print_logs');
    }
};
