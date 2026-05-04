<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balances', function (Blueprint $table): void {
            $table->id();
            $table->string('exchange');
            $table->string('asset');
            $table->decimal('available', 36, 12)->default(0);
            $table->decimal('locked', 36, 12)->default(0);
            $table->string('mode')->default('paper');
            $table->timestamp('synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['exchange', 'asset', 'mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balances');
    }
};
