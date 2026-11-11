<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NetSendo connector: the OAuth side of the remote MCP endpoint (/mcp).
 *
 *   connector_clients      an AI host that asked to connect (Claude, Hermes, …)
 *   connector_auth_codes   one-time codes between the consent screen and the token endpoint
 *   connector_connections  what a user approved: which app, which permissions ("connected app")
 *   connector_tokens       access and refresh tokens of a connection (hashes only)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connector_clients', function (Blueprint $table) {
            $table->id();
            // Registered clients: a random identifier. Metadata-document clients:
            // sha256 of the document URL (the URL itself is too long to index).
            $table->string('identifier', 64)->unique();
            $table->string('registration_type', 20); // dynamic | metadata
            $table->text('metadata_url')->nullable();
            $table->string('name');
            $table->json('redirect_uris');
            $table->text('client_uri')->nullable();
            $table->text('logo_uri')->nullable();
            $table->timestamp('metadata_fetched_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('connector_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connector_client_id')->constrained('connector_clients')->cascadeOnDelete();
            $table->string('client_name');
            $table->json('scopes');
            $table->text('resource')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });

        Schema::create('connector_auth_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code_hash', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connector_client_id')->constrained('connector_clients')->cascadeOnDelete();
            $table->text('redirect_uri');
            $table->json('scopes');
            $table->string('code_challenge', 128);
            $table->text('resource')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            // Set when the code is exchanged, so a replayed code can end that connection.
            $table->foreignId('connector_connection_id')->nullable()
                ->constrained('connector_connections')->nullOnDelete();
            $table->timestamps();

            $table->index('expires_at');
        });

        Schema::create('connector_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connector_connection_id')->constrained('connector_connections')->cascadeOnDelete();
            $table->string('access_token_hash', 64)->unique();
            $table->timestamp('access_expires_at');
            $table->string('refresh_token_hash', 64)->unique();
            $table->timestamp('refresh_expires_at');
            // The refresh token was exchanged for a new pair.
            $table->timestamp('rotated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index('refresh_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_tokens');
        Schema::dropIfExists('connector_auth_codes');
        Schema::dropIfExists('connector_connections');
        Schema::dropIfExists('connector_clients');
    }
};
