<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transit_keys', function (Blueprint $table) {
            $table->id();
            $table->string('kid', 64)->unique();
            $table->string('public_key', 64);
            $table->text('secret_key')->nullable();
            $table->string('status', 16)->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('account_migrations', function (Blueprint $table) {
            $table->id();
            $table->char('intent_hash', 64)->unique();
            $table->text('intent_id');
            $table->string('source_host');
            $table->string('destination_host');
            $table->text('intent_jws');
            $table->string('intent_key_fingerprint', 71);
            $table->unsignedBigInteger('intent_leaf')->nullable();
            $table->text('entry_jws')->nullable();
            $table->timestamp('intent_received_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->char('receipt_hash', 64)->nullable()->index();
            $table->text('receipt_jws')->nullable();
            $table->string('receipt_key_fingerprint', 71)->nullable();
            $table->text('seal_jws')->nullable();
            $table->unsignedBigInteger('seal_leaf')->nullable();
            $table->unsignedInteger('receipts_count')->default(0);
            $table->timestamp('sealed_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('log_leaves', function (Blueprint $table) {
            $table->unsignedBigInteger('leaf_index')->primary();
            $table->string('kind', 16);
            $table->char('leaf_hash', 64)->index();
            $table->json('data');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('log_nodes', function (Blueprint $table) {
            $table->unsignedTinyInteger('level');
            $table->unsignedBigInteger('node_index');
            $table->char('hash', 64);
            $table->primary(['level', 'node_index']);
        });

        Schema::create('tree_heads', function (Blueprint $table) {
            $table->unsignedBigInteger('size')->primary();
            $table->char('root', 64);
            $table->text('jws');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('log_state', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('size');
            $table->char('root', 64);
            $table->timestamp('updated_at')->nullable();
        });

        DB::table('log_state')->insert([
            'id' => 1,
            'size' => 0,
            'root' => hash('sha256', ''),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('log_state');
        Schema::dropIfExists('tree_heads');
        Schema::dropIfExists('log_nodes');
        Schema::dropIfExists('log_leaves');
        Schema::dropIfExists('account_migrations');
        Schema::dropIfExists('transit_keys');
    }
};
