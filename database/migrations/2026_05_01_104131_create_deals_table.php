<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('market_id')->constrained()->cascadeOnDelete();
            $table->string('mode')->default('paper');
            $table->string('status')->default('opening');
            $table->decimal('entry_average_price', 36, 12)->default(0);
            $table->decimal('entry_amount', 36, 12)->default(0);
            $table->decimal('exit_average_price', 36, 12)->default(0);
            $table->decimal('exit_amount', 36, 12)->default(0);
            $table->decimal('realized_pnl', 36, 12)->default(0);
            $table->decimal('realized_pnl_percent', 18, 8)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['market_id', 'status']);
            $table->index(['mode', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
