<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('market_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('exchange_trade_id')->nullable()->index();
            $table->string('mode')->default('paper');
            $table->string('side');
            $table->decimal('price', 36, 12);
            $table->decimal('amount', 36, 12);
            $table->decimal('quote_amount', 36, 12)->default(0);
            $table->decimal('fee', 36, 12)->default(0);
            $table->string('fee_asset')->nullable();
            $table->timestamp('filled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['deal_id', 'side']);
            $table->index(['market_id', 'filled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
