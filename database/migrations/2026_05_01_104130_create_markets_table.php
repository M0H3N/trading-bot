<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('markets', function (Blueprint $table): void {
            $table->id();
            $table->string('exchange')->default('wallex');
            $table->string('symbol');
            $table->string('base_asset');
            $table->string('quote_asset')->default('TMN');
            $table->decimal('tick_size', 36, 12)->default(1);
            $table->decimal('step_size', 36, 12)->default(1);
            $table->decimal('min_order_amount', 36, 12)->default(0);
            $table->boolean('is_active')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['exchange', 'symbol']);
            $table->index(['is_active', 'exchange']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('markets');
    }
};
