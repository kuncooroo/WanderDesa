<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 20);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 80);
            $table->string('entity_type', 40)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->foreignId('destination_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['actor_type', 'actor_id', 'created_at']);
            $table->index(['destination_id', 'created_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->string('description')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('integration_settings', function (Blueprint $table) {
            $table->id();
            $table->string('integration', 40);
            $table->string('provider', 60);
            $table->boolean('is_active')->default(false);
            $table->json('config_json')->nullable();
            $table->text('secrets_encrypted')->nullable();
            $table->timestamps();

            $table->unique(['integration', 'provider']);
        });

        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 40)->default('local');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('checksum_sha256', 64)->nullable();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['disk', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
        Schema::dropIfExists('integration_settings');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('audit_logs');
    }
};
