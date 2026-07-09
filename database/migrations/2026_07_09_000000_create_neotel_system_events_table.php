<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('neotel_system_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('connection_id', 64)->nullable();
            $table->string('type', 40);
            $table->string('action', 60)->nullable();
            $table->string('server_name', 120)->nullable();
            $table->string('extension', 50)->nullable();
            $table->json('payload')->nullable();
            $table->longText('raw_payload')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'action']);
            $table->index('occurred_at');
            $table->index('connection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('neotel_system_events');
    }
};
