<?php

use App\Domain\Trading\Services\MarketBudgetService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('market_id')->constrained()->cascadeOnDelete();
            $table->string('deal_type');
            $table->string('budget_asset', 16);
            $table->decimal('budget', 36, 12)->default(0);
            $table->decimal('used_budget', 36, 12)->default(0);
            $table->timestamps();

            $table->unique(['market_id', 'deal_type']);
            $table->index(['deal_type', 'budget_asset']);
        });

        app(MarketBudgetService::class)->initialize();
    }

    public function down(): void
    {
        Schema::dropIfExists('market_budgets');
    }
};
