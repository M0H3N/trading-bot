<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('market_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('exchange')->default('wallex');
            $table->string('symbol');
            $table->string('client_id')->unique();
            $table->string('external_id')->nullable()->index();
            $table->string('mode')->default('paper');
            $table->string('side');
            $table->string('type')->default('limit');
            $table->string('status')->default('pending');
            $table->decimal('price', 36, 12);
            $table->decimal('amount', 36, 12);
            $table->decimal('filled_amount', 36, 12)->default(0);
            $table->decimal('quote_amount', 36, 12)->default(0);
            $table->unsignedInteger('tick_offset')->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['market_id', 'side', 'status']);
            $table->index(['deal_id', 'status']);
            $table->index(['mode', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
