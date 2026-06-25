<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table): void {
            $table->boolean('exited')->default(false)->after('realized_pnl_percent');
            $table->decimal('unexited_amount', 36, 12)->default(0)->after('exited');

            $table->index('exited');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table): void {
            $table->dropIndex(['exited']);
            $table->dropColumn(['exited', 'unexited_amount']);
        });
    }
};
