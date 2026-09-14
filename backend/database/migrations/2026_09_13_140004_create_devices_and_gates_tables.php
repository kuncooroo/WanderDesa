<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('destination_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->string('controller_type', 40)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['destination_id', 'code']);
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 64)->unique();
            $table->string('terminal_id', 64)->nullable()->unique();
            $table->foreignId('destination_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('status', 30)->default('registered');
            $table->boolean('is_active')->default(false);
            $table->boolean('maintenance_mode')->default(false);
            $table->string('software_version', 40)->nullable();
            $table->string('hardware_version', 40)->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->string('activation_secret_hash')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->index(['destination_id', 'status']);
            $table->index('last_heartbeat_at');
            $table->index(['maintenance_mode', 'is_active']);
        });

        Schema::create('device_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('software_version', 40)->nullable();
            $table->boolean('printer_ok')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['device_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_heartbeats');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('gates');
    }
};
